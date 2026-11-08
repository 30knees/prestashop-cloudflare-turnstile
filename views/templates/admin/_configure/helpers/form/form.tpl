{extends file="helpers/form/form.tpl"}

{block name="label"}
  <label for="{$input.id|escape:'html':'UTF-8'}" class="control-label col-lg-3{if !empty($input.required)} required{/if}">
    {$input.label|escape:'html':'UTF-8'}
  </label>
{/block}
