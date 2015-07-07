<?php
/**
 * 应用入口
 * @author steveswwang
 */

namespace common;
use Flight;

define('ROOT_DIR', realpath(__DIR__ . '/../..'));

require_once ROOT_DIR . '/flight/Flight.php';

class App {
    public static function start() {
        Flight::set('flight.log_errors', true);
        // Flight::set('flight.handle_errors', false);

        Flight::path(ROOT_DIR . '/src');
        Flight::set('flight.views.path', ROOT_DIR . '/src/views');
        Flight::set('tars.controller.path', ROOT_DIR . '/src/controller');
        // Flight::set('tars.views.relative.path', '../../../src/views');

        // 登录验证
        Flight::before('start', function(){

            // 不需要登录的链接
            if (url_need_not_login(Flight::request()->url, Flight::request()->method)) {
                return;
            }

            session_start();
            if (! isset($_SESSION['username'])) {
                // 未登录
                if (Flight::request()->ajax) {
                    Flight::json(array('error' => 'no session'), 403);
                } else {
                    Flight::redirect('/signin');
                }
                return false;
            }

            Flight::set('token', $_SESSION['token']);
            Flight::set('userid', $_SESSION['id']);
            Flight::set('username', $_SESSION['username']);
            Flight::set('userrole', $_SESSION['role']);

            session_write_close();
        });

        // $controllers = array();

        if ($handle = opendir(Flight::get('tars.controller.path'))) {
            while (false !== ($entry = readdir($handle))) {
                if (substr($entry, -4) === '.php') {
                    $name = str_replace('.php', '', $entry);
                    $Ctrl = "\\controller\\$name";
                    /*$controllers[$name] = */new $Ctrl();
                }
            }
        }

        Flight::start();
    }
}
