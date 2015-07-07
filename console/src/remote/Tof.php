<?php
/**
 * pkg打包相关
 **/

namespace remote;
use Flight;
use common\Curl;
use common\Config;
use common\DES;

class Tof extends Curl {
    public function request($path, array $query = null, array $data = null, $method = 'GET', $timeout = 120, $type = 'json') {
        $url = Config::get('auth.api_url') . $path;
        $host = Config::get('auth.api_host');

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $json = $type === 'json';

        $random = (string) mt_rand();
        $timestamp = (string) time();

        $salt = str_pad(Config::get('auth.appid'), 8, '-');
        $plaintext = 'random' . $random . 'timestamp' . $timestamp;

        $signature = strtoupper(bin2hex(DES::encrypt($salt, $plaintext)));

        $headerMap = array(
            'appkey'    => Config::get('auth.appkey'),
            'random'    => $random,
            'timestamp' => $timestamp,
            'signature' => $signature,
        );

        $header = array();
        foreach ($headerMap as $key => $value) {
            $header[] = $key . ': ' . $value;
        }

        if ($data !== null && $json) {
            $header[] = 'Content-Type: application/json';
        }

        if (! $timeout) {
            $timeout = null;
        }

        $options = compact('url', 'host', 'method', 'header', 'timeout');

        if ($data !== null) {
            if ($json) {
                $options['data'] = json_encode($data);
            } else {
                $options['data'] = $data;
            }
        }

        return $this->curl($options);
    }

    public function decryptTicket($encryptedTicket, $browseIP) {
        $appkey = Config::get('auth.appkey');
        return $this->request('Passport/DecryptTicketWithClientIP', compact('appkey', 'encryptedTicket', 'browseIP'));
    }
}
