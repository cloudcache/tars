<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

require_once __DIR__ .'/PkgWorker.php';
$pkgworker = new PkgWorker();
$PkgWorker->process(array('task_id'=>''));
// $a = $pkgworker->checkCache('a', 'b', 'v');
// var_dump($a);
// $pkgworker->install("5456502a803a0");
// $a = $pkgworker->exportUpdatePkg("IM", "group", "1.0.1", "1.0.2");
// var_dump($a);
// $a = $pkgworker->deleteIgnoreFile("IM", "group", "1.0.1", "1.0.2", "xx");
// var_dump($a);
// $a = $pkgworker->ignoreFile("IM", "group", "1.0.1", "1.0.2", array(),"xx");
// var_dump($a);

// require_once __DIR__ .'/../lib/log4php/Log.php';
// require_once __DIR__ .'/../lib/MagicTool.php';
// require_once __DIR__ .'/../conf/Conf.php';
// // $log = new Log("test");
// // $log = $log->getLogger();
// // $log->info("end process message :");

// $a = new MagicTool();
// $url = "http://peking.pkg.isd.com/filemanage/export";
// $data = array(
//     'name'=>'group',
//     'product'=>'IM',
//     'version'=>'1.0.1');
// $opt = array(
//     'ip'=>Conf::get('fileManageHost'),
//     'method'=>'GET',
//     'data'=>$data
//     );

// $d = $a->httpRequest($url, $opt);
// var_dump($d);

