<?php
/**
 * 用户相关
 **/

namespace remote;
use Flight;
use common\Curl;
use common\Config;

class PkgUser extends Curl {
    CONST TIMEOUT = 120;

    private $token;

    private function setToken() {
        $this->token = Flight::get('token');
    }

    private function unsetToken() {
        $this->token = null;
    }

    public function request($path, array $query = null, array $data = null, $method = 'GET') {
        $url = Config::get('pkg.api_url') . 'user/' . $path;
        $host = Config::get('pkg.api_host');
        $timeout = self::TIMEOUT;
        $header = array();

        // 请求头中加入 `token`
        if ($this->token) {
            $header[] = 'Tars-Token: ' . $this->token;
        }/* else {
            $header[] = 'Tars-Token: no';
        }*/

        // GET 参数
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $options = compact('url', 'method', 'host', 'timeout');

        // 请求主体
        if ($data) {
            $header[] = 'Content-Type: application/json';
            $options['data'] = json_encode($data);
        }

        $options['header'] = $header;

        return $this->curl($options);
    }

    public function get($path, array $query = null) {
        return $this->request($path, $query);
    }

    public function post($path, array $data = null, array $query = null) {
        return $this->request($path, $query, $data, 'POST');
    }

    public function put($path, array $data = null, array $query = null) {
        return $this->request($path, $query, $data, 'PUT');
    }

    public function delete($path, array $query = null) {
        return $this->request($path, $query, null, 'DELETE');
    }

    public function storeUsers(array $users) {
        $this->setToken();
        return $this->post('users', $users);
    }

    public function signin($username, $password) {
        $this->unsetToken();
        return $this->post('session', compact('username', 'password'));
    }

    public function getUser() {
        $this->setToken();
        return $this->get('user');
    }

    public function listUsers() {
        $this->setToken();
        return $this->get('alluser');
    }

    public function updateUser($username, array $options) {
        $this->setToken();
        $data = compact('username');
        $data += $options;
        return $this->put('user', $data);
    }
}
