<?php
/** Real database failure and same-process cache recovery; authority is tested over HTTP. */
if(PHP_SAPI!=='cli' || getenv('TURNSTILE_TEST_SHOP')!=='1') throw new RuntimeException('Disposable CLI only');
require '/var/www/html/config/config.inc.php';
if(ShopUrl::getMainShopDomain()!=='localhost:19105') throw new RuntimeException('Wrong fixture');
require_once _PS_MODULE_DIR_.'pixel_cloudflare_turnstile/pixel_cloudflare_turnstile.php';
Shop::setContext(Shop::CONTEXT_SHOP,1); Context::getContext()->shop=new Shop(1);
class AtomicTurnstileFixture extends Pixel_cloudflare_turnstile {
    public function canConfigureSettings(): bool { return true; }
    public function isSettingsWriteAllowed(): bool { return true; }
    public function displayForm(): string { return ''; }
    public function getCurrentThemeName(): string { return 'synthetic'; }
}
$module=new AtomicTurnstileFixture(); $db=Db::getInstance(); $keys=['SITEKEY','SECRET_KEY','THEME','APPEARANCE','TEST_MODE','FORMS']; $before=[];
foreach($keys as $key) $before['CLOUDFLARE_TURNSTILE_'.$key]=Configuration::get('CLOUDFLARE_TURNSTILE_'.$key);
$_POST=$before; $_POST['CLOUDFLARE_TURNSTILE_FORMS']=explode(',',$_POST['CLOUDFLARE_TURNSTILE_FORMS']);
$_POST['CLOUDFLARE_TURNSTILE_APPEARANCE']=$_POST['CLOUDFLARE_TURNSTILE_APPEARANCE']?:'always';
$_POST['CLOUDFLARE_TURNSTILE_TEST_MODE']=$_POST['CLOUDFLARE_TURNSTILE_TEST_MODE']?:'disabled';
$_POST['CLOUDFLARE_TURNSTILE_SITEKEY']='synthetic-rollback-sitekey';
$_POST['CLOUDFLARE_TURNSTILE_THEME']='dark'; $_POST['submitpixel_cloudflare_turnstile']='1';
$trigger=_DB_PREFIX_.'turnstile_qualification_failure'; $checks=0;
function atomicCheck($ok,$message) { global $checks; ++$checks; if(!$ok) throw new RuntimeException($message); }
try {
    atomicCheck($db->execute('CREATE TRIGGER `'.$trigger.'` BEFORE UPDATE ON `'._DB_PREFIX_."configuration` FOR EACH ROW BEGIN IF NEW.name='CLOUDFLARE_TURNSTILE_THEME' AND NEW.value='dark' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic private failure'; END IF; END"),'Create failure trigger');
    $html=$module->getContent();
    atomicCheck(str_contains($html,'Settings were not saved') && !str_contains($html,'synthetic private failure'),'Sanitized real storage failure');
    foreach($before as $key=>$value) atomicCheck(Configuration::get($key)===$value,'Native cache rollback '.$key);
    atomicCheck($db->getValue("SELECT value FROM `"._DB_PREFIX_."configuration` WHERE name='CLOUDFLARE_TURNSTILE_SITEKEY'",false)===$before['CLOUDFLARE_TURNSTILE_SITEKEY'],'Primary storage rollback');
    $db->execute('DROP TRIGGER `'.$trigger.'`');
    atomicCheck(str_contains($module->getContent(),'Settings updated'),'Same-process retry succeeds');
    atomicCheck(Configuration::get('CLOUDFLARE_TURNSTILE_SITEKEY')==='synthetic-rollback-sitekey','Retry cache updated');
    atomicCheck($db->getValue("SELECT value FROM `"._DB_PREFIX_."configuration` WHERE name='CLOUDFLARE_TURNSTILE_SITEKEY'",false)==='synthetic-rollback-sitekey','Retry primary storage updated');
} finally {
    $db->execute('DROP TRIGGER IF EXISTS `'.$trigger.'`');
    foreach($before as $key=>$value) $value===false?Configuration::deleteByName($key):Configuration::updateValue($key,$value);
    Configuration::resetStaticCache();
}
echo "$checks native transaction/cache assertions passed.\n";
