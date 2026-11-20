<?php
namespace PrestaShop\PrestaShop\Core\Module { interface WidgetInterface {} }
namespace {
if (PHP_SAPI !== 'cli' || !str_contains(ini_get('disable_functions'), 'curl_exec')) { exit(2); }
define('_PS_VERSION_', '9.2.0');
foreach (['CURLOPT_URL','CURLOPT_RETURNTRANSFER','CURLOPT_POST','CURLOPT_POSTFIELDS','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLINFO_HTTP_CODE'] as $i => $name) { if (!defined($name)) { define($name, $i); } }
$transportResult = '{"success":true}';
$transportCalls = 0;
$transportStatus = 200;
function curl_init() { return new \stdClass(); }
function curl_setopt($handle, $key, $value) { return true; }
function curl_exec($handle) { global $transportResult, $transportCalls; ++$transportCalls; return $transportResult; }
function curl_error($handle) { return 'sensitive transport diagnostic'; }
function curl_getinfo($handle,$key) { global $transportStatus; return $transportStatus; }
class Configuration {
    public static array $values = [];
    public static array $writes = [];
    public static bool $failWrites = false;
    public static function get($key) { return self::$values[$key] ?? false; }
    public static function updateValue($key, $value) {
        self::$writes[] = $key;
        if (self::$failWrites) { return false; }
        self::$values[$key] = $value;
        return true;
    }
}
class Tools {
    public static function getValue($key, $default = false) { return $_POST[$key] ?? $_GET[$key] ?? $default; }
    public static function isSubmit($key) { return isset($_POST[$key]) || isset($_GET[$key]); }
    public static function redirect($url) { throw new \RuntimeException('redirect:' . $url); }
}
class Context {
    public static $instance;
    public $customer;
    public $controller;
    public $cookie, $link, $smarty;
    public static function getContext() { return self::$instance; }
    public function getTranslator() { return new class { public function trans($text, $args = [], $domain = '') { return strtr($text, $args); } }; }
}
#[\AllowDynamicProperties]
class Module {
    public function __construct() { $this->context = Context::getContext(); }
    public function trans($text, $args = [], $domain = '') { return strtr($text, $args); }
    public function displayError($text) { return 'ERROR:' . $text; }
    public function displayConfirmation($text) { return 'SUCCESS:' . $text; }
    public $smarty = null;
    public function getDefaultCompileId() { return null; }
}
// Records each widget template instance; the real template is rendered by the native suites.
class Smarty {
    public const CACHING_OFF = 0;
    public array $templates = [];
    public function createTemplate($file, $cacheId = null, $compileId = null, $parent = null) {
        return $this->templates[] = new class($file) {
            public $caching = 1;
            public array $vars = [];
            public function __construct(public string $file) {}
            public function assign(array $vars) { $this->vars = $vars + $this->vars; }
            public function fetch() { return 'TEMPLATE:' . json_encode($this->vars); }
        };
    }
}
class Customer { public function isLogged() { return false; } }
class FakeFrontController {
    public array $errors = [], $styles = [], $scripts = [];
    public function registerStylesheet($id, $path, array $params = []) { $this->styles[$id] = [$path, $params]; }
    public function registerJavascript($id, $path, array $params = []) { $this->scripts[$id] = [$path, $params]; }
}
class ContactController extends FakeFrontController {}
class RegistrationController extends FakeFrontController {}
class IndexController extends FakeFrontController {}
class ProductController extends FakeFrontController {}
class AuthController extends FakeFrontController {}
class PasswordController extends FakeFrontController {}
class OrderController extends FakeFrontController {}
class SimplewithdrawalWithdrawalModuleFrontController extends FakeFrontController {}
class Cookie { public array $data = []; public function __get($key) { return $this->data[$key] ?? null; } public function __set($key,$value) { $this->data[$key]=$value; } public function __unset($key) { unset($this->data[$key]); } }
Context::$instance = new Context();
Context::$instance->customer = new Customer();
Context::$instance->controller = new ContactController();
Context::$instance->cookie = new Cookie();
Context::$instance->link = new class { public function getPageLink($page) { return 'https://fixture.invalid/'; } };
require dirname(__DIR__) . '/pixel_cloudflare_turnstile.php';
class ReviewTurnstile extends Pixel_cloudflare_turnstile {
    public function getCurrentThemeName(): string { return 'GreenMouse'; }
    public function error() { return self::$validationError; }
    public function returnTarget() { return self::returnUrl(); }
    public function displayForm(): string { return ''; }
    public function process(string $controller, bool $validate = false): bool { return $this->canProcess($controller, $validate); }
    public static function newRequest(): void { self::$providerScriptLoaded = false; }
}
$module = new ReviewTurnstile();
$checks = [];
$findings = [];
function check($name, $ok) { global $checks; $checks[$name] = (bool) $ok; if (!$ok) { throw new \RuntimeException($name); } }
$valid = [
    'CLOUDFLARE_TURNSTILE_SITEKEY' => 'synthetic-sitekey',
    'CLOUDFLARE_TURNSTILE_SECRET_KEY' => 'synthetic-secret',
    'CLOUDFLARE_TURNSTILE_THEME' => 'light',
    'CLOUDFLARE_TURNSTILE_APPEARANCE' => 'always',
    'CLOUDFLARE_TURNSTILE_TEST_MODE' => 'disabled',
    'CLOUDFLARE_TURNSTILE_FORMS' => 'contact,register,newsletter',
];
Configuration::$values = $valid;
$_GET = $_POST = [];
foreach (['ContactController','RegistrationController'] as $controller) { check($controller . ' display enabled', $module->process($controller)); }
check('login disabled by current configuration', !$module->process('AuthController'));
check('checkout explicitly excluded', !$module->process('OrderController'));
check('contact GET does not validate', !$module->process('ContactController', true));
$_POST = ['submitMessage' => '1'];
check('contact POST validation selected', $module->process('ContactController', true));
$_POST = ['submitCreate' => '1'];
check('registration POST validation selected', $module->process('RegistrationController', true));
check('missing token rejected without network', !Pixel_cloudflare_turnstile::turnstileValidation() && $transportCalls === 0);
$hookError = null;
$module->hookActionNewsletterRegistrationBefore(['hookError' => &$hookError]);
check('newsletter missing token propagates error reference', is_string($hookError) && $hookError !== '');
foreach ([['{"success":true}',true], ['{"success":false,"error-codes":["timeout-or-duplicate"]}',false], ['not-json',false], [false,false]] as $i => [$transportResult,$expected]) {
    $_POST = ['cf-turnstile-response' => 'synthetic-token'];
    check('synthetic provider case ' . $i, Pixel_cloudflare_turnstile::turnstileValidation() === $expected);
}
foreach (['disabled','pass','fail','interactive'] as $mode) {
    Configuration::$values['CLOUDFLARE_TURNSTILE_TEST_MODE'] = $mode;
    $widget = $module->getWidgetVariables('', ['custom' => 1, 'action' => 'simple-withdrawal']);
    check($mode . ' custom withdrawal widget variables', is_string($widget['sitekey']) && $widget['action'] === 'simple-withdrawal');
}
Configuration::$values=$valid;
foreach([[],str_repeat('x',2049),''] as $token) {
    $_POST=['cf-turnstile-response'=>$token]; $calls=$transportCalls;
    check('Malformed token refused '.count($checks),!Pixel_cloudflare_turnstile::turnstileValidation() && $calls===$transportCalls);
}
foreach(['{"success":"true"}','{"success":1}','{"success":"false"}','true','[]','{"success":false,"error-codes":"private"}','{"success":false,"error-codes":[["private"]]}'] as $raw) {
    $_POST=['cf-turnstile-response'=>'synthetic-token']; $transportResult=$raw;
    check('Strict provider response '.count($checks),!Pixel_cloudflare_turnstile::turnstileValidation());
}
$transportResult='{"success":true}'; $transportStatus=503;
check('HTTP failures cannot pass',!Pixel_cloudflare_turnstile::turnstileValidation());
check('No raw cURL diagnostic',!str_contains($module->error(),'sensitive'));
$transportStatus=200;
check('Success clears previous error',Pixel_cloudflare_turnstile::turnstileValidation() && $module->error()===null);
Configuration::$values['CLOUDFLARE_TURNSTILE_SECRET_KEY']='';$calls=$transportCalls;
check('Missing secret refuses before provider',!Pixel_cloudflare_turnstile::turnstileValidation() && $calls===$transportCalls);
Configuration::$values=$valid;Configuration::$values['CLOUDFLARE_TURNSTILE_TEST_MODE']='unknown';
check('Unknown stored mode cannot enable test bypass',$module->getWidgetVariables('',[])['sitekey']==='synthetic-sitekey');
foreach([['action'=>[]],['action'=>'bad action'],['action'=>str_repeat('x',33)],['theme'=>'invalid'],['appearance'=>[]]] as $input) {
    $denied=false;try {$module->getWidgetVariables('',$input);}catch(InvalidArgumentException $e){$denied=true;}
    check('Invalid widget input refused '.count($checks),$denied);
}
foreach(['https://example.invalid/','//example.invalid/','https://fixture.invalid:444/','http://fixture.invalid/','https://user:pass@fixture.invalid/',"https://fixture.invalid/\r\nLocation: bad",'https://fixture.invalid\\@other.invalid/',''] as $referer) {
    $_SERVER['HTTP_REFERER']=$referer;
    check('Redirect origin boundary '.count($checks),$module->returnTarget()==='https://fixture.invalid/');
}
$_SERVER['HTTP_REFERER']='https://fixture.invalid/contact?test=1';
check('Same-origin return preserved',$module->returnTarget()===$_SERVER['HTTP_REFERER']);

// 1.2.2: the provider script loads only where a widget can render, once per request.
$api = Pixel_cloudflare_turnstile::PROVIDER_SCRIPT_URL;
check('provider script URL unchanged', $api === 'https://challenges.cloudflare.com/turnstile/v0/api.js');
function page(string $controller) { ReviewTurnstile::newRequest(); $_GET = $_POST = []; return Context::$instance->controller = new $controller(); }
function widget(string $html) { return str_starts_with($html, 'TEMPLATE:') ? json_decode(substr($html, 9), true) : null; }
Configuration::$values = $valid;
Context::$instance->smarty = $smarty = new Smarty();
$headScript = ['cloudflare-turnstile' => [$api, ['server' => 'remote', 'position' => 'head', 'priority' => 100, 'attributes' => 'async']]];
$style = ['cloudflare-turnstile' => ['modules/pixel_cloudflare_turnstile/views/css/turnstile.css', ['position' => 'head', 'priority' => 100]]];
$pages = ['IndexController', 'ProductController', 'ContactController', 'RegistrationController', 'AuthController', 'PasswordController', 'OrderController', 'SimplewithdrawalWithdrawalModuleFrontController'];
foreach (['contact,register,newsletter' => ['ContactController', 'RegistrationController'], 'login,password,newsletter' => ['AuthController', 'PasswordController'], '' => []] as $forms => $protected) {
    Configuration::$values['CLOUDFLARE_TURNSTILE_FORMS'] = $forms;
    foreach ($pages as $controller) {
        $page = page($controller); $module->hookActionFrontControllerSetMedia();
        check("[$forms] $controller keeps the stylesheet", $page->styles === $style);
        check("[$forms] $controller head script only on protected forms", $page->scripts === (in_array($controller, $protected, true) ? $headScript : []));
    }
}
Configuration::$values = $valid;
$page = page('IndexController'); $module->hookActionFrontControllerSetMedia(); $before = count($smarty->templates);
$first = widget($module->hookDisplayNewsletterRegistration([]));
$second = widget($module->hookDisplayNewsletterRegistration([]));
$custom = widget($module->renderWidget(null, ['custom' => 1, 'action' => 'custom-form']));
check('home: no head script', $page->scripts === []);
check('home: first newsletter widget carries the provider script', $first['provider_script'] === $api && $first['sitekey'] === 'synthetic-sitekey');
check('home: later widgets do not repeat it', $second['provider_script'] === '' && $custom['provider_script'] === '');
check('home: widget templates stay uncached', count($smarty->templates) === $before + 3 && array_sum(array_map(fn ($t) => $t->caching, $smarty->templates)) === 0);
$page = page('ContactController'); $module->hookActionFrontControllerSetMedia();
$newsletter = widget($module->hookDisplayNewsletterRegistration([]));
$contact = widget($module->renderWidget(null, []));
check('contact: head script and no second copy beside widgets', $page->scripts === $headScript && $newsletter['provider_script'] === '' && $contact['provider_script'] === '' && $contact['action'] === 'contact');
$page = page('RegistrationController'); $module->hookActionFrontControllerSetMedia();
$account = widget($module->hookDisplayCustomerAccountForm());
check('registration: hooked widget relies on the head script', $page->scripts === $headScript && $account['provider_script'] === '' && $account['action'] === 'registration');
$page = page('SimplewithdrawalWithdrawalModuleFrontController'); $module->hookActionFrontControllerSetMedia();
$first = widget($module->renderWidget(null, ['custom' => 1, 'action' => 'simple-withdrawal']));
$second = widget($module->renderWidget(null, ['custom' => 1, 'action' => 'simple-withdrawal']));
check('custom form page: script once beside the first widget', $page->scripts === [] && $first['provider_script'] === $api && $second['provider_script'] === '');
page('IndexController');
check('widget without setMedia still loads the script', widget($module->hookDisplayNewsletterRegistration([]))['provider_script'] === $api);
$page = page('OrderController'); $module->hookActionFrontControllerSetMedia(); $before = count($smarty->templates);
check('checkout: no account widget, no template, no script', $module->hookDisplayCustomerAccountForm() === '' && count($smarty->templates) === $before && $page->scripts === []);
Configuration::$values['CLOUDFLARE_TURNSTILE_FORMS'] = 'contact,register';
$page = page('IndexController'); $module->hookActionFrontControllerSetMedia(); $before = count($smarty->templates);
check('newsletter disabled: home renders no widget and no script', $module->hookDisplayNewsletterRegistration([]) === '' && count($smarty->templates) === $before && $page->scripts === []);
check('disabled newsletter does not consume the script', widget($module->renderWidget(null, ['custom' => 1, 'action' => 'custom-form']))['provider_script'] === $api);
Configuration::$values = $valid; Configuration::$values['CLOUDFLARE_TURNSTILE_SITEKEY'] = '';
page('IndexController');
$missing = widget($module->hookDisplayNewsletterRegistration([]));
check('missing sitekey: no widget markup, no script', $missing['sitekey'] === null && $missing['provider_script'] === '');
Configuration::$values = $valid;
check('missing sitekey does not consume the script', widget($module->hookDisplayNewsletterRegistration([]))['provider_script'] === $api);
echo count($checks)." provider/form/widget/redirect/asset checks passed; no real network calls.\n";
}
