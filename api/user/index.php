<?php
ini_set('display_errors','On');
error_reporting(E_ALL);

require '../flight/Flight.php';

Flight::route('/', function(){
    echo 'hello world!filemanage';
});

Flight::route('POST /role/', function(){
    $cgi = new user\src\PkgUser;
    $cgi->addPkgAdmin();   
});

Flight::route('DELETE /role/', function(){
    $cgi = new user\src\PkgUser;
    $cgi->delPkgAdmin();   
});

Flight::route('GET /role/user/', function(){
    $cgi = new user\src\PkgUser;
    $cgi->getUserRole();   
});

Flight::route('GET /role/', function(){
    $cgi = new user\src\PkgUser;
    $cgi->getPkgUser();   
});

Flight::route('GET /checkPrivilege/', function(){
    $cgi = new user\src\PkgUser;
    $cgi->authenticate();   
});

Flight::route('PUT|POST /PkgPublic/', function(){
    $cgi = new user\src\PkgUser;
    $cgi->changePkgAtt();   
});
Flight::route('GET /PkgPublic/', function(){
    $cgi = new user\src\PkgUser;
    $cgi->getPkgAtt();   
});


Flight::route('POST /user/', function(){
    $user = new user\src\User;
    $user->register();   
});
Flight::route('GET /user/', function(){
    $user = new user\src\User;
    $user->getUserInfo();   
});
Flight::route('PUT /user/', function(){
    $user = new user\src\User;
    $user->updateUserInfo();   
});
Flight::route('POST /users/', function(){
    $user = new user\src\User;
    $user->batRegister();   
});
Flight::route('GET /alluser/', function(){
    $user = new user\src\User;
    $user->getAllUser();   
});
Flight::route('DELETE /users/', function(){
    $user = new user\src\User;
    $user->deleteUsers();   
});
Flight::route('POST /session/', function(){
    $user = new user\src\User;
    $user->login();   
});


Flight::start();
?>
