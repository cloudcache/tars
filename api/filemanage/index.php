<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

require '../flight/Flight.php';

Flight::path(__DIR__ . '/src');
Flight::path(__DIR__ . '/../src');


Flight::route('/', function(){
    echo 'hello world!filemanage';
});

Flight::route('GET /get_update_file_list/', function(){
    $t = new filemanage\src\FileManage;
    $t->getUpdateFileList();
});

Flight::route('GET /delete_cache/', function(){
    $t = new filemanage\src\FileManage;
    $t->deleteCache();
});

Flight::route('GET /export/', function(){
    $t = new filemanage\src\FileManage;
    $t->exportPackage();
});

//exportupdate
Flight::route('GET /export_update/', function(){
    $t = new filemanage\src\FileManage;
    $t->exportUpdatePackage();
});

Flight::route('POST /upload/', function(){
    $t = new filemanage\src\FileManage;
    $t->uploadPackage();
});

Flight::route('GET /delete_ignore/', function(){
    $t = new filemanage\src\FileManage;
    $t->deleteIgnoreFile();
});

Flight::route('GET /ignore_file/', function(){
    $t = new filemanage\src\FileManage;
    $t->ignoreFile();
});


Flight::route('POST /test/', function(){
    $t = new filemanage\src\FileManage;
    $t->test();
});



Flight::start();
?>
