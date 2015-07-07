<?php
ini_set('display_errors','On');
error_reporting(E_ALL);
require_once __DIR__ .'/PkgWorker.php';
$worker = new PkgWorker();
$taskId = $_SERVER['argv'][1];
var_dump($taskId);
$msg = array('task_id'=>$taskId);
$a = $worker->process($msg);

