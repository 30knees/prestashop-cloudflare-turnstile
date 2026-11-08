<?php
/** Real core and SDK checks in the disposable shop. @author 30knees */
if(PHP_SAPI!=='cli' || getenv('TURNSTILE_TEST_SHOP')!=='1') throw new RuntimeException('Disposable CLI only');
require '/var/www/html/config/config.inc.php';
if(ShopUrl::getMainShopDomain()!=='localhost:19105') throw new RuntimeException('Wrong fixture');
require _PS_MODULE_DIR_.'ps_mcp_server/vendor/autoload.php';
require_once _PS_MODULE_DIR_.'pixel_cloudflare_turnstile/src/Mcp/StatusResource.php';
Shop::setContext(Shop::CONTEXT_SHOP,1); $context=Context::getContext(); $context->shop=new Shop(1);
$module=Module::getInstanceByName('pixel_cloudflare_turnstile'); $adapter=new ThirtyKnees\Turnstile\Mcp\StatusResource();
$checks=0; $db=Db::getInstance(); $id=(int)$module->id; $before=hash_file('sha256',_PS_ROOT_DIR_.'/classes/Tools.php');
function scopeCheck($ok,$message) { global $checks; ++$checks; if(!$ok) throw new RuntimeException($message); }
function refused($adapter,$message) {
    $denied=false; try { $adapter->read(); } catch(PsMcpServerDeps\Mcp\Exception\ResourceReadException $error) { $denied=true; }
    scopeCheck($denied,$message);
}
$attribute=(new ReflectionMethod($adapter,'read'))->getAttributes(PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpResource::class)[0]->newInstance();
scopeCheck($attribute->uri==='turnstile://status','Official SDK resource metadata');
scopeCheck($adapter->read()===$module->getStatus(),'Direct native adapter');
$denied=false;try { $adapter->read('unexpected'); } catch(PsMcpServerDeps\Mcp\Exception\ResourceReadException $error) { $denied=true; }
scopeCheck($denied,'Extra arguments refused');
Shop::setContext(Shop::CONTEXT_ALL); refused($adapter,'Aggregate context refused'); Shop::setContext(Shop::CONTEXT_SHOP,1);
$context->shop->active=false; refused($adapter,'Inactive shop refused'); $context->shop->active=true;
$context->shop->deleted=true; refused($adapter,'Deleted shop refused'); $context->shop->deleted=false;
$context->shop->id=2; refused($adapter,'Mismatched shop refused'); $context->shop=new Shop(1);
$association=$db->getRow('SELECT * FROM `'._DB_PREFIX_.'module_shop` WHERE id_module='.$id.' AND id_shop=1',false);
$db->delete('module_shop','id_module='.$id.' AND id_shop=1');
try { refused($adapter,'Warmed module refuses shop disable'); } finally { $db->insert('module_shop',$association); }
$db->update('module',['active'=>0],'id_module='.$id);
try { refused($adapter,'Warmed module refuses global disable'); } finally { $db->update('module',['active'=>1],'id_module='.$id); }
scopeCheck($adapter->read()['installed_version']==='1.2.0','Restored activation readable');
scopeCheck(hash_file('sha256',_PS_ROOT_DIR_.'/classes/Tools.php')===$before,'Adapter never rewrites core');
echo "$checks native scope/SDK assertions passed.\n";
