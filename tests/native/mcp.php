<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('TURNSTILE_TEST_SHOP') !== '1') {
    exit("Run only with TURNSTILE_TEST_SHOP=1 in an isolated shop.\n");
}
$prestashopRoot = getenv('PRESTASHOP_ROOT') ?: '/var/www/html';
require $prestashopRoot . '/config/config.inc.php';
require_once $prestashopRoot . '/modules/pixel_cloudflare_turnstile/pixel_cloudflare_turnstile.php';

$http = true;
$checks = 0;
$sessions = [];
$requestId = 0;
$tokens = $http ? json_decode(file_get_contents('/tmp/turnstile-mcp-token.json'), true, 512, JSON_THROW_ON_ERROR) : [];
function checkLegalMcp(bool $ok, string $message): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function legalMcpRequest(string $method, array $params = [], string $role = 'viewer'): array
{
    global $tokens, $sessions, $requestId;
    $id = ++$requestId;
    $curl = curl_init('http://127.0.0.1/index.php?fc=module&module=ps_mcp_server&controller=McpServer');
    $headers = ['Host: ' . getenv('TURNSTILE_MCP_HOST'), 'Content-Type: application/json', 'Accept: application/json, text/event-stream', 'Authorization: Bearer ' . ($tokens[$role] ?? 'invalid')];
    if ($role === 'anonymous') array_pop($headers);
    if (isset($sessions[$role])) {
        $headers[] = 'Mcp-Session-Id: ' . $sessions[$role];
    }
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params], JSON_THROW_ON_ERROR), CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30]);
    $raw = curl_exec($curl);
    if ($raw === false) {
        throw new RuntimeException('MCP HTTP transport failed.');
    }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    if (preg_match('/Mcp-Session-Id:\s*([^\r\n]+)/i', substr($raw, 0, $size), $match)) {
        $sessions[$role] = trim($match[1]);
    }
    $body = substr($raw, $size);
    $data = json_decode($body, true);
    if (!is_array($data)) {
        preg_match_all('/^data: (.+)$/m', $body, $matches);
        foreach ($matches[1] as $line) {
            $message = json_decode(trim($line), true);
            if (($message['id'] ?? null) === $id) {
                $data = $message;
            }
        }
    }

    return ['status' => $status, 'data' => $data];
}
function legalMcpFailed(array $response): bool
{
    $data = $response['data'] ?? [];

    return $response['status'] >= 400 || isset($data['error']) || !empty($data['isError']) || !empty($data['result']['isError']);
}
function legalMcpValue(array $response): array
{
    checkLegalMcp(!legalMcpFailed($response) && is_array($response['data']), 'Expected MCP success: ' . json_encode($response));
    $result = $response['data']['result'] ?? [];
    if (isset($result['structuredContent'])) {
        return $result['structuredContent'];
    }
    $content = $result['content'] ?? $result['contents'] ?? [];
    if (isset($content[0]['text'])) {
        return json_decode($content[0]['text'], true, 512, JSON_THROW_ON_ERROR);
    }

    return $result;
}
function legalMcpState(): string {
    $db=Db::getInstance();
    $state=$db->executeS('SELECT * FROM `'._DB_PREFIX_.'hook_module` ORDER BY id_module,id_hook,id_shop',true,false);
    $state[]=$db->executeS('SELECT * FROM `'._DB_PREFIX_.'configuration` ORDER BY id_configuration',true,false);
    $state[]=hash_file('sha256',_PS_ROOT_DIR_.'/classes/Tools.php');
    return hash('sha256',serialize($state));
}
$module=Module::getInstanceByName('pixel_cloudflare_turnstile');
checkLegalMcp($module instanceof Pixel_cloudflare_turnstile && $module->isMcpCompliant(),'Module opts into MCP');
checkLegalMcp((int)Configuration::get('PS_MAIL_METHOD')===3,'Mail remains disabled');
legalMcpValue(legalMcpRequest('initialize',['protocolVersion'=>'2025-06-18','capabilities'=>(object)[],'clientInfo'=>['name'=>'Turnstile isolated verifier','version'=>$module->version]]));
checkLegalMcp(legalMcpFailed(legalMcpRequest('tools/list',[],'invalid')),'Authentication enforced');
checkLegalMcp(legalMcpFailed(legalMcpRequest('resources/list',[],'anonymous')),'Anonymous discovery refused');
$tools=legalMcpValue(legalMcpRequest('tools/list'))['tools'];
$tools=array_values(array_filter($tools,static fn(array $tool):bool=>str_starts_with($tool['name'],'pixel_cloudflare_turnstile-')));
checkLegalMcp($tools===[],'No Turnstile writer or other tool exposed');
$resources=legalMcpValue(legalMcpRequest('resources/list'))['resources'];
$resources=array_values(array_filter($resources,static fn(array $resource):bool=>str_contains($resource['uri'],'turnstile://')));
checkLegalMcp(count($resources)===1,'Exactly one status resource discovered: '.json_encode($resources));
$uri=$resources[0]['uri'];
// Initialize/list lets the official server settle discovery/notification flags.
$before=legalMcpState();
$status=legalMcpValue(legalMcpRequest('resources/read',['uri'=>$uri]));
checkLegalMcp($status===$module->getStatus(),'Viewer resource matches direct status');
checkLegalMcp(legalMcpFailed(legalMcpRequest('resources/read',['uri'=>'turnstile://unknown'])),'Unknown resource refused');
checkLegalMcp(legalMcpState()===$before,'Reads preserve configuration and hooks');
$db=Db::getInstance(); $id=(int)$module->id;
$association=$db->getRow('SELECT * FROM `'._DB_PREFIX_.'module_shop` WHERE id_module='.$id.' AND id_shop=1',false);
$db->delete('module_shop','id_module='.$id.' AND id_shop=1');
try {
    checkLegalMcp(legalMcpFailed(legalMcpRequest('resources/read',['uri'=>$uri])),'Cached resource rejects disabled shop');
} finally { $db->insert('module_shop',$association); }
$db->update('module',['active'=>0],'id_module='.$id);
try { checkLegalMcp(legalMcpFailed(legalMcpRequest('resources/read',['uri'=>$uri])),'Resource rejects global disable'); }
finally { $db->update('module',['active'=>1],'id_module='.$id); }
checkLegalMcp(legalMcpState()===$before,'Final state preserved');
echo 'Authenticated MCP passed: '.$checks.' checks; PS '._PS_VERSION_.', PHP '.PHP_VERSION.PHP_EOL;
