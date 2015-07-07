<?php
/**
 * 权限认证
 **/

namespace remote;
use common\Curl;
use common\Config;

class PkgAuth extends Curl {
    CONST TIMEOUT = 120;

    public function request($path, array $query = null, array $data = null, $method = 'GET') {
        $url = Config::get('pkg.api_url') . 'user/' . $path;
        $host = Config::get('pkg.api_host');
        $timeout = self::TIMEOUT;
        $header = array(
            'Content-Type: application/json',
        );

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $options = compact('url', 'method', 'host', 'header', 'timeout');

        if ($data) {
            $options['data'] = json_encode($data);
        }

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

    public function getVisibility($product, $name) {
        $query = array(
            'product' => $product,
            'name' => $name,
        );
        return $this->get('PkgPublic', $query);
    }

    public function setVisibility($product, $name, $private) {
        $data = array(
            'product' => $product,
            'name' => $name,
            'public' => intval(!$private),
        );
        return $this->post('PkgPublic', $data);
    }

    public function getRoles($product, $name) {
        $query = array(
            'product' => $product,
            'name' => $name,
        );
        return $this->get('role', $query);
    }

    public function removeRoleUsers($product, $name, $role, array $users) {
        $query = array(
            'product' => $product,
            'name' => $name,
            'role' => $role,
            'username' => $users,
        );
        return $this->delete('role', $query);
    }

    public function storeRoleUsers($product, $name, $role, array $users) {
        $data = array(
            'product' => $product,
            'name' => $name,
            'role' => $role,
            'username' => $users,
        );
        return $this->post('role', $data);
    }

    public function auth($product, $name, $username, $act, $ip = '') {
        if ($act === 'upgrade') {
            $act = 'update';
        }
        $query = array(
            'product' => $product,
            'name' => $name,
            'username' => $username,
            'act' => $act,
            'iplist' => $ip,
        );
        // return $query;
        return $this->get('checkPrivilege', $query);
    }
}
