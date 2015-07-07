<?php
/**
 * 自定义 CURL 请求
 * @author steveswwang
 */

namespace common;
use common\Log;
use common\CurlError;

class Curl {

    /**
     * @var array 上一条请求出错信息
     */
    private $_curlLastError;

    /**
     * @var bool 是否启用了并发请求
     */
    private $_curlMultiEnabled = false;

    /**
     * @var array 并发请求列表
     */
    private $_curlMultiList;

    /**
     * @var array 上一条并发请求出错信息
     */
    private $_curlMultiLastError;

    /**
     * @var array curl选项map
     */
    private $_curlOptionMap = array(
        'url'     => CURLOPT_URL,
        'method'  => CURLOPT_CUSTOMREQUEST,
        'header'  => CURLOPT_HTTPHEADER,
        'data'    => CURLOPT_POSTFIELDS,
        'post'    => CURLOPT_POSTFIELDS, // Deprecated
        'timeout' => CURLOPT_TIMEOUT,
    );

    /**
     * 获取上一次请求错误信息
     * @return CurlError
     */
    public function curlLastError() {
        return $this->_curlLastError;
    }

    /**
     * 获取上一次并发请求错误信息列表
     * @return array [CurlError]
     */
    public function curlMultiLastError() {
        return $this->_curlMultiLastError;
    }

    /**
     * 进行curl请求
     * @param array $options curl参数
     *     支持的键: url, method, data, timeout, host
     * @param bool $jsonDecode 是否需要json解析
     * @return mixed 正常：json解析后的数据；异常：false
     */
    public function curl(array $options, $jsonDecode = true, $beforeCurlClose = null) {
        // 转换可读参数名称
        foreach ($options as $key => $value) {
            if (isset($this->_curlOptionMap[$key])) {
                $options[$this->_curlOptionMap[$key]] = $value;
                unset($options[$key]);
            }
        }

        // 设置Host头
        if (isset($options['host'])) {
            if (! isset($options[CURLOPT_HTTPHEADER])) {
                $options[CURLOPT_HTTPHEADER] = array();
            }
            $options[CURLOPT_HTTPHEADER][] = 'Host: ' . $options['host'];
        }
        // Host 可能是 null
        unset($options['host']);

        // 需要返回内容
        if (! isset($options[CURLOPT_RETURNTRANSFER])) {
            $options[CURLOPT_RETURNTRANSFER] = 1;
        }

        // 并发请求
        if ($this->_curlMultiEnabled) {
            return $this->curlMultiPush($options, $jsonDecode);
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        $url = $options[CURLOPT_URL];

        // 判断 http method
        $method = 'GET';
        if (isset($options[CURLOPT_CUSTOMREQUEST])) {
            $method = $options[CURLOPT_CUSTOMREQUEST];
        } elseif (isset($options[CURLOPT_POSTFIELDS])) {
            $method = 'POST';
        }

        $cost = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        $length = $result === false ? -1 : strlen($result);

        Log::notice('curl', "{$method} \"{$url}\" Cost: {$cost}ms, Length: {$length}");

        $failed = false;
        $message = '';
        $code = 0;
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result !== false) {
            // HTTP响应状态码为 `2xx`, 才认为成功
            if ($httpStatus >= 200 && $httpStatus < 300) {
                // 默认会进行 json_decode()
                if ($jsonDecode) {
                    $message = $result;
                    $result = json_decode($result, true);

                    // JSON 解析出错
                    if ($result === null && strtolower($message) !== 'null') {
                        $code = 1000;
                        $failed = true;
                    }
                }
            } else {
                $message = $result;
                $result = null;
                $failed = true;
            }
        } else {
            // 请求出错
            $code = curl_errno($ch);
            $message = curl_error($ch);
            $failed = true;
        }

        if ($failed) {
            $this->_curlLastError = new CurlError($message, $code, $httpStatus, $options);
            Log::error('curl', "{$method} \"{$url}\" {$code} [{$httpStatus}] {$message}");

            if (DEV) {
                Log::error('curl.options', var_export($options, true));
            }
        } else {
            $this->_curlLastError = null;
        }

        if (is_callable($beforeCurlClose)) {
            $beforeCurlClose($ch, $result);
        }

        curl_close($ch);

        return $result;
    }

    /**
     * 并发请求：准备
     */
    public function multi() {
        if ($this->_curlMultiEnabled) {
            throw new Exception('CurlMultiAlreadyEnabled');
        }
        $this->_curlMultiEnabled = true;
        $this->_curlMultiList = array();
    }

    /**
     * 并发请求：添加一个
     * @param array $options curl参数
     * @param bool $jsonDecode 是否需要json解析
     */
    protected function curlMultiPush(array $options, $jsonDecode = true) {
        if (! $this->_curlMultiEnabled) {
            throw new Exception('CurlMultiNotEnabled');
        }
        $this->_curlMultiList[] = compact('options', 'jsonDecode');
    }

    /**
     * 并发请求：开始执行
     * @return array
     */
    public function exec() {
        if (! $this->_curlMultiEnabled) {
            throw new Exception('CurlMultiNotEnabled');
        }
        $this->_curlMultiEnabled = false;
        $this->_curlMultiLastError = array();
        $results = array();

        // 空请求
        if (empty($this->_curlMultiList)) {
            return $results;
        }

        $mh = curl_multi_init();
        $chList = array();
        $infoList = array();
        $count = count($this->_curlMultiList);
        foreach ($this->_curlMultiList as $args) {
            $options = $args['options'];
            $jsonDecode = $args['jsonDecode'];

            $url = $options[CURLOPT_URL];

            // 判断 http method
            $method = 'GET';
            if (isset($options[CURLOPT_CUSTOMREQUEST])) {
                $method = $options[CURLOPT_CUSTOMREQUEST];
            } elseif (isset($options[CURLOPT_POSTFIELDS])) {
                $method = 'POST';
            }

            $infoList[] = compact('jsonDecode', 'url', 'method');

            $ch = curl_init();
            curl_setopt_array($ch, $options);

            $chList[] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        $active = null;

        $start = microtime(true);
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        $totalCost = round((microtime(true) - $start) * 1000);

        $totalLength = 0;
        foreach ($chList as $i => $ch) {
            $result = curl_multi_getcontent($ch);
            if ($result === null) {
                $result = false;
            }

            // $url, $method, $jsonDecode
            extract($infoList[$i]);

            $cost = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
            $length = $result === false ? -1 : strlen($result);
            $totalLength += $length;

            Log::notice('curl', "{$method} \"{$url}\" Cost: {$cost}ms, Length: {$length}");

            $failed = false;
            $message = '';
            $code = 0;
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($result !== false) {
                // HTTP响应状态码为 `2xx`, 才认为成功
                if ($httpStatus >= 200 && $httpStatus < 300) {
                    // 默认会进行 json_decode()
                    if ($jsonDecode) {
                        $message = $result;
                        $result = json_decode($result, true);

                        // JSON 解析出错
                        if ($result === null && strtolower($message) !== 'null') {
                            $code = 1000;
                            $failed = true;
                        }
                    }
                } else {
                    $message = $result;
                    $result = null;
                    $failed = true;
                }
            } else {
                // 请求出错
                $code = curl_errno($ch);
                $message = curl_error($ch);
                $failed = true;
            }

            if ($failed) {
                $this->_curlMultiLastError[] = new CurlError($message, $code, $httpStatus, $options);
                Log::error('curl', "{$method} \"{$url}\" {$code} [{$httpStatus}] {$message}");
                Log::error('curl.options', var_export($options, true));
            } else {
                $this->_curlMultiLastError[] = null;
            }

            $results[] = $result;

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        Log::notice('curl', "Multi Request * {$count}, Total Cost: {$totalCost}ms, Total Length: {$totalLength}");

        return $results;
    }
}
