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
    $t = new query\src\PackageQuery;
    $t->test();
});

Flight::route('POST /report_instance/', function(){
    $t = new query\src\PackageQuery;
    $t->dealInstanceReport();
});

Flight::route('POST /import_password/', function(){
    $t = new query\src\PackageQuery;
    $t->importDevicePassword();
});

Flight::route('GET /device_password/', function(){
    $t = new query\src\PackageQuery;
    $t->getDevicePassword();
});

Flight::route('GET /device/', function(){
    $t = new query\src\PackageQuery;
    $t->getDevice();
});

Flight::route('POST /update_device/', function(){
    $t = new query\src\PackageQuery;
    $t->updateDevice();
});

Flight::route('POST /delete_device/', function(){
    $t = new query\src\PackageQuery;
    $t->deleteDevice();
});

Flight::route('GET /device_idc/', function(){
    $t = new query\src\PackageQuery;
    $t->getDeviceIdc();
});

Flight::route('GET /device_business/', function(){
    $t = new query\src\PackageQuery;
    $t->getDeviceBusiness();
});

Flight::route('GET /search_package/', function(){
    $t = new query\src\PackageQuery;
    $t->searchPackage();
});

Flight::route('GET /get_package_versionlist/', function(){
    $t = new query\src\PackageQuery;
    $t->getPackageVersionList();
});

Flight::route('GET /get_package_information/', function(){
    $t = new query\src\PackageQuery;
    $t->getPackageInformation();
});

Flight::route('GET /get_package_information_bypath/', function(){
    $t = new query\src\PackageQuery;
    $t->getPackageInformationByPath();
});

Flight::route('GET /get_instance/', function(){
    $t = new query\src\PackageQuery;
    $t->getInstanceByIpAndPath();
});

Flight::route('GET /get_path/', function(){
    $t = new query\src\PackageQuery;
    $t->getPathByProductAndName();
});

Flight::route('GET /get_instance_countlist/', function(){
    $t = new query\src\PackageQuery;
    $t->getInstanceCountList();
});

Flight::route('POST /get_instance_list/', function(){
    $t = new query\src\PackageQuery;
    $t->getInstanceList();
});

Flight::route('GET /get_instance_list_byip/', function(){
    $t = new query\src\PackageQuery;
    $t->getInstanceListbyIp();
});

Flight::route('PUT /set_remark/', function(){
    $t = new query\src\PackageQuery;
    $t->setPackageRemark();
});

Flight::route('POST /delete_install_record/', function(){
    $t = new query\src\PackageQuery;
    $t->deleteInstallRecord();
});

Flight::route('GET /get_product_map/', function(){
    $t = new query\src\PackageQuery;
    $t->getProductMap();
});

Flight::route('POST /add_product_map/', function(){
    $t = new query\src\PackageQuery;
    $t->addProductMap();
});

Flight::route('DELETE /delete_product_map/', function(){
    $t = new query\src\PackageQuery;
    $t->deleteProductMap();
});

Flight::start();
?>
