<?php
/** Offline CI guard for Symcon Module Control root-folder convention. */
$base=dirname(__DIR__);$allowed=['libs','docs','imgs','tests','actions'];$errors=[];
foreach(new DirectoryIterator($base) as $entry){
    if($entry->isDot()||!$entry->isDir())continue;
    $name=$entry->getFilename();
    if(str_starts_with($name,'.')||in_array($name,$allowed,true))continue;
    $p=$entry->getPathname();
    if(!is_file($p.'/module.json')||!is_file($p.'/module.php')){
        $errors[]=$name.': module.json oder module.php fehlt';continue;
    }
    $config=json_decode((string)file_get_contents($p.'/module.json'),true);
    if(!is_array($config)||!isset($config['id'],$config['name'],$config['prefix']))
        $errors[]=$name.': module.json ungueltig';
}
if($errors){fwrite(STDERR,"FAIL Symcon-Struktur: ".implode('; ',$errors)."\n");exit(1);}
echo "PASS Symcon library directory structure\n";
