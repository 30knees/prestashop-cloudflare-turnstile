<?php
/** Native translation and failure fixtures, confined to the disposable shop. */
if (PHP_SAPI !== 'cli' || getenv('TURNSTILE_TEST_SHOP') !== '1') throw new RuntimeException('Disposable CLI only');
require '/var/www/html/config/config.inc.php';
if (ShopUrl::getMainShopDomain() !== 'localhost:19105' || (int) Configuration::get('PS_MAIL_METHOD') !== 3) throw new RuntimeException('Wrong fixture');
$context = Context::getContext();
Shop::setContext(Shop::CONTEXT_SHOP, 1);
$context->shop = new Shop(1);
$context->employee = new Employee(1);
$db = Db::getInstance();
$action = $argv[1] ?? '';
$locale = $argv[2] ?? 'en-US';
if (!in_array($locale, ['en-US', 'de-DE', 'es-ES', 'fr-FR', 'it-IT'], true)) throw new RuntimeException('Unexpected locale');
$trigger = _DB_PREFIX_ . 'turnstile_translation_failure';
if ($action === 'language') {
    $id = Language::getIdByLocale($locale, true);
    if (!$id) {
        $language = new Language();
        $language->name = 'Synthetic ' . $locale;
        $language->iso_code = substr($locale, 0, 2);
        $language->locale = $locale;
        $language->language_code = strtolower($locale);
        $language->date_format_lite = 'Y-m-d';
        $language->date_format_full = 'Y-m-d H:i:s';
        $language->active = true;
        $language->is_rtl = false;
        if (!$language->add()) throw new RuntimeException('Language fixture failed');
        $id = $language->id;
    }
    $context->employee->id_lang = (int) $id;
    if (!$context->employee->update()) throw new RuntimeException('Employee locale fixture failed');
    echo $locale . PHP_EOL;
} elseif ($action === 'messages') {
    $context->language = new Language((int) Language::getIdByLocale($locale, true));
    $module = Module::getInstanceByName('pixel_cloudflare_turnstile');
    $expected = json_decode(file_get_contents('/module/tests/settings-translations.json'), true, 512, JSON_THROW_ON_ERROR)[$locale];
    $method = new ReflectionMethod(Module::class, 'trans');
    $actual = [];
    foreach ($expected as $message => $target) {
        $actual[$message] = html_entity_decode($method->invoke($module, $message, ['%field%' => 'Field'], 'Modules.Pixelcloudflareturnstile.Admin', $locale), ENT_QUOTES, 'UTF-8');
    }
    // A real module call exercises the previously hard-coded authorization error.
    $context->employee->active = false;
    $actual['denied_html'] = html_entity_decode($module->getContent(), ENT_QUOTES, 'UTF-8');
    echo json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} elseif ($action === 'snapshot') {
    $rows = $db->executeS("SELECT * FROM `" . _DB_PREFIX_ . "configuration` WHERE name LIKE 'CLOUDFLARE_TURNSTILE_%' ORDER BY id_configuration", true, false);
    echo hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)) . PHP_EOL;
} elseif ($action === 'fail-write') {
    if (!$db->execute('CREATE TRIGGER `' . $trigger . '` BEFORE UPDATE ON `' . _DB_PREFIX_ . "configuration` FOR EACH ROW BEGIN IF NEW.name='CLOUDFLARE_TURNSTILE_THEME' AND NEW.value='dark' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic private translation failure'; END IF; END")) throw new RuntimeException('Failure trigger creation failed');
    echo "Synthetic write failure enabled.\n";
} elseif ($action === 'clear-failure') {
    if (!$db->execute('DROP TRIGGER IF EXISTS `' . $trigger . '`')) throw new RuntimeException('Failure trigger cleanup failed');
    echo "Synthetic failure removed.\n";
} else {
    throw new RuntimeException('Unknown fixture action');
}
