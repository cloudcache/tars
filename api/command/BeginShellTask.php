<?php
/*
	返回码说明

	11000-11099	命令通道管理系统
	11100-11199	任务服务器
	11200-11299	鉴权服务器
	11300-11399	命令通道agent端
	11400-11499	附加脚本错误码
	11800-11899	旧系统接口层
	11900-11999	旧系统通道服务器
		BeginShellTask.php	11800-11819
		QueryShellTask.php	11820-11829
*/

error_reporting(E_ALL );
ini_set('display_errors','On');
require_once "KLogger.php";
require_once "config.php";
require_once __DIR__."/../publicsrc/conf/Conf.php";


if(empty($_REQUEST['dst_ips']))
{
    $msg = 'dst ip is empty';
    echo json_encode(array('code'=>-11801,'msg'=>'dst_ips para error','data'=>$msg)) ;
    exit;
}
if(empty($_REQUEST['input_cmd']))
{
    $msg = 'input_cmd is empty';
    echo json_encode(array('code'=>-11802,'msg'=>'input_cmd para error','data'=>$msg)) ;
    exit;
}

if(empty($_REQUEST['user_name']))
	$_REQUEST['user_name']= 'root';
if(empty($_REQUEST['timeout']))
	$_REQUEST['timeout']= '600';

if (get_magic_quotes_gpc())
{
	$input_cmd = stripslashes($_REQUEST['input_cmd']);
}
else
{
	$input_cmd = $_REQUEST['input_cmd'];
}

$dst_ips= $_REQUEST['dst_ips'];
$password= isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
$user_name= $_REQUEST['user_name'];
$timeout= $_REQUEST['timeout'];


$ip_pattern = "/((25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(25[0-5]|2[0-4]\d|1?\d?\d)/";
$match_count = preg_match_all($ip_pattern,$dst_ips,$ip_matches);
if(0 == $match_count)
{
    $msg = 'dst ip format error';
    echo json_encode(array('code'=>-11803,'msg'=>'ip para error','data'=>$msg)) ;
    exit;
}
foreach($ip_matches[0] as $ip)
{
    $ip_list[] = $ip;
}
$ip_list = array_unique($ip_list);

$task = new BeginTask();
$ret = $task->startTask($ip_list,$password,$input_cmd,$user_name,$timeout);
echo json_encode($ret);

class BeginTask
{
	public function __construct()
	{
		$this->mysqlServer = AREA_CONFIG::$dbHost;
		$this->mysqlPort = AREA_CONFIG::$dbPort;
		$this->mysqlDbName = AREA_CONFIG::$dbName;
		$this->mysqlUsername =  AREA_CONFIG::$dbUser;
		$this->mysqlPasswd =  AREA_CONFIG::$dbPassword;
		$this->log = new KLogger(dirname(__FILE__), 'log/BeginShellTask', KLogger::DEBUG);
	}

	public function startTask($ip_list,$password,$input_cmd,$user_name,$timeout)
	{
		global $async;
		if('develop' == AREA_CONFIG::$server_type)
		{
			$mysqlTableName = 'pkg_test';
		}
		else
		{
			$mysqlTableName = 'pkg_'.date('Ymd', time());
		}

		$this->log->logDebug('line'.__LINE__.': Input: ip_list = '.json_encode($ip_list).', input_cmd = '.$input_cmd);

		$task_id = date('Ymd', time()).'_'.uniqid('',true);
		$ret = $this->mysqlConnect();
		if(false == $ret)
		{
			return $this->formatReturn(-11804,'mysqlConnect failed, code='.$this->code.', msg='.$this->msg);
		}

		$code = 0;
		$msg = '';
		//$password_info_array = AREA_CONFIG::getPasswordManage($ip_list,$code,$msg);
		//if(false === $password_info_array)
		//{
		//	return $this->formatReturn(-11805, 'getPasswordManage failed, code='.$code.' msg='.$msg);
		//}

		foreach($ip_list as $ip)
		{
			if(empty($ip))
			{
				continue;
			}

			$sql = 'INSERT INTO  `'.$mysqlTableName.'` (
				   `task_id` ,
				   `task_status` ,
				   `ip` ,
				   `password` ,
				   `input_cmd` ,
				   `user_name` ,
				   `timeout` ,
				   `start_time`
				   )
			   VALUES (
					   "'.$task_id.'",
					   "wait",
					   "'.$ip.'",
					   "'.$password.'",
					   "'.base64_encode($input_cmd).'",
					   "'.$user_name.'",
					   "'.$timeout.'",
					   CURRENT_TIMESTAMP
					  );';

			$ret = $this->mysqli->query($sql);
			if(false == $ret)
			{
				//数据表不存在，则先创建
				if(1146 === $this->mysqli->errno)
				{
					$ret = $this->createTable($mysqlTableName);
					if(0 !== $ret)
					{
						return $this->formatReturn(-11806, 'mysqli create table failed, errno='. $this->mysqli->errno .',error='. $this->mysqli->error);
					}

					$ret = $this->mysqli->query($sql);
					if(false == $ret)
					{
						return $this->formatReturn(-11807, 'mysqli RE INSERT failed, errno='. $this->mysqli->errno .',error='. $this->mysqli->error .', sql = '.$sql);
					}
				}
				else
				{
					return $this->formatReturn(-11808, 'mysqli 1st INSERT failed, errno='. $this->mysqli->errno .',error='. $this->mysqli->error .', sql = '.$sql);
				}
			}

			$quene_data = array();
			$quene_data['task_id'] = $task_id;
			$quene_data['ip'] = $ip;
		//	$password_info = empty($password_info_array[$ip]) ? array() : $password_info_array[$ip];
			$type = empty($password_info['type']) ? '' : $password_info['type'];
			$message = empty($password_info['message']) ? '' : $password_info['message'];
			//if(0 === $password_info['result'] && !empty($password_info['type']))
			//{
			//	$quene_data['type'] = $type;
			//	$quene_data['message'] = $message;
			//}

            $queue_content = json_encode($quene_data);
            $phpPath = AREA_CONFIG::$phpPath;

            $cmd = $phpPath ." ./SingleShellTask.php '$queue_content' >/dev/null 2>&1 &";
			$this->log->logDebug('line'.__LINE__.': cmd: '.$cmd);
            $ret = shell_exec($cmd);
		}

		return $this->formatReturn(0, 'OK', array('task_id'=>$task_id));
    }

	public function formatReturn($code,$msg,$data = array())
	{
		if(0 == $code)
		{
			$this->log->logDebug('line'.__LINE__.': Output: code = '.$code.' msg = '.$msg.' data = '.json_encode($data));
		}
		else
		{
			$this->log->logError('line'.__LINE__.': Output: code = '.$code.' msg = '.$msg.' data = '.json_encode($data));
		}


		return array('code'=>$code,'msg'=>$msg,'data'=>$data);
	}

	public function createTable($mysqlTableName)
	{
		$sql = "CREATE TABLE if not exists `".$mysqlTableName."` like pkg_default";
		$ret = $this->mysqli->query($sql);
		if(false == $ret)
		{
			$ret = $this->mysqli->query($sql);
			if(false == $ret)
			{
				$this->log->logError('line'.__LINE__.': mysqli create errno='. $this->mysqli->errno .'err='. $this->mysqli->error .', sql = '.$sql);
				return $this->mysqli->errno;
			}
		}
		return 0;
	}

	private function mysqlConnect()
	{
		$mysqli = new mysqli($this->mysqlServer,
				$this->mysqlUsername,$this->mysqlPasswd,$this->mysqlDbName,$this->mysqlPort);

		if($mysqli->connect_errno)
		{
			$this->log->logError('line'.__LINE__.': mysqli connect errno = '.$mysqli->connect_errno);
			$this->code = $mysqli->connect_errno;
			$this->msg = 'mysqli connect failed, err='. $mysqli->connect_error;
			return false;
		}

		$sql = "SET NAMES 'utf8'";
		$ret = $mysqli->query($sql);
		if(true != $ret)
		{
			$this->log->logError('line'.__LINE__.': mysqli query err, sql = '.$sql);
			$this->code = $mysqli->errno;
			$this->msg = 'mysqli query failed, err='. $mysqli->error .'sql = '.$sql;
			return false;
		}

		$this->mysqli = $mysqli;
		return true;
	}

    private $mysqlServer;
    private $mysqlPort;
    private $mysqlUsername;
    private $mysqlDbName;
    private $mysqlPasswd;
	private $mysqli;
	private $rabbitmq;
	private $quene_name;
	private $log;
	private $code;
	private $msg;
}

function request_ip_in_white_list()
{
	$klog = new KLogger(dirname(__FILE__), 'log/BeginShellTask', KLogger::DEBUG);

	$mysqli = new mysqli(AREA_CONFIG::$dbHost,'itil','itil',AREA_CONFIG::$dbName,AREA_CONFIG::$dbPort);
	if($mysqli->connect_errno)
	{
		$klog->logError('line'.__LINE__.': mysqli connect errno = '.$mysqli->connect_errno);
		return false;
	}

	$sql = "SET NAMES 'utf8'";
	$ret = $mysqli->query($sql);
	if(true != $ret)
	{
		$klog->logError('line'.__LINE__.': mysqli query err, sql = '.$sql);
		return false;
	}

	$sql = 'select ip from shell_white_ip';
	$mysql_ret = $mysqli->query($sql);
	if(true != $mysql_ret)
	{
		$klog->logError('line'.__LINE__.': mysqli query err, sql = '.$sql);
		return false;
	}

	$ip_while_list = array();
	$ip_info = $mysql_ret->fetch_assoc();
	while($ip_info)
	{
		$ip_while_list[] = $ip_info['ip'];
		$ip_info = $mysql_ret->fetch_assoc();
	}

	if(!in_array($_SERVER["REMOTE_ADDR"],$ip_while_list))
	{
		return false;
	}

	return true;
}


