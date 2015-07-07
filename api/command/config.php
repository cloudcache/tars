<?php

class AREA_CONFIG
{
    public static $server_type = 'formal';

    public static $dbHost = '192.168.1.1';
    public static $dbPort = 3306;
    public static $dbName = 'TARS';
    public static $dbUser= 'tars';
    public static $dbPassword= 'tars';
    public static $passwordUrl= 'http://tars.qq.com/query/device_password';
    public static $passwordHost = '192.168.1.1';
    public static $authentication= 'password';  
    public static $phpPath="/usr/local/php/bin/php";
    public static $sshPort=22;
    /*
        使用密码 登陆时，设置配置项$authentication 值为 "password"
        当使用公钥登录时，设置配置项$authentication 值为"publickey"
        同时指定私钥文件目录，默认上配置为  command下的 key/id_rsa(绝对路径)
    */
    //public static $authentication= 'publickey';  //publickey  ,password
    //public static $privatekey=  '/data/webroot/tars.qq.com/pkg_opensrc/command/key/id_rsa';    //path to private key

    public static function getLocalIP()
    {
        $cmd = "/sbin/ip route|egrep 'src 172\.|src 10\.'|awk '{print $9}'|head -n 1";
        $ret = shell_exec($cmd);
        $ip = trim($ret);
        return $ip;
    }
}
