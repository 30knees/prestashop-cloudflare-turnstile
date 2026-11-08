<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('TURNSTILE_TEST_SHOP') !== '1') {
    throw new RuntimeException('Disposable MCP fixture only.');
}
require '/var/www/html/config/config.inc.php';
$kernel = new AdminKernel('prod', false);
$kernel->boot();
Configuration::updateValue('PS_MAIL_METHOD', 3);
Configuration::updateValue('PS_MCP_SERVER_AUTH_DISABLED', false);
Configuration::updateValue('PS_MCP_SERVER_STARTED', true);
$server = Module::getInstanceByName('ps_mcp_server');
if (!$server || !Module::isEnabled('ps_mcp_server')) {
    throw new RuntimeException('Official MCP server is not installed');
}
$users = $server->getService(PrestaShop\Module\PsMcpServer\Services\McpAllowedUsersService::class);
$email = 'viewer@turnstile-synthetic.test';
$access = $users->getUserByEmail($email) ? $users->regenerateTokenForUser($email) : $users->addAllowedUser($email, 'viewer');
$service = $server->getService(PrestaShop\Module\PsMcpServer\Services\McpService::class);
$service->fetchAllModulesCompliantWithMcp();
$service->discover();
file_put_contents('/tmp/turnstile-mcp-token.json', json_encode(['viewer' => $access['token']], JSON_THROW_ON_ERROR));
chmod('/tmp/turnstile-mcp-token.json', 0600);
echo 'Authenticated Viewer fixture initialized for MCP Server ' . $server->version . PHP_EOL;
