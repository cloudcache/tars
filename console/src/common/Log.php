<?php
/**
 * 自定义日志
 * @author steveswwang
 */

namespace common;

class Log {

    /**
     * 写日志
     * @param string $level 日志级别
     * @param string $category 分类
     * @param string $message 消息
     */
    private static function log($level, $category, $message) {
        $now = date('Y-m-d H:i:s');
        $dir = __DIR__ . '/../../logs/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755);
        }
        error_log("[$now] $message\n", 3, $dir . "/$category.$level.log");
    }

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
