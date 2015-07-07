<?php
/**
 * 入口页控制器
 * @author steveswwang
 */

namespace controller;
use Flight;
use common\BaseController;
use common\Config;
use remote\Pkg;
use remote\PkgAuth;
use remote\PkgUser;
use remote\Tof;

class Index extends BaseController {

    // 路由表
    protected $routes = array(
        'GET /' => 'index',
        'GET /signin' => 'signin',
        'GET /signout' => 'signout',
        'GET /auth' => 'auth',
        'GET /modern-browser-required' => 'browser',
        'GET /LICENSE' => 'license',
        'GET /task/@taskId:\w+/log/@ip:[\d\.]+' => 'taskLog',
        'GET /*' => 'all',
    );

    // 所有单页应用页面入口
    private function layout() {
        FLight::view()->set('title', Config::get('page_title'));
        FLight::set('auth_by_third_part', Config::get('auth.use_third_part'));
        Flight::render(DEV ? 'layout' : 'layout-dist');
    }

    // 首页
    public function index() {
        $this->layout();
    }

    // 登录页
    public function signin() {
        if (Config::get('auth.use_third_part')) {
            // 使用第三方登录
            $query = array(
                'url' => Config::get('auth.redirect_url'),
                'title' => Config::get('page_title'),
                'appkey' => Config::get('auth.appkey'),
            );
            Flight::redirect('http://passport.third.com/signin?' . http_build_query($query));
        } else {
            Flight::render('signin');
        }
    }

    // 退出登录
    public function signout() {
        session_start();

        unset($_SESSION['token']);
        unset($_SESSION['userid']);
        unset($_SESSION['username']);
        unset($_SESSION['role']);

        session_write_close();

        if (Config::get('auth.use_third_part')) {
            // 使用第三方登录
            $query = array(
                'url' => Config::get('auth.redirect_url'),
                'title' => Config::get('page_title'),
                'appkey' => Config::get('auth.appkey'),
            );
            Flight::redirect('http://passport.third.com/signout?' . http_build_query($query));
        } else {
            Flight::redirect('/signin');
        }
    }

    // 第三方登录后回调
    public function auth() {
        $req = Flight::request();
        $ticket = $req->query->ticket;
        if (!$ticket) {
            Flight::halt(400);
        }

        $tof = new Tof();
        $ret = $tof->decryptTicket($ticket, $req->ip);

        if ($ret && $ret['Ret'] === 0) {
            $data = $ret['Data'];

            session_start();

            $_SESSION['token'] = $data['Token'];
            $_SESSION['id'] = $data['StaffId'];
            $_SESSION['username'] = $data['LoginName'];
            $isAdmin = in_array($_SESSION['username'], explode(',', Config::get('auth.admin_list')));
            $_SESSION['role'] = $isAdmin ? 'admin' : 'user';

            session_write_close();

            Flight::redirect('/');
        } else {
            Flight::halt(500, $ret ? $ret['ErrMsg'] : '');
        }
    }

    // 浏览器提示
    public function browser() {
        Flight::render('browser');
    }

    // License
    public function license() {
        $text = file_get_contents(ROOT_DIR . '/LICENSE');
        header('Content-Type: text/plain');
        echo $text;
    }

    // 其它请求
    public function all($route) {
        if (strpos($route->splat, 'api/') === 0) {
            // 对于 `/api/*` 交给 ApiCtrl 处理
            return true;
        }
        $this->layout();
    }

    // 发布任务日志
    public function taskLog($taskId, $ip) {
        $pkg = new Pkg();
        $result = $pkg->getTaskResult($taskId);
        $log = null;
        foreach ($result as $row) {
            if ($row['ip'] === $ip) {
                $log = $row['taskInfo'];
                break;
            }
        }
        if (!$log) {
            Flight::notFound();
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo $log;
    }
}
