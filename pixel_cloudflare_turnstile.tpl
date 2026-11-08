{if $sitekey}
<div class="pxd-cloudflare-turnstile">
    <div class="cf-turnstile" data-sitekey="{$sitekey|escape:'htmlall':'UTF-8'}" data-theme="{$theme|escape:'htmlall':'UTF-8'}" data-appearance="{$appearance|escape:'htmlall':'UTF-8'}" data-action="{$action|escape:'htmlall':'UTF-8'}"></div>
</div>
{/if}