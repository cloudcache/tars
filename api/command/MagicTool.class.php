<?php
//ini_set("display_errors", "On");
//error_reporting(E_ALL);


//----------------------------------------
class MagicTool{
	//打LOG
	public function Log($level, $log)
	{
		$cur_path = dirname(__FILE__);
		$datetime = date("Y-m-d H:i:s", time());
		$log_data = $datetime . " " . $level . " " . $log;
		file_put_contents($cur_path . "/$datetime.log", $log_data . "\n", FILE_APPEND);
	}


	/*
	 * 发送http请求
	 * @param  $url  请求url，必填
	 * @param  $opt  请求参数，数组，可选参数为
	 * 				timeout	:超时时间
	 * 				method  :GET/POST
	 * 				data    :类型为string或array，				
	 * 				ip		:域名对应的ip，指定ip后，url中的域名将不依赖host和域名解析
	 * 				decode  : true/false ，是否对结果进行json_decode
	 * 				check_callback :检查结果回调函数,例:function check($ret_data);
	 * 				
	 * @example  
	 * $url = "http://api.qq.com/a/interface/interface1.php"
	 * $opt = array(
	 * 	'ip'=>'192.168.1.1',
	 * 	'data'=>json_encode(array('xxx'=>'xx')),
	 * 	"method"=>"POST",
	 *  "timeout"=>60,
	 *  "decode" => true,
	 *  "header" => array('Content-Type: application/json'),
	 * );
	 * $ret = http_request($url,$opt);
	 * 				
	 */

	public function http_request($url, $opt = array(),&$code=0,&$msg='') {
		if (!empty($opt) && !is_array($opt)) {
            $code = -1001;
			$msg = "error:opt is not array\n";
			return false;
		}
		if (empty($url)) {
            $code = -1002;
			$msg = "error:url is null\n";
			return false;
		}
		$org_url = $url;
		list($req_url, ) = explode('?', $org_url, 2);
		//设置超时时间
		$timeout = array_key_exists('timeout', $opt) ? $opt['timeout'] : 60;
		//获取请求方法
		$method = array_key_exists('method', $opt) ? strtoupper($opt['method']) : 'GET';
		//请求数据
		$data = array_key_exists('data', $opt) ? $opt['data'] : '';
		//构造请求数据
		if ($method == 'GET') {
			$data = is_array($data) ? http_build_query($data) : $data;
			$con_flag = strpos($url, '?') ? "&" : "?";
			$url .= $con_flag . $data;
		}
		//如果指定了ip，转换url，host
        $http_header = array();
		$ch = curl_init();
		if (array_key_exists('ip', $opt)) {
			$ip = $opt['ip'];
			$match_count = preg_match('/^http(s)?:\/\/([-0-9a-z.]+)+(:\d+)?\//', trim($url), $matches);
            if (!$match_count) {
                $code = -1003;
                $msg = "error:url format error\n";
				return false;
			}
			$host = $matches[2];
			$url = preg_replace('/' . $host . '/', $ip, trim($url));
			//$curl_host = array("Host:" . $host);   //绑定域名到此ip地址上
			//curl_setopt($ch , CURLOPT_HTTPHEADER, $curl_host);  // 传送头信息
            $http_header[] = "Host:".$host;
		}
        if(array_key_exists('header', $opt))
        {
            if(is_array($opt['header']))
            {
                $http_header = array_merge($http_header,$opt['header']);
            }
        }
		curl_setopt($ch , CURLOPT_HTTPHEADER, $http_header);  // 传送头信息
        switch ($method){  
            case "GET" : curl_setopt($ch, CURLOPT_HTTPGET, true);break;  
            case "POST": curl_setopt($ch, CURLOPT_POST,true);   
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);break;  
            case "PUT" : curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");   
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);break;  
            case "DELETE":curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "DELETE");   
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);break;  
            case "PATCH":curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PATCH");   
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);break;  
        }  
        curl_setopt($ch , CURLOPT_URL, $url);
        curl_setopt($ch , CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch , CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch , CURLOPT_TIMEOUT, $timeout);
        $start_time = microtime(true);
        $ret_data = curl_exec($ch );
        $http_code = curl_getinfo($ch , CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);
        if (curl_errno($ch )) {
            $msg = "error:curl error, " . curl_error($ch) . "\n";
            $code = -1004;
            $succ_flag = false;
            return $ret_data;
        }
        $code = $http_code;
        $msg = curl_error($ch);
        curl_close($ch);
        if (array_key_exists('decode', $opt) && $opt['decode'] == true) {
            $decode_data = json_decode($ret_data, true);
            $ret_data = $decode_data;
        }
        return $ret_data;
    }

}


?>
