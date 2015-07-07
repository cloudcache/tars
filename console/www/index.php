<?php

// 测试环境调试
// define('DEBUG', $_SERVER['SERVER_NAME'] === 'steve.pkg.qq.com');
define('DEBUG', false);
define('DEV', false);

require '../src/common/App.php';

function url_need_not_login($url, $method) {
    if (DEBUG) {
        return true;
    }

    $index = strpos($url, '?');
    $path = strtolower($index === false ? $url : substr($url, 0, $index));

    // 登录/登出 不需要验证登录
    $pathList = array('/signin', '/api/session', '/signout', '/auth', '/modern-browser-required', '/license');
    if (in_array($path, $pathList)) {
        return true;
    }
}

common\App::start();
