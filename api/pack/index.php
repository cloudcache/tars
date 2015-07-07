<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

require '../flight/Flight.php';
// Flight::path(__DIR__ . '/../src');

Flight::route('/', function(){
    echo 'hello world!pack';
});

//上传
Flight::route('POST /upload/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->uploadFile();
});

//拉取
Flight::route('POST /pull/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->pullFile();
});

//编辑
Flight::route('GET /getFileContent/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->getFileContent();
});

//保存
Flight::route('POST /save/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->saveFile();
});

//操作文件
Flight::route('POST /operate_file/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->operateFile();
});

//获取路径svn状态
Flight::route('GET /get_svn_status/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->getSvnStatus();
});

//svn目录更新
Flight::route('PUT /svn_update/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->svnUpdate();
});

//保存包信息与配置
Flight::route('POST /save_package_config/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->savePackageConfig();
});

//导出安装包
Flight::route('GET /export_package_to_cache/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->exportPackageToCache();
});

//包拷贝--用于spp
Flight::route('POST /copy_package/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->copyPackage();
});

//创建包
Flight::route('POST /create/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->createPackage();
});

//提交创建包
Flight::route('POST /submit_create/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->submitCreatePackage();
});

//撤销包
Flight::route('DELETE /delete/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->deleteVersion();
});

//撤销变更
Flight::route('POST /revert/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->revertChange();
});

//提交更新版本
Flight::route('PUT /submit_update/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->submitUpdateVersion();
});

//提交版本
Flight::route('PUT /checkout/', function(){
    $packExample = new pack\src\PackFunction;
    $packResult = $packExample->checkOut();
});

Flight::route('GET /list_directory/', function(){
    $t = new pack\src\PackFunction;
    $t->listDirectory();
});

Flight::route('GET /download_file/', function(){
    $t = new pack\src\PackFunction;
    $t->downloadFile();
});


Flight::route('GET /check_package_exist/', function(){
    $t = new pack\src\PackFunction;
    $t->checkPackageExist();
});

Flight::route('POST /test/', function(){
    $t = new pack\src\PackFunction;
    $t->test();
});

Flight::start();
?>
