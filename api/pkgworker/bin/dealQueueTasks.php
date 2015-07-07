<?php
ini_set('display_errors','On');
error_reporting(E_ALL);
require_once __DIR__ .'/PkgWorker.php';
$worker = new PkgWorker();
$worker->start();
