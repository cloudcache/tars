<?php
namespace publicsrc\conf;

class Conf
{
    private static $confArray;
    function __construct()
    {
        $path = __DIR__ . '/Conf.ini';
        self::$confArray = parse_ini_file($path);
        // self::$dir = __DIR__;
        // var_dump(self::$confArray);exit;
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
        //单独路径 依赖本项目位置
        $tool_shell = realpath($dir . "/../shell/");
        $extraPathArray = array(
            'tool_shell'=> $tool_shell,
            'tool_svn'=>$tool_shell . '/svn/',
            'tool_operate'=>$tool_shell . '/operate/',
            'tool_file'=>$tool_shell . '/file/'
            );
        if (isset($extraPathArray[$key])) {
            $resultValue = $extraPathArray[$key];
        } elseif (isset(self::$confArray[$key])) {
            //识别conf是否配置了该路径
            //处理布尔
            if (self::$confArray[$key] == 'true' ||
                self::$confArray[$key] == 'false') {
                $boolValue = self::$confArray[$key] == 'true' ? true:false;
                return $boolValue;
            } else {
                $resultValue = self::$confArray[$key];
            }
        } else {
            //子路径
            $subPathArray = array(
                'package_home'=>'/pkg_home/current_package',
                'package_path_export'=>'/pkg_home/pkg/',
                'package_path_update'=>'/pkg_home/update_pkg/',
                'package_framework'=>'/framework/',
                'package_backup'=>'/tmp/backup',
                'package_tmp'=>'/tmp/package',
                'svn_tmp'=>'/tmp/svn',
                );
            if (isset($subPathArray[$key])) {
                $resultValue = rtrim(self::$confArray['package_path'], '/') .
                '/' . ltrim($subPathArray[$key], '/');
            }
            if (is_string($resultValue) && $resultValue[0] == '/') {
                exec('mkdir -p ' . $resultValue);
            }
        }
        return $resultValue;
    }
}

?>
