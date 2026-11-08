<?php
/** Behavioral mutations in a disposable copy; maintained source is never edited. @author 30knees */
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__); $temp=sys_get_temp_dir().'/turnstile-mutations-'.getmypid();
mkdir($temp,0700);
$paths=['pixel_cloudflare_turnstile.php','src','upgrade','tests/settings.php','tests/components.php','tests/runtime.php'];
foreach($paths as $relative) {
    $source=$root.'/'.$relative;
    $files=is_dir($source)?new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS)):[$source];
    foreach($files as $file) {
        $path=(string)$file; if(!is_file($path)) continue;
        $target=$temp.substr($path,strlen($root));
        if(!is_dir(dirname($target))) mkdir(dirname($target),0700,true);
        copy($path,$target);
    }
}
$cases=PHP_VERSION_ID<80500?[
    ['pixel_cloudflare_turnstile.php','PHP_VERSION_ID < 80500','false','runtime.php'],
]:[
    ['pixel_cloudflare_turnstile.php',"version_compare(_PS_VERSION_, '9.2.0', '<')",'false','runtime.php 9.1.0'],
    ['pixel_cloudflare_turnstile.php',"version_compare(_PS_VERSION_, '9.2.99', '>')",'false','runtime.php 9.3.0'],
    ['pixel_cloudflare_turnstile.php','if (!$this->checkRuntime()) throw new PrestaShopException','if (false) throw new PrestaShopException','runtime.php 9.1.0'],
    ['upgrade/upgrade-1.2.0.php','return $module->checkRuntime();','return true;','runtime.php 9.1.0'],
    ['pixel_cloudflare_turnstile.php','($_SERVER[\'REQUEST_METHOD\'] ?? \'\') !== \'POST\'','false','settings.php'],
    ['pixel_cloudflare_turnstile.php','$this->getPermission(\'configure\', $employee)','true','settings.php'],
    ['pixel_cloudflare_turnstile.php','hash_equals($this->settingsFormToken(), $token)','true','settings.php'],
    ['pixel_cloudflare_turnstile.php','&& $employee->hasAuthOnShop((int) $shop->id)','','settings.php'],
    ['pixel_cloudflare_turnstile.php','$code === self::CONFIG_CLOUDFLARE_TURNSTILE_SECRET_KEY ? \'\' : $value','$value','settings.php'],
    ['pixel_cloudflare_turnstile.php','$db->execute(\'ROLLBACK\');','','settings.php'],
    ['pixel_cloudflare_turnstile.php','Configuration::resetStaticCache();','','settings.php'],
    ['pixel_cloudflare_turnstile.php','($result[\'success\'] ?? null) !== true','empty($result[\'success\'])','components.php'],
    ['pixel_cloudflare_turnstile.php','strlen($response) > 2048','false','components.php'],
    ['pixel_cloudflare_turnstile.php','curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200','false','components.php'],
];
$checks=0;
try {
    foreach($cases as [$relative,$needle,$replacement,$test]) {
        $path=$temp.'/'.$relative; $original=file_get_contents($path);
        if(substr_count($original,$needle)!==1) throw new RuntimeException('Mutation anchor mismatch: '.$needle);
        $parts=explode(' ',$test);
        $command=escapeshellarg(PHP_BINARY).' -d disable_functions=curl_init,curl_setopt,curl_exec,curl_error,curl_getinfo '.escapeshellarg($temp.'/tests/'.array_shift($parts));
        foreach($parts as $part) $command.=' '.escapeshellarg($part);
        exec($command.' 2>&1',$output,$code);
        if($code!==0) throw new RuntimeException('Baseline failed: '.implode("\n",$output));
        file_put_contents($path,str_replace($needle,$replacement,$original));
        $output=[]; exec($command.' 2>&1',$output,$code);
        file_put_contents($path,$original);
        if($code===0) throw new RuntimeException('Mutation survived: '.$needle);
        ++$checks;
    }
} finally {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
    }
    rmdir($temp);
}
echo "$checks behavioral mutations rejected on PHP".PHP_VERSION.".\n";
