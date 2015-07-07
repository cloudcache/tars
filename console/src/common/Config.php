<?php
/**
 * 配置解析
 * @author steveswwang
 */

namespace common;
use Exception;

class Config {
    private static $conf;

    private static function parse() {
        if (self::$conf === null) {
            self::$conf = parse_ini_file(ROOT_DIR . '/tars.ini');
            if (self::$conf === false) {
                throw new Exception('parse tars.ini failed');
            }
        }
    }

    public static function get($key) {
        self::parse();
        return isset(self::$conf[$key]) ? self::$conf[$key] : null;
    }
}
