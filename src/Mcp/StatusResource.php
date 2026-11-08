<?php
/** @author 30knees */
namespace ThirtyKnees\Turnstile\Mcp;

use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpResource;
use PrestaShop\Module\PsMcpServer\Server\Exceptions\PsMcpResourceReadException;

if (!defined('_PS_VERSION_')) exit;

final class StatusResource
{
    #[PsMcpResource(uri: 'turnstile://status', name: 'turnstile_status', description: 'Read current-shop protected forms, test mode, credential presence and hook registration. No keys, tokens, provider calls or writes.', mimeType: 'application/json')]
    public function read(): array
    {
        try {
            if (func_num_args() !== 0) throw new \InvalidArgumentException('This resource takes no arguments.');
            $module = \Module::getInstanceByName('pixel_cloudflare_turnstile');
            if (!$module instanceof \Pixel_cloudflare_turnstile) throw new \DomainException('Turnstile is unavailable.');
            return $module->getStatus();
        } catch (\Throwable $error) {
            throw new PsMcpResourceReadException($error instanceof \DomainException || $error instanceof \InvalidArgumentException
                ? $error->getMessage() : 'Unable to read Turnstile status.', 1);
        }
    }
}
