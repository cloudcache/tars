<?php
error_reporting(E_ALL );
require_once "KLogger.php";
require_once "config.php";

$task_id= isset($_REQUEST['task_id']) ? $_REQUEST['task_id'] : false;
//$task_id='20150403_551e098f541153.67948350';

if(empty($task_id) || (strlen($task_id) <= 8))
{
    $msg = 'task_id is empty!';
    echo json_encode(array('code'=>-11820,'msg'=>'task_id para error','data'=>array())) ;
    exit;
}
//前8个为日期，用于查数据表
if(!is_numeric(substr($task_id,0,8)))
{
    $msg = 'task_id is wrong!';
    echo json_encode(array('code'=>-11821,'msg'=>'task_id para format error','data'=>array())) ;
    exit;
}

$task = new QueryTask();
$ret = $task->startTask($task_id);
echo json_encode($ret);

class QueryTask
{
    public function __construct()
	{
		$this->mysqlServer = AREA_CONFIG::$dbHost;
		$this->mysqlPort = AREA_CONFIG::$dbPort;
		$this->mysqlDbName = AREA_CONFIG::$dbName;
		$this->mysqlUsername = AREA_CONFIG::$dbUser;
		$this->mysqlPasswd = AREA_CONFIG::$dbPassword;
		$this->log = new KLogger(dirname(__FILE__), 'log/QueryShellTask', KLogger::DEBUG);
    }
    public  function startTask($task_id)
    {		
		$this->task_id = $task_id;
		$this->log->logDebug('line'.__LINE__.': Input: task_id = '.$task_id);

        $ret = $this->mysqlConnect();
        if(false == $ret)
        {
			return $this->formatReturn(-11822,'mysqlConnect failed, code='.$this->code.', msg='.$this->msg);
        }

		if('develop' == AREA_CONFIG::$server_type)
		{
			$mysqlTableName = 'pkg_test';
		}
		else
		{
			$mysqlTableName = 'pkg_'.substr($task_id,0,8);
		}
		
		$sql = 'select ip,task_status,result,start_time,end_time from '.$mysqlTableName.' where task_id="'.$task_id.'"';
		$mysql_ret = $this->mysqli->query($sql);
		if(empty($mysql_ret))
		{
			return $this->formatReturn(-11823, 'mysqli query failed, errno='. $this->mysqli->errno .',error='. $this->mysqli->error .', sql = '.$sql);
		}

		$task_info = $mysql_ret->fetch_assoc();
		if(empty($task_info))
		{
			return $this->formatReturn(-11824, 'mysqli query null !');
		}

		$finish_all = true;
		$result = array();
		$return_data = array();
		while($task_info)
		{
			if(('suc' != $task_info['task_status']) && ('fail' != $task_info['task_status']))
			{
				$finish_all = false;
			}
			
			$result[$task_info['ip']] = $task_info;
			$task_info = $mysql_ret->fetch_assoc();
		}
		
		$return_data['finish_all'] = $finish_all;
		$return_data['result'] = $result;

		return $this->formatReturn(0, 'OK', $return_data);
    }

	public function formatReturn($code,$msg,$data = array())
	{
		if(0 == $code)
		{
			$this->log->logDebug('line'.__LINE__.': Output: code = '.$code.' msg = '.$msg.' task_id = '.$this->task_id);
		}
		else
		{
			$this->log->logError('line'.__LINE__.': Output: code = '.$code.' msg = '.$msg.' task_id = '.$this->task_id);
		}

		return array('code'=>$code,'msg'=>$msg,'data'=>$data);
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
	private $log;
	private $code;
	private $msg;
	private $task_id;
}

