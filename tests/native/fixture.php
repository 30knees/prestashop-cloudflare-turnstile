<?php
/** Disposable native PS9 verification. @author 30knees */
if(PHP_SAPI!=='cli' || getenv('TURNSTILE_TEST_SHOP')!=='1') throw new RuntimeException('Disposable CLI only');
require '/var/www/html/config/config.inc.php';
if(ShopUrl::getMainShopDomain()!=='localhost:19105' || _PS_VERSION_!=='9.2.0' || PHP_VERSION_ID<80500) throw new RuntimeException('Wrong test target');
Shop::setContext(Shop::CONTEXT_SHOP,1);
$context=Context::getContext(); $context->shop=new Shop(1); $context->employee=new Employee(1);
$action=$argv[1]??''; $checks=0; $name='pixel_cloudflare_turnstile'; $db=Db::getInstance();
function checkFixture($ok,$message) { global $checks; ++$checks; if(!$ok) throw new RuntimeException($message); }
function configurationDigest(): string {
    return hash('sha256',json_encode(Db::getInstance()->executeS("SELECT * FROM `"._DB_PREFIX_."configuration` WHERE name LIKE 'CLOUDFLARE_TURNSTILE_%' ORDER BY id_configuration",true,false)));
}
function preservedState(): array {
    $db=Db::getInstance(); $result=[];
    foreach(['customer','orders','order_detail','cart','cart_product','stock_available'] as $table) {
        $rows=array_map('json_encode',$db->executeS('SELECT * FROM `'._DB_PREFIX_.$table.'`',true,false)); sort($rows);
        $result[$table]=hash('sha256',implode("\n",$rows));
    }
    $result['policy']=hash('sha256',json_encode($db->executeS("SELECT * FROM `"._DB_PREFIX_."configuration` WHERE name IN ('PS_DISABLE_NON_NATIVE_MODULE','PS_SHOP_ENABLE','PS_MAIL_METHOD','PS_SHOP_DOMAIN','PS_SHOP_DOMAIN_SSL') ORDER BY id_configuration",true,false)));
    $result['core']=hash_file('sha256',_PS_ROOT_DIR_.'/classes/Tools.php');
    return $result;
}
if($action==='prepare') {
    Configuration::updateValue('PS_MAIL_METHOD',3);
    Configuration::updateValue('PS_SHOP_NAME','Turnstile synthetic qualification');
}
checkFixture((int)Configuration::get('PS_MAIL_METHOD')===3,'Mail must remain disabled');
if($action==='extract') {
    $zip=new ZipArchive(); checkFixture($zip->open($argv[2])===true,'ZIP unavailable');
    for($i=0;$i<$zip->numFiles;++$i) {
        $entry=$zip->getNameIndex($i);
        checkFixture(str_starts_with($entry,$name.'/') && !str_contains($entry,'..') && !str_contains($entry,'\\'),'Unsafe ZIP member');
    }
    // Only the disposable module directory may be replaced; no staging data is copied.
    $target=_PS_MODULE_DIR_.$name;
    if(is_dir($target)) {
        checkFixture(!is_link($target) && realpath($target)==='/var/www/html/modules/'.$name,'Wrong module root');
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            checkFixture(!$file->isLink() && str_starts_with($file->getRealPath(),$target.'/'),'Unexpected module path');
            $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        }
    }
    checkFixture($zip->extractTo(_PS_MODULE_DIR_),'Extraction failed'); $zip->close();
} elseif(in_array($action,['install','upgrade','disable','enable','reset','uninstall'],true)) {
    $kernel=new AdminKernel('prod',false); $kernel->boot(); $container=$kernel->getContainer(); $context->container=$container;
    $container->get(PrestaShop\PrestaShop\Core\Context\ContextBuilderPreparer::class)->prepareFromLegacyContext($context);
    $manager=$container->get(PrestaShop\PrestaShop\Core\Module\ModuleManager::class);
    checkFixture($manager->$action($name),'Native '.$action.' failed: '.json_encode($manager->getError($name)));
} elseif($action==='status') {
    $module=Module::getInstanceByName($name); $version=$argv[2]??'1.2.0';
    checkFixture($module->version===$version && $db->getValue("SELECT version FROM `"._DB_PREFIX_."module` WHERE name='".$name."'",false)===$version,'Installed/code mismatch');
    foreach(['actionFrontControllerSetMedia','displayCustomerAccountForm','displayNewsletterRegistration','actionNewsletterRegistrationBefore','actionFrontControllerInitBefore'] as $hook) checkFixture($module->isRegisteredInHook($hook),'Missing hook '.$hook);
    if($version==='1.2.0') {
        $status=$module->getStatus();
        checkFixture($status['installed_version']===$version && count($status['hooks'])===5,'Status mismatch');
        $json=json_encode($status);
        checkFixture(!str_contains($json,'synthetic-secret') && !str_contains($json,'1x00000000000000000000AA'),'Status contains no key material');
    }
} elseif($action==='seed') {
    foreach(['SITEKEY'=>'1x00000000000000000000AA','SECRET_KEY'=>'synthetic-secret-retain','THEME'=>'auto','FORMS'=>'contact,login,register,password,newsletter'] as $key=>$value) checkFixture(Configuration::updateValue('CLOUDFLARE_TURNSTILE_'.$key,$value),'Seed failed');
} elseif($action==='snapshot') {
    file_put_contents('/tmp/turnstile-before.json',json_encode(preservedState()));
    file_put_contents('/tmp/turnstile-config.sha256',configurationDigest());
    file_put_contents('/tmp/turnstile-hooks.json',json_encode($db->executeS("SELECT hm.* FROM `"._DB_PREFIX_."hook_module` hm JOIN `"._DB_PREFIX_."module` m ON m.id_module=hm.id_module WHERE m.name='".$name."' ORDER BY hm.id_hook,hm.id_shop",true,false)));
} elseif($action==='preserved') {
    checkFixture(preservedState()===json_decode(file_get_contents('/tmp/turnstile-before.json'),true),'Commerce, policy or core changed');
    checkFixture(configurationDigest()===file_get_contents('/tmp/turnstile-config.sha256'),'Settings rows or credentials changed');
} elseif($action==='hooks-preserved') {
    checkFixture($db->executeS("SELECT hm.* FROM `"._DB_PREFIX_."hook_module` hm JOIN `"._DB_PREFIX_."module` m ON m.id_module=hm.id_module WHERE m.name='".$name."' ORDER BY hm.id_hook,hm.id_shop",true,false)===json_decode(file_get_contents('/tmp/turnstile-hooks.json'),true),'Upgrade changed hook positions');
} elseif($action==='uninstalled') {
    checkFixture(!Module::isInstalled($name),'Module registration remains');
    checkFixture(!(int)$db->getValue("SELECT COUNT(*) FROM `"._DB_PREFIX_."hook_module` hm JOIN `"._DB_PREFIX_."module` m ON m.id_module=hm.id_module WHERE m.name='".$name."'",false),'Hooks remain');
} elseif($action==='render') {
    $module=Module::getInstanceByName($name);
    $context->controller=new FrontController();
    $context->smarty->caching=Smarty::CACHING_LIFETIME_CURRENT;
    foreach([['contact','dark','always'],['login','light','interaction-only'],['contact','auto','execute']] as [$form,$theme,$appearance]) {
        $html=$module->renderWidget(null,['custom'=>1,'action'=>$form,'theme'=>$theme,'appearance'=>$appearance]);
        checkFixture(str_contains($html,'data-action="'.$form.'"') && str_contains($html,'data-theme="'.$theme.'"') && str_contains($html,'data-appearance="'.$appearance.'"'),'Widget reused stale cached options');
    }
    Configuration::updateValue('CLOUDFLARE_TURNSTILE_SITEKEY','synthetic" onload="bad');
    try {
        $html=$module->renderWidget(null,['custom'=>1,'action'=>'escape']);
        checkFixture(!str_contains($html,' onload="bad') && str_contains($html,'&quot;'),'Widget attributes must be escaped');
    } finally { Configuration::updateValue('CLOUDFLARE_TURNSTILE_SITEKEY','1x00000000000000000000AA'); }
    // Updating and restoring a value changes date_upd; compare content, not this synthetic write's timestamp.
    checkFixture(Configuration::get('CLOUDFLARE_TURNSTILE_SECRET_KEY')==='synthetic-secret-retain','Rendering changed secret');
} elseif($action==='provider') {
    $module=Module::getInstanceByName($name);
    $_POST['cf-turnstile-response']='XXXX.DUMMY.TOKEN.XXXX';
    $before=Configuration::get('CLOUDFLARE_TURNSTILE_TEST_MODE');
    try {
        foreach(['pass'=>true,'fail'=>false] as $mode=>$expected) {
            Configuration::updateValue('CLOUDFLARE_TURNSTILE_TEST_MODE',$mode);
            checkFixture(Pixel_cloudflare_turnstile::turnstileValidation()===$expected,'Official dummy provider mode '.$mode);
        }
    } finally {
        $before===false?Configuration::deleteByName('CLOUDFLARE_TURNSTILE_TEST_MODE'):Configuration::updateValue('CLOUDFLARE_TURNSTILE_TEST_MODE',$before);
    }
} elseif($action==='browser-ready') {
    Configuration::updateValue('CLOUDFLARE_TURNSTILE_TEST_MODE','pass');
} elseif($action!=='prepare') throw new RuntimeException('Unknown fixture action');
echo 'Native '.$action.': '.$checks.' assertions passed; PS '._PS_VERSION_.', PHP '.PHP_VERSION.PHP_EOL;
