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
		class SingleShellTask	11900-11919
		class Ssh2Helper		11920-11929
		class AgentHelper		11930-11939
*/

error_reporting(E_ALL );
ini_set('display_errors','On');
require_once "KLogger.php";
require_once "config.php";
$path = __DIR__.'/phpseclib/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);


$task = new SingleShellTask();
$task_log = new KLogger(dirname(__FILE__), '../logs/SingleShellTask', KLogger::DEBUG);
$async_cmd = '';
$ip_c_list = array();
$data = json_decode($argv[1],true);

$ret = $task->startTask($data);
$ret['data']['task_id'] = $data['task_id'];
$ret['data']['ip'] = $data['ip'];
echo json_encode($ret)."\n";

class SingleShellTask
{
	public function __construct()
	{
		$this->local_ip = AREA_CONFIG::getLocalIP();
		$this->ssh2_helper = new Ssh2Helper();
		$this->mysqlServer = AREA_CONFIG::$dbHost;
		$this->mysqlPort = AREA_CONFIG::$dbPort;
		$this->mysqlDbName = AREA_CONFIG::$dbName;
		$this->mysqlUsername = AREA_CONFIG::$dbUser;
		$this->mysqlPasswd = AREA_CONFIG::$dbPassword;
		$this->passwordUrl= AREA_CONFIG::$passwordUrl;
		$this->passwordHost = AREA_CONFIG::$passwordHost;
        require_once __DIR__."/MagicTool.class.php";
        $this->magic = new MagicTool();
		$this->log = new KLogger(dirname(__FILE__), '../logs/SingleShellTask', KLogger::DEBUG);
	}

	public function startTask($data)
	{
		$task_id = $data['task_id'];
		$ip = $data['ip'];
		$this->log->logDebug('line'.__LINE__.': Input: task_id = '.$task_id.', ip = '.$ip);

		$type = empty($data['type']) ? '' : $data['type'];
		$message = empty($data['message']) ? '' : $data['message'];

		$connect_ip = $ip;
		$connect_port = AREA_CONFIG::$sshPort;
		$this->connect_ip = $connect_ip;
		$this->connect_port = $connect_port;
		$this->task_id = $task_id;
		$this->ip = $ip;

		if('develop' == AREA_CONFIG::$server_type)
		{
			$this->mysqlTableName = 'pkg_test';
		}
		else
		{
			$this->mysqlTableName = 'pkg_'.substr($task_id,0,8);
		}

		$ret = $this->mysqlConnect();
		if(false == $ret)
		{
			return $this->formatReturn(-11900,'mysqlConnect failed, code='.$this->code.', msg='.$this->msg);
		}

		$sql = 'select * from  '.$this->mysqlTableName.'  where task_id="'.$task_id.'" and ip="'.$ip.'"';
		$mysql_ret = $this->mysqlQuery($sql);
		if(empty($mysql_ret))
		{
			return $this->formatReturn(-11901,'mysqli query failed, errno='. $this->mysqli->errno .'error='. $this->mysqli->error .' | sql = '.$sql);
		}

		$task_info = $mysql_ret->fetch_assoc();
		if(empty($task_info))
		{
			return $this->formatReturn(-11902, 'mysqli query null !');
		}

		$task_status = $task_info['task_status'];
		$srcPassword = $task_info['password'];
		$user_name = $task_info['user_name'];
		$timeout = $task_info['timeout'];
        $input_cmd = base64_decode($task_info['input_cmd']);
		if('wait' !== $task_status)
		{
			//return $this->formatReturn(-11903, 'task_status invalid, task_status= '.$task_status);
		}

		//从消息队列拿出来先置状态
		$sql = "update ".$this->mysqlTableName." set task_status='connect',end_time=CURRENT_TIMESTAMP,server_ip='".$this->local_ip.":".getmypid()."' where task_id='".$task_id."' and ip='".$ip."'";
		$ret = $this->mysqlQuery($sql);
		if(false == $ret)
		{
			return $this->formatReturn(-11904,'mysqli update (connect) err, errno='. $this->mysqli->errno .'error='. $this->mysqli->error .' | sql = '.$sql);
		}

        if(empty($srcPassword))
        {
            //$srcPassword = AREA_CONFIG::getRootPassword($ip);
            $srcPassword = $this->getPassword($ip);
        }
        $this->ssh2 = $this->ssh2_helper;
        $ret = $this->ssh2->connect($ip,$connect_port,$srcPassword,$user_name);
        if(0 != $ret)
        {
            return $this->formatReturn($ret, 'ssh2 connect failed !');
        }

        //连接上目标机器置connect状态
        $sql = "update  ".$this->mysqlTableName."  set task_status='run',end_time=CURRENT_TIMESTAMP where task_id='".$task_id."' and ip='".$ip."'";
        $ret = $this->mysqlQuery($sql);
        if(false == $ret)
        {
            return $this->formatReturn(-11910,'mysqli update (run) err, errno='. $this->mysqli->errno .' , error='. $this->mysqli->error .' | sql = '.$sql);
        }

        $shell_result = $this->ssh2->runShell($input_cmd,$timeout);
        if(false === $shell_result)
        {
            return $this->formatReturn(-11911, 'runShell failed !');
        }

        $shell_result = addslashes(mb_convert_encoding($shell_result,'utf-8','utf-8,GBK,GB2312'));
        $sql = "update  ".$this->mysqlTableName."  set task_status='suc', result ='". $shell_result ."', end_time=CURRENT_TIMESTAMP where task_id='".$task_id."' and ip='".$ip."'";
        $ret = $this->mysqlQuery($sql);
        if(false == $ret)
        {
            return $this->formatReturn(-11912,'mysqli update suc err, errno='. $this->mysqli->errno .' error='. $this->mysqli->error .' | sql = '.$sql);
        }

        return $this->formatReturn(0, 'OK');
    }

    public function formatReturn($code,$msg,$data = array())
    {
        //获取调用所在行号
        $debug_info = debug_backtrace();
        $line = $debug_info[0]['line'];

        if(0 == $code)
        {
            $this->log->logDebug('line'.$line.':  Output: code = '.$code.' msg = '.$msg.' task_id = '.$this->task_id.' ip = '.$this->ip.' connect_ip = '.$this->connect_ip.' connect_port = '.$this->connect_port);
        }
        else
        {
            $this->log->logError('line'.$line.':  Output: code = '.$code.' msg = '.$msg.' task_id = '.$this->task_id.' ip = '.$this->ip.' connect_ip = '.$this->connect_ip.' connect_port = '.$this->connect_port);

            //失败则更新数据记录状态
            if($this->mysqli)
            {
				$sql = 'update '.$this->mysqlTableName.' set task_status="fail", ret_code ="'.$code.'", result =" '. $msg .'", end_time = CURRENT_TIMESTAMP where task_id="'.$this->task_id.'" and ip="'.$this->ip.'"';
				$ret = $this->mysqlQuery($sql);
				if(false == $ret)
				{
					$this->log->logError('line'.__LINE__.': '.$this->mysqli->errno.' mysqli update(fail) err, err='. $this->mysqli->error .' | sql = '.$sql);
				}
				else
				{
					$this->log->logDebug('line'.__LINE__.': '.$this->mysqli->errno.' mysqli update(fail) suc, error='. $this->mysqli->error .' | sql = '.$sql);
				}
			}
			else
			{
				$this->log->logError('line'.__LINE__.': this->mysqli is null');
			}
		}

		$this->mysqli->close();

		return array('code'=>$code,'msg'=>$msg,'data'=>$data,'time'=>date('Ymd H:i:s', time()));
	}

    public function getPassword($ip)
    {
        $opt = array(
                'ip'=>$this->passwordHost,
                'data'=>'deviceId='.$ip,
                "method"=>"GET",
                "timeout"=>60,
                );
        $ret = $this->magic->http_request($this->passwordUrl,$opt);
        $data = json_decode($ret,true); 
        $password = $data['password'];
        return $password;
    }
    private function mysqlConnect()
    {
        $mysqli = new mysqli($this->mysqlServer,
                $this->mysqlUsername,$this->mysqlPasswd,$this->mysqlDbName,$this->mysqlPort);

        if($mysqli->connect_errno)
        {
            sleep(1);
            $mysqli = new mysqli($this->mysqlServer,
                    $this->mysqlUsername,$this->mysqlPasswd,$this->mysqlDbName,$this->mysqlPort);
            if($mysqli->connect_errno)
            {
                sleep(1);
                $mysqli = new mysqli($this->mysqlServer,
                        $this->mysqlUsername,$this->mysqlPasswd,$this->mysqlDbName,$this->mysqlPort);
                if($mysqli->connect_errno)
                {
                    $this->log->logError('line'.__LINE__.': mysqli connect err, errno='. $mysqli->connect_errno .'error='. $mysqli->connect_error);
                    $this->code = $mysqli->connect_errno;
                    $this->msg = 'mysqli connect failed, err='. $mysqli->connect_error;					
                    return false;
                }
            }
        }

        $this->mysqli = $mysqli;

        $sql = "SET NAMES 'utf8'";
        $ret = $this->mysqlQuery($sql);
        if(true != $ret)
		{
			$this->log->logError('line'.__LINE__.': mysqli query err, errno='. $mysqli->errno .'error='. $mysqli->error .'sql = '.$sql);
			$this->code = $mysqli->errno;
			$this->msg = 'mysqli query failed, err='. $mysqli->error .'sql = '.$sql;			
			return false;
		}

		return true;
	}

	private function mysqlQuery($sql)
	{
		$ret = $this->mysqli->query($sql);
		if(false == $ret)
		{
			sleep(1);
			$ret = $this->mysqli->query($sql);
			if(false == $ret)
			{
				sleep(1);
				$ret = $this->mysqli->query($sql);
			}
		}

		return $ret;
	}

	private $mysqlServer;
	private $mysqlPort;
	private $mysqlDbName;
	private $mysqlTableName;
	private $mysqlUsername;
	private $mysqlPasswd;
	private $mysqli;
	private $local_ip;
	private $ssh2;
	private $ssh2_helper;
	private $log;
	private $code;
	private $msg;
	private $task_id;
	private $ip;
	private $connect_ip;
	private $connect_port;
}

class Ssh2Helper
{
	private $ssh;
	private $sftp;
	private $connect_timeout = 60;
	private $log;
	private $last_buf = "";
	private $agent;
	public function __construct()
	{
	//	set_include_path('/usr/local/services/MagicFlowExtension/lib');
		require_once 'Net/SSH2.php';
		require_once 'Net/SFTP.php';
		require_once 'Crypt/RSA.php';
		$this->log = new KLogger(dirname(__FILE__), '../logs/SingleShellTask', KLogger::DEBUG);
	}
	public function connect($ip,$port,$password,$user_name = 'user_00')
	{
		//获取密码出错
		if(empty($password) && AREA_CONFIG::$authentication == "password" )
		{
			$this->log->logError('line'.__LINE__.': Get Password Error: ip = '.$ip.', user_name = '.$user_name);
			return -11920;
		}

		//ip不在密码库里
		if((false !== strpos($password,':{"result":false,"message":')) || (false !== strpos($password,"\n")))
		{
			$this->log->logError('line'.__LINE__.': Get Password Failed: ip = '.$ip.', user_name = '.$user_name.', password = '.$password);
			return -11921;
		}
        if(AREA_CONFIG::$authentication == "publickey")
        {

            $key = new Crypt_RSA();
            $key->loadKey(file_get_contents(AREA_CONFIG::$privatekey));

            $this->ssh = new Net_SSH2($ip,$port,$this->connect_timeout);
            if (!$this->ssh->login($user_name, $key)) 
            {
                $this->log->logError('line'.__LINE__.': Net_SSH2 Login Failed: ip = '.$ip.', user_name = '.$user_name.', key= '.AREA_CONFIG::$privatekey);
                return -11922;
            }
        }
        else
        {
            $this->ssh = new Net_SSH2($ip,$port,$this->connect_timeout);
            if (!$this->ssh->login($user_name, $password))
            {
                usleep(mt_rand(5, 30)*100000);
                if (!$this->ssh->login($user_name, $password))
                {
                    usleep(mt_rand(5, 30)*100000);
                    if (!$this->ssh->login($user_name, $password))
                    {
                        $this->log->logError('line'.__LINE__.': Net_SSH2 Login Failed: ip = '.$ip.', user_name = '.$user_name.', password = '.$password);
                        return -11922;
                    }
                }
            }
        }
        return 0;
        }

        public function runShell($shell,$timeout)
        {
            // Run a command that will probably write to stderr (unless you have a folder named /hom)
            $this->ssh->setTimeout($timeout);
            $shell_return = $this->ssh->exec($shell);
            return $shell_return;
        }
        public function close()
        {
            //$ret = $this->ssh->exec('exit');
        }
    }

