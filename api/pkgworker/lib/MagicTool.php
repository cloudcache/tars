<?php
/**
 * @author [pekinglin] <[email]>
 */
class MagicTool
{
    private $log;

    public function __construct()
    {
        require_once __DIR__ .'/log4php/Log.php';
        $this->log = new Log(__CLASS__);
        $this->log = $this->log->getLogger();
    }

    /**
     * [log 打日志]
     * @param  [type] $msg [description]
     * @return [type]      [description]
     */
	public function log($msg)
	{
		$this->log->info($msg);
	}

    /**
     * [httpRequest 发起请求]
     * @param  [type] $url    [url]
     * @param  array  $option [option ip, timeout, method, data(array), decode]
     * @return [type]         [description]
     */
	public function httpRequest($url, $option = array())
    {
        $this->log("request url:$url");
        $this->log("request data:" . json_encode($option));
		if ((!empty($option)) && (!is_array($option))) {
            $this->log("error:option is not array");
			return false;
		}
		if (empty($url)) {
			$this->log("error:url is null");
			return false;
		}
		$copyUrl = $url;
		list($req_url, ) = explode('?', $copyUrl, 2);
		//设置超时时间
		$timeout = array_key_exists('timeout', $option) ? $option['timeout'] : 60;
		//获取请求方法
		$method = array_key_exists('method', $option) ? strtoupper($option['method']) : 'GET';
		//请求数据
		$data = array_key_exists('data', $option) ? $option['data'] : '';
		//构造请求数据
		if ($method == 'GET') {
			$data = is_array($data) ? http_build_query($data) : $data;
			$conFlag = strpos($url, '?') ? "&" : "?";
			$url .= $conFlag . $data;
		}
		//如果指定了ip，转换url，host
		$curlHandle = curl_init();
		if (array_key_exists('ip', $option)) {
			$ip = $option['ip'];
			$match_count = preg_match('/^http(s)?:\/\/([-0-9a-z.]+)+(:\d+)?\//', trim($url), $matches);
			if (!$match_count) {
                $this->log("error:url format error");
				return false;
			}
			$host = $matches[2];
			$url = preg_replace('/' . $host . '/', $ip, trim($url));
			$curlHost = array("Host:" . $host);   //绑定域名到此ip地址上
			curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $curlHost);  // 传送头信息
		}
		if ($method == 'POST') {
			curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($curlHandle, CURLOPT_URL, $url);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, $timeout);
		$returnData = curl_exec($curlHandle);
		$httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		if (curl_errno($curlHandle)) {
            $this->log("error:curl error, " . curl_error($curlHandle));
			$succFlag = false;
			return $returnData;
		}
		curl_close($curlHandle);
		if ($httpCode !== 200) {
            $this->log("error:server response error ,ret code is " . $httpCode);
			$succFlag = false;
			return $returnData;
		}
		if (array_key_exists('decode', $option) && $option['decode'] == true) {
		    $decode_data = json_decode($returnData, true);
			$returnData = $decode_data;
		}
        $this->log("request return data");
		return $returnData;
	}

}


