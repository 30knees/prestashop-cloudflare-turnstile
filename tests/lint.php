<?php
$root=dirname(__DIR__); $count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file) {
    if($file->getExtension()!=='php' || str_contains($file->getPathname(),DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)) continue;
    $output=[];
    exec(escapeshellarg(PHP_BINARY).' -d disable_functions=curl_init,curl_setopt,curl_exec,curl_error,curl_getinfo -l '.escapeshellarg($file->getPathname()).' 2>&1',$output,$code);
    if($code) throw new RuntimeException(implode("\n",$output));
    ++$count;
}
echo "$count PHP files linted on ".PHP_VERSION.".\n";
