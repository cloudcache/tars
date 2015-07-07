<?php
namespace operation\src;

use publicsrc\conf\Conf;
use publicsrc\src\Package;
use publicsrc\src\ExecShell;
use publicsrc\lib\Log;
use publicsrc\lib\Amqp;
use publicsrc\lib\Database;
use publicsrc\src\MagicTool;
use Flight;

class PackageOperation
{
    private $pkgHome;
    private $pkgCodeHome;
    private $installCopyHome;
    public $errorMsg;
    private $log;

    function __construct()
    {
        $this->log = new Log(__CLASS__);
        $this->pkgHome = Conf::get('package_home');
        $this->pkgPath = Conf::get('package_path_export');
        $this->uploadPkgPath = Conf::get('package_path_update');
        $this->errorMsg = null;
        $this->fileMaxSize = intval(Conf::get('max_file_size'));
    }

    public function test()
    {

    }

    /**
     * [parameterCheck check if parameters are valid]
     * @param  [type] $paraNameArray       [description]
     * @param  [type] $realParametersArray [description]
     * @return [type]                      [description]
     */
    public function checkParameter($paraNameArray, &$realParametersArray, $optionalPara=null)
    {
        $error = '';
        foreach ($paraNameArray as $name) {
            if (!isset($realParametersArray[$name])) {
                $error .= "$name;";
            }
        }
        if (!empty($optionalPara)) {
            foreach ($optionalPara as $name) {
                if (!isset($realParametersArray[$name])) {
                    $realParametersArray[$name] = '';
                }
            }
        }

        if (!empty($error)) {
            $error = "parameter empty:$error";
            Flight::json(array('error'=>$error), 400);
        }
        return $error;
    }

    /**
     * [install 发起任务安装包]
     * @return [type] [description]
     */
    public function install()
    {
        $this->log->info("START INSTALL FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $this->log->info(json_encode($data));
        $needPara = array('product', 'name', 'version', 'frameworkType',
            'ipList', 'renameList', 'paraList', 'startAfterComplete', 'operator');
        $optionalPara = array('batchNum', 'batchInterval');
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        //去掉version等于last的操作
        $ipList = $data['ipList'];
        $taskId = $this->addTask($ipList, 'install', $data['operator'], $data);
        $this->log->info($taskId);
        $this->startTask($taskId);
        $product = $data['product'];
        $name = $data['name'];
        $version = $data['version'];
        $object = "安装程序包 /$product/$name";
        $operator = $data['operator'];
        $description = "安装程序包 /$product/$name,版本$version";
        $this->reportToLogSystem($ipList, $object, $operator, $description);
        //成功
        $errorArray['taskId'] = $taskId;
        Flight::json($errorArray, 200);
    }

    /**
     * [update 升级]
     * @return [type] [description]
     */
    public function update()
    {
        $this->log->info("START UPDATE FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $this->log->info(json_encode($data));
        $needPara = array('product', 'name', 'operator','fromVersion', 'toVersion',
            'installPath', 'stopBeforeUpdate', 'forceUpdate', 'restartAfterUpdate',
            'updateAppName', 'updatePort', 'hotRestart', 'updateStartStopScript',
            'copyFileInstallOrCp', 'batchNum', 'batchInterval', 'ignoreFileList',
            'restartOnlyApp', 'ipList');
        $error = $this->checkParameter($needPara, $data);

        $ipList = $data['ipList'];
        $operator = $data['operator'];
        $taskId = $this->addTask($ipList, 'update', $operator, $data);
        $this->log->info($taskId);
        $this->startTask($taskId);
        $product = $data['product'];
        $name = $data['name'];
        $fromVersion = $data['fromVersion'];
        $toVersion = $data['toVersion'];
        $object = "升级程序包 /$product/$name";
        $description = "升级程序包 /$product/$name,从$fromVersion 到 $toVersion";
        $this->reportToLogSystem($ipList, $object, $operator, $description);
        //成功
        $errorArray['taskId'] = $taskId;
        Flight::json($errorArray, 200);
    }

    /**
     * [rollback 回滚操作]
     * @return [type] [description]
     */
    public function rollback()
    {
        $this->log->info("START ROLLBACK FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $this->log->info(json_encode($data));
        $needPara = array('product', 'name', 'operator','ipList','installPath',
            'currentVersion');
        $error = $this->checkParameter($needPara, $data);

        $ipList = $data['ipList'];
        $operator = $data['operator'];
        $taskId = $this->addTask($ipList, 'rollback', $operator, $data);
        $this->log->info($taskId);
        $this->startTask($taskId);
        $product = $data['product'];
        $name = $data['name'];
        $currentVersion = $data['currentVersion'];
        $object = "回滚程序包 /$product/$name";
        $description = "回滚程序包 /$product/$name,从$currentVersion 回滚";
        $this->reportToLogSystem($ipList, $object, $operator, $description);
        //成功
        $errorArray['taskId'] = $taskId;
        Flight::json($errorArray, 200);
    }

    /**
     * [maintance 日常维护脚本]
     * @return [type] [description]
     */
    public function maintenance()
    {
        $this->log->info("START MAINTANCE FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $this->log->info(json_encode($data));
        $needPara = array('product', 'name', 'operator','ipList','installPath',
            'operation', 'packageUser','frameworkType', 'batchNum',
            'batchInterval');
        $optionalPara = array('hotRestart');
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $ipList = $data['ipList'];
        $operator = $data['operator'];
        $operation = $data['operation'];
        $taskId = $this->addTask($ipList, $operation, $operator, $data);
        $this->log->info($taskId);
        $this->startTask($taskId);
        $product = $data['product'];
        $name = $data['name'];
        $object = "维护程序包 /$product/$name $operation";
        $description = "维护程序包 /$product/$name $operation";
        $this->reportToLogSystem($ipList, $object, $operator, $description);
        //成功
        $errorArray['taskId'] = $taskId;
        Flight::json($errorArray, 200);
    }

    /**
     * [getTaskResult 获取任务结果]
     * @return [type] [description]
     */
    public function getTaskResult()
    {
        $this->log->info("START MAINTANCE FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        //获取参数 检查
        $data = Flight::request()->query->getData();
        $this->log->info(json_encode($data));
        $needPara = array('taskId');
        $error = $this->checkParameter($needPara, $data);

        //分表逻辑
        $taskId = $data['taskId'];
        $database = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_task_table'));

        $toSelect = array('task_id'=>$taskId);
        $dbRes = $database->selectValue($toSelect);
        if (empty($dbRes)) {
            $errorArray['error'] = 'search database error, taskid not exists in taskinfo';
            $this->log->info($errorArray['error']);
            Flight::json($errorArray, 400);
        }

        $taskParameters = $dbRes[0]['param'];
        $taskParameters = json_decode(($taskParameters), true);
        $product = $taskParameters['product'];
        $name = $taskParameters['name'];
        //读各个ip结果
        $dbRes = $database->selectValue($toSelect, Conf::get('pkg_db_task_result_table'));
        if (empty($dbRes)) {
            $errorArray['error'] = 'search database error, taskid not exists in taskdetail';
            $this->log->info($errorArray['error']);
            Flight::json($errorArray, 400);
        }
        $resultList = array();
        //将读到数据装到结果里
        foreach ($dbRes as $index => $value) {
            $tmp = array();
            $tmp['ip'] = $value['ip'];
            $tmp['taskId'] = $value['id'];
            $tmp['packagePath'] = "/$product/$name";
            $tmp['name'] = $name;
            $tmp['lastErrmsg'] = $value['error'];
            $tmp['taskInfo'] = $value['task_info'];
            $tmp['status'] = $value['status'];
            $resultList[] = $tmp;
        }
        $errorArray = $resultList;
        Flight::json($errorArray, 200);
    }

    /**
     * [getTaskResult 获取主任务结果]
     * @return [type] [description]
     */
    public function getTaskResultAll()
    {
        $this->log->info("START MAINTANCE FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        //获取参数 检查
        $data = Flight::request()->query->getData();
        $this->log->info(json_encode($data));
        $needPara = array('taskId');
        $error = $this->checkParameter($needPara, $data);

        //分表逻辑
        $taskId = $data['taskId'];
        $database = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_task_table'));

        $toSelect = array('task_id'=>$taskId);
        $dbRes = $database->selectValue($toSelect);
        if (empty($dbRes)) {
            $errorArray['error'] = 'search database error, taskid not exists in taskinfo';
            $this->log->info($errorArray['error']);
            Flight::json($errorArray, 400);
        }
        $dbRes = $dbRes[0];
        $dbRes['param'] = json_decode($dbRes['param'], true);
        $errorArray = $dbRes;
        Flight::json($errorArray, 200);
    }

    /**
     * [getTaskByOperator 根据操作者查询记录]
     * @return [type] [description]
     */
    public function getTaskCountByOperator()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array();
        //获取参数 检查
        $data = Flight::request()->query->getData();
        $this->log->info(json_encode($data));
        if (!isset($data['operator']) && !(isset($data['product']) && isset($data['name'])) ) {
            $errorArray['error'] = 'operator || product, name  ';
            Flight::json($errorArray, 400);
        }
        $needPara = array();
        $optionalPara = array('operator', 'operation', 'startTime', 'endTime', 'product', 'name', 'hasRead');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $database = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_task_table'));
        $tableName = Conf::get('pkg_db_task_table');

        //拼凑出sql
        $keys = array();
        $sql = "select count(*) from $tableName where ";
        foreach ($data as $key => $value) {
            if (empty($value) && $value != "0") {
                continue;
            }
            switch ($key) {
                case 'operation':
                    $sql .= "op_type=:operation and ";
                    break;
                case 'startTime':
                    $sql .= "start_time >=:startTime and ";
                    break;
                case 'endTime':
                    $sql .= "end_time <=:endTime and ";
                    break;
                case 'hasRead':
                    $sql .= " hasRead=:hasRead and ";
                    break;
                default:
                    $sql .= "$key=:$key and ";
                    break;
            }
            $keys[$key] = $value;
        }
        $sql .= '1 ';

        // var_dump($sql);exit;
        $this->log->info('sql command');
        $this->log->info($sql);
        $dbRes = $database->executeSql($sql, $keys);
        if ($dbRes == false) {
            $this->log->info('empty data');
            Flight::json($errorArray, 200);
        }
        $count = $dbRes[0]['count(*)'];

        $errorArray = array('count'=>$count);
        Flight::json($errorArray, 200);
    }

    /**
     * [getTaskByOperator 根据操作者查询记录]
     * @return [type] [description]
     */
    public function getTaskByOperator()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array();
        //获取参数 检查
        $data = Flight::request()->query->getData();
        $this->log->info(json_encode($data));
        if (!isset($data['operator']) && !(isset($data['product']) && isset($data['name'])) ) {
            $errorArray['error'] = 'operator || product, name  ';
            Flight::json($errorArray, 400);
        }
        $needPara = array();
        $optionalPara = array('operator', 'operation', 'startTime', 'endTime', 'fromIndex', 'limit', 'product', 'name', 'hasRead');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $database = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_task_table'));
        $tableName = Conf::get('pkg_db_task_table');

        $keys = array();
        //拼凑出sql
        $sql = "select * from $tableName where ";
        foreach ($data as $key => $value) {
            if (empty($value) && $value != "0") {
                continue;
            }
            switch ($key) {
                case 'operation':
                    $sql .= "op_type=:operation and ";
                    break;
                case 'startTime':
                    $sql .= "start_time >=:startTime and ";
                    break;
                case 'endTime':
                    $sql .= "end_time <=:endTime and ";
                    break;
                case 'hasRead':
                    $sql .= " hasRead=:hasRead and ";
                    break;
                case 'fromIndex':
                case 'limit':
                    break;
                default:
                    $sql .= "$key=:$key and ";
                    break;
            }
            if ($key != 'fromIndex' && $key != 'limit') {
                $keys[$key] = $value;
            }
        }
        $sql .= '1 order by start_time desc';
        if (!empty($data['limit'])) {
            $this->log->info("add page limit");
            $sql .= " limit {$data['fromIndex']}, {$data['limit']} ";
        }
        $this->log->info('sql command');
        $this->log->info($sql);
        $dbRes = $database->executeSql($sql, $keys);
        if ($dbRes == false) {
            $this->log->info('empty data');
            Flight::json($errorArray, 200);
        }
        foreach ($dbRes as $key => $value) {
            $value['param'] = json_decode(($value['param']), true);
            $dbRes[$key] = $value;
        }
        $errorArray = $dbRes;
        Flight::json($errorArray, 200);
    }

    /**
     * [markTaskRead 标记任务已读]
     * @return [type] [description]
     */
    public function markTaskRead()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        //获取参数 检查
        $data = Flight::request()->data->getData();
        $this->log->info(json_encode($data));
        $needPara = array('taskIdList', 'hasRead');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $database = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_task_table'));
        $tableName = Conf::get('pkg_db_task_table');
        $hasRead = $data['hasRead'];
        $keys = array('hasRead'=>$data['hasRead']);
        $sql = "UPDATE $tableName SET hasRead=:hasRead WHERE task_id in (";
        $range = "";
        $count = 1;
        foreach ($data['taskIdList'] as $taskId) {
            $name = "task$count";
            $count ++;
            $range .= ":$name,";
            $keys[$name] = $taskId;
        }
        $range = rtrim($range, ',');
        $sql = $sql . $range . ")";
        $this->log->info($sql);
        $result = $database->executeSql($sql, $keys, false);
        if ($result) {
            Flight::json($errorArray, 200);
        } else {
            $errorArray['error'] = '数据修改错误';
            Flight::json($errorArray, 400);
        }

    }



    public function getUpdateFileList()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        //获取参数 检查
        $data = Flight::request()->query->getData();
        $this->log->info(json_encode($data));
        $needPara = array('product', 'name', 'fromVersion', 'toVersion');
        $error = $this->checkParameter($needPara, $data);
        $hostIp = Conf::get('file_manage_host');
        $hostName = Conf::get('file_manage_hostname');
        $url = $hostName . Conf::get('file_manage_mainurl') . Conf::get('file_manage_suburl_getfilelist');
        $magic = new MagicTool();
        $option = array(
            'ip'=>$hostIp,
            'method'=>'GET',
            'data'=>$data
            );
        $responseData = $magic->httpRequest($url, $option);
        $this->log->info($responseData);
        $responseDataArray = json_decode($responseData, true);
        if ($responseDataArray == null) {
            $errorArray['error'] = $responseData;
            Flight::json($errorArray, 400);
        } elseif (isset($responseData['error'])) {
            $errorArray = $responseDataArray;
            Flight::json($errorArray, 400);
        } else {
            $errorArray = $responseDataArray;
            Flight::json($errorArray, 200);
        }
    }

    /**
     * [startTask 发起任务内部函数]
     * @param  [type] $taskId [description]
     * @return [type]         [description]
     */
    public function startTask($taskId)
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $this->log->info(json_encode($taskId));
        //开源使用逻辑
        if (Conf::get('open')) {
            $this->log->info('open src logic');
            //开源逻辑
            $dir = __DIR__.'/../../pkgworker/bin/';
            $dir = realpath($dir);
            $phpPath = Conf::get('php_path');
            $runFile = $dir . '/runTask.php';
            $command = "$phpPath $runFile $taskId>>/dev/null 2>&1 &";
            $this->log->info($command);
            $shellRun = shell_exec($command);
            $this->log->info($shellRun);
        } else {
            $this->log->info('self use logic');
            $rabbitMq = new Amqp(Conf::get('rabbitmq_host'));
            $msg = json_encode(array('task_id'=>$taskId));
            $rabbitMq->send(Conf::get('exchange_name'), $msg);
            $this->log->info('rabbitmq msg sent');
        }
    }

    /**
     * [startTask 发起任务接口]
     * @param  [type] $taskId [任务id]
     * @return [type]         [description]
     */
    public function startTaskApi()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $data = Flight::request()->data->getData();
        $this->log->info(json_encode($data));
        $needPara = array('taskId');
        $error = $this->checkParameter($needPara, $data);
        $taskId = $data['taskId'];
        $this->startTask($taskId);
    }

    /**
     * [reportToLogSystem 上报变更日志]
     * @param  [type] $ipList      [description]
     * @param  [type] $object      [description]
     * @param  [type] $operator    [description]
     * @param  [type] $description [description]
     * @return [type]              [description]
     */
    public function reportToLogSystem($ipList, $object, $operator, $description)
    {
        if (Conf::get('open')) {
            return;
        }
        $this->log->info("START REPORT FUNCTION " . __FUNCTION__);
        $logInfo = array(
            'modifiedIps'=>implode(',', $ipList),
            'operator'=>$operator,
            'modifiedObj'=>$object,
            'modifyDesc'=>$description,
            'passiveSystem'=>'pkg',
            'logType'=>'',
            'startTime'=>time()
            );
        $this->log->info(json_encode($logInfo));
        $hostIp = Conf::get('report_host');
        $reportUrl = Conf::get('report_url');
        $magic = new MagicTool();
        $option = array(
            'ip'=>$hostIp,
            'method'=>'POST',
            'data'=>$logInfo
            );
        ///先不上报
        $reportResult = $magic->httpRequest($reportUrl, $option);
        $this->log->info($reportResult);
    }

    /**
     * [addTask 将任务信息加到数据库表中]
     * @param [array] $ipList         [ip列表]
     * @param [string] $operation      [install ..]
     * @param [string] $operator       [description]
     * @param [array] $parameterArray [description]
     */
    public function addTask($ipList, $operation, $operator, $parameterArray)
    {
        //无需加参数检查 都在调用处检查
        $result = true;
        $startTime = date('Y-m-d H:i:s');
        $database = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_task_table'));
        $taskId = uniqid();
        $toInsert = array(
            'task_id'=>$taskId,
            'op_type'=>$operation,
            'operator'=>$operator,
            'ip_list'=>implode(";", $ipList),
            'param'=>json_encode($parameterArray),
            'task_status'=>'wait',
            'success_num'=>0,
            'fail_num'=>0,
            'task_num'=>count($ipList),
            'product'=>$parameterArray['product'],
            'name'=>$parameterArray['name'],
            'start_time'=>$startTime);
        $dbRes = $database->insertValue($toInsert);
        if (!$dbRes) {
            $result = false;
        }
        foreach ($ipList as $ip) {
            $toInsert = array(
                'task_id'=>$taskId,
                'operate'=>$operation,
                'ip'=>$ip,
                'status'=>'wait',
                'start_time'=>$startTime);
            $dbRes = $database->insertValue($toInsert, Conf::get('pkg_db_task_result_table'));
            if (!$dbRes) {
                $result = false;
            }
        }
        return $taskId;
    }


}
