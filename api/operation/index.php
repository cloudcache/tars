<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

require '../flight/Flight.php';

Flight::path(__DIR__ . '/src');
Flight::path(__DIR__ . '/../src');


Flight::route('/', function(){
    echo 'hello world!operation';
});

Flight::route('POST /test/', function(){
    $t = new operation\src\PackageOperation;
    $t->test();
});


Flight::route('POST /install/', function(){
    $t = new operation\src\PackageOperation;
    $t->install();
});

Flight::route('POST /update/', function(){
    $t = new operation\src\PackageOperation;
    $t->update();
});

Flight::route('POST /rollback/', function(){
    $t = new operation\src\PackageOperation;
    $t->rollback();
});

Flight::route('POST /maintenance/', function(){
    $t = new operation\src\PackageOperation;
    $t->maintenance();
});

Flight::route('GET /get_task_result/', function(){
    $t = new operation\src\PackageOperation;
    $t->getTaskResult();
});

Flight::route('GET /get_task_result_all/', function(){
    $t = new operation\src\PackageOperation;
    $t->getTaskResultAll();
});


Flight::route('GET /get_task_by_operator/', function(){
    $t = new operation\src\PackageOperation;
    $t->getTaskByOperator();
});

Flight::route('GET /get_task_count_by_operator/', function(){
    $t = new operation\src\PackageOperation;
    $t->getTaskCountByOperator();
});

Flight::route('POST /mark_task_read/', function(){
    $t = new operation\src\PackageOperation;
    $t->markTaskRead();
});


Flight::route('GET /get_update_filelist/', function(){
    $t = new operation\src\PackageOperation;
    $t->getUpdateFileList();
});

Flight::route('POST /start_task/', function(){
    $t = new operation\src\PackageOperation;
    $t->startTaskApi();
});

Flight::start();
?>
