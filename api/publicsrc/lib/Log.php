<?php

namespace publicsrc\lib;

use publicsrc\conf\Conf;

/**
 * 自定义日志
 */
class Log {

    private $fileName;

    function __construct($fileName)
    {

        $month = date('Ym');
        $dir = __DIR__ . '/../../logs/' . $month;
        if (strpos($fileName, '\\')) {
            $fileNameArray = explode('\\', $fileName);
            $fileName = end($fileNameArray);
        }
        if (!file_exists($dir)) {
            shell_exec("mkdir -p $dir");
        }
        $this->fileName = $dir."/".$fileName;
    }

    public function info($msg, $file=null, $line=null)
    {
        $message = date('Y-m-d H:i:s');
        $filePath = $this->fileName."_".date('Ymd');
        if ($file != null) {
            $file = basename($file);
        }
        $message .= is_null($file) ? '' : " file $file";
        $message .= is_null($line) ? '' : " line $line";
        $message .= " $msg \n";
        file_put_contents($filePath, $message, FILE_APPEND);
        return true;
    }

    public function emptyLines($lineNum=2)
    {
        $filePath = $this->fileName."_".date('Ymd');
        $message = str_repeat("\n", $lineNum);
        file_put_contents($filePath, $message, FILE_APPEND);
    }

    public function log($msg, $level='INFO')
    {
        $message = "[$level] $msg";
        $this->info($message);
    }

    /**
     * 写日志
     * @param string $level 日志级别
     * @param string $category 分类
     * @param string $message 消息
     */
    // private static function log($level, $category, $message) {
    //     $now = date('Y-m-d H:i:s');
    //     $month = date('Y-m');
    //     $dir = __DIR__ . '/../logs/' . $month;
    //     if (!file_exists($dir)) {
    //         shell_exec("mkdir -p $dir");
    //         // mkdir($dir, 0755);
    //     }
    //     error_log("[$now] $message\n", 3, $dir . "/$category.$level.log");
    // }

    /**
     * 写日志（通知）
     * @param string $category 分类
     * @param string $message 消息
     */
    public static function notice($category, $message) {
        self::log('notice', $category, $message);
    }

    /**
     * 写日志（错误）
     * @param string $category 分类
     * @param string $message 消息
     */
    public static function error($category, $message) {
        self::log('error', $category, $message);
    }
}
