<?php
class Conf
{
    private static $confArray;
    function __construct()
    {
        self::$confArray = parse_ini_file($path);
    }

    /**
     * [get 获取配置文件中的变量]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function get($key)
    {
        $dir = __DIR__;
        $path = $dir . '/Conf.ini';
        self::$confArray = parse_ini_file($path);
        $resultValue = null;
        if (isset(self::$confArray[$key])) {
            //识别conf是否配置了该路径
            //处理布尔
            if (self::$confArray[$key] == 'true' ||
                self::$confArray[$key] == 'false') {
                $boolValue = self::$confArray[$key] == 'true' ? true:false;
                return $boolValue;
            } else {
                $resultValue = self::$confArray[$key];
            }
        }
        return $resultValue;
    }
}
