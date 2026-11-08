<?php
namespace PrestaShop\PrestaShop\Core\Module { interface WidgetInterface {} }
namespace {
define('_PS_VERSION_',$argv[1]??'9.2.0');
#[AllowDynamicProperties]
class Module {
    public static int $installs=0, $upgrades=0;
    protected $_errors=[];
    public function __construct() {}
    public function trans($text,$args=[],$domain="") { return $text; }
    public function install() { ++self::$installs; return true; }
    public function registerHook($hook) { return true; }
    public function runUpgradeModule() { ++self::$upgrades; return ['success'=>true]; }
}
class PrestaShopException extends Exception {}
require dirname(__DIR__).'/pixel_cloudflare_turnstile.php';
require dirname(__DIR__).'/upgrade/upgrade-1.2.0.php';
$supported=PHP_VERSION_ID>=80500 && PHP_VERSION_ID<80600 && version_compare(_PS_VERSION_,'9.2.0','>=') && version_compare(_PS_VERSION_,'9.2.99','<=');
$module=new Pixel_cloudflare_turnstile();
if($module->install()!==$supported || Module::$installs!==(int)$supported) throw new RuntimeException('Install runtime gate failed');
if(upgrade_module_1_2_0($module)!==$supported) throw new RuntimeException('Upgrade entry gate failed');
$refused=false;
try { $module->runUpgradeModule(); } catch(PrestaShopException $error) { $refused=true; }
if($refused===$supported || Module::$upgrades!==(int)$supported) throw new RuntimeException('Native upgrade gate failed before writes');
echo 'Runtime '.PHP_VERSION.' / PS '._PS_VERSION_.' '.($supported?'accepted':'refused before writes').PHP_EOL;
}
