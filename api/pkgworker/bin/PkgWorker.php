<?php

class PkgWorker
{
    private $logger;
    private $magic;
    private $cmdUrl;
    private $cmdQueryUrl;
    private $open;
    private $useRabbitMq;
    private $database;
    private $timeout;
    private $requestTimeout;
    private $batchNum;
    private $batchInterval;

    function __construct()
    {
        require_once __DIR__ .'/../lib/log4php/Log.php';
        require_once __DIR__ .'/../lib/Database.php';
        require_once __DIR__ .'/../lib/MagicTool.php';
        require_once __DIR__ .'/../conf/Conf.php';
        require_once __DIR__ .'/../lib/ShellExec.php';
        $this->logger = new Log('PkgWorker');
        $this->magic = new MagicTool();
        $this->timeout = Conf::get('defaultTimeout');
        $this->requestTimeout = Conf::get('defaultRequestTimeout');
        $this->batchNum = Conf::get('defaultBatchNum');
        $this->batchInterval = Conf::get('defaultBatchInterval');
        $this->logger = $this->logger->getLogger();
        $this->cmdUrl = Conf::get('commandUrl');
        $this->cmdQueryUrl = Conf::get('commandQueryUrl');
        $this->open = Conf::get('open');
        $this->fileManageUrl = "http://" . Conf::get('fileManageHostName') . Conf::get('mainurl');
        $this->fileManageHost = Conf::get('fileManageHost');
        $this->database = new Database(
            Conf::get('mysqlServer'),
            Conf::get('mysqlPort'),
            Conf::get('mysqlUserName'),
            Conf::get('mysqlPassword'),
            Conf::get('mysqlDbName'),
            Conf::get('mysqlTaskTableName'));
        // $this->useRabbitMq = Conf::USERABBITMQ;
        if ($this->open == false) {
            require_once __DIR__ .'/../lib/Amqp.php';
        }
    }

    /**
     * [start 启动worker]
     * @return [type] [description]
     */
    public function start()
    {
        //selfuse logic
        if ($this->open == false) {
            $rabbit = new Amqp(Conf::get('rabbitHost'));
            $rabbit->recv(Conf::get('exchangeName'), Conf::get('queueName'), Conf::get('queueCallbackFunction'));
        } else {
            //直接收任务
            return;
        }
    }

    /**
     * [processMessage 处理消息]
     * @param  [type] $envelope [AMQPEnvelope]
     * @param  [type] $queue    [AMQPQueue]
     * @return [type]           [description]
     */
    public static function processMessage($envelope, $queue)
    {
        $msg = $envelope->getBody();
        $this->logger->info("start process message :".$msg);
        $msg = json_decode($msg,true);

        $worker = new PkgWorker();
        $worker->process($msg);
        $this->logger->info("end process message :".$msg);
        $queue->ack($envelope->getDeliveryTag());
    }

    public function test()
    {
        //优化 只要taskid则可拿到操作信息
        $msg = array('operate'=>'update','task_id'=>'545649a6c3366');
        $worker = new PkgWorker();
        $worker->process($msg);
    }

    //需不需要改成以taskid 则可
    public function process($msg)
    {
        $taskId = $msg['task_id'];
        $toSelect = array('task_id'=>$taskId);
        $dbRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskTableName'));
        if (empty($dbRes) || count($dbRes) > 1) {
            $this->logger->error("search result for task should not be empty or more than 1");
            return ;
        }
        $data = $dbRes[0];
        $operation = $data['op_type'];
        $this->logger->info('process message now ');
        switch($operation)
        {
            case 'install':
                $this->install($taskId);
                break;
            case 'update':
                $this->update($taskId);
                break;
            case 'rollback':
                $this->rollback($taskId);
                break;
            case 'restart':
            case 'stop':
            case 'start':
            case 'uninstall':
            case 'scan':
                $this->maintenance($taskId);
                break;
        }
    }

    /**
     * [ignoreDoneIp 传入任务ip, 除去已完成的ip]
     * @param  [type] $taskId [任务id]
     * @param  [type] $ipList [完整IP列表]
     * @return [type]         [description]
     */
    public function ignoreDoneIp($taskId, $ipList)
    {
        $doneIpList = array();
        $toSelect = array('task_id'=>$taskId, 'status'=>'ok');
        $doneRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskResultTableName'));
        if (!empty($doneRes)) {
            foreach ($doneRes as $index => $taskInfo) {
                $doneIpList[$taskInfo['ip']] = 1;
            }
        }
        $this->logger->info("finished ip list:" . json_encode($doneIpList));
        foreach ($ipList as $index => $ip) {
            if (array_key_exists($ip, $doneIpList)) {
                unset($ipList[$index]);
            }
        }
        $this->logger->info("unfinished ip list:" . json_encode($ipList));
        return $ipList;
    }

    /**
     * [install 安装包任务]
     * @param  [type] $taskId  [description]
     * @param  [type] $timeout [description]
     * @return [type]          [description]
     */
    public function install($taskId, $timeout=null)
    {
        if (empty($timeout)) {
            $timeout = $this->timeout;
        }
        $this->logger->info("START INSTALL:$taskId");
        $toSelect = array('task_id'=>$taskId);
        $dbRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskTableName'));
        if (empty($dbRes) || count($dbRes) > 1) {
            $this->logger->error("search result for task should not be empty or more than 1");
            // $this->setTaskFailed($ipList, $taskId, $msg, $status = 'fail');
            return false;
        }
        $data = $dbRes[0];
        $ipList = explode(";", $data['ip_list']);
        $param = json_decode(($data['param']), true);
        $product = $param['product'];
        $name = $param['name'];
        $version = $param['version'];
        $startOnComplete = $param['startAfterComplete'];
        $paramList = $param['paraList'];
        $renameList = $param['renameList'];
        $frameworkType= $param['frameworkType'];
        $batchNum = intval($param['batchNum']);
        $batchInterval= intval($param['batchInterval']);
        $para = $param;
        $para['start_on_complete'] = $param['startAfterComplete'];
        $para['param_list'] = $param['paraList'];
        $para['rename_list'] = $param['renameList'];

        if ($batchNum <= 0) {
            $batchNum = $this->batchNum;
        }
        if ($batchInterval <= 0) {
            $batchInterval = $this->batchInterval;
        }
        // $instanceName= $param['instanceName'];去掉
        //clear the finished ip
        $ipList = $this->ignoreDoneIp($taskId, $ipList);
        if (empty($ipList)) {
            //has finished all
            $this->logger->info("task has already done:$taskId");
            $this->updateTaskStatus($taskId);
            return true;
        }
        //export file from svn
        $exportRes = $this->checkCache($product, $name, $version);
        if ($exportRes == false) {
            $msg = "check cache failed";
            $this->logger->error($msg);
            $this->setTaskFailed($ipList, $taskId, $msg);
            return false;
        }
        //upload file to server
        $uploadRes = $this->uploadPkg($ipList, $product, $name, $version);
        if ($uploadRes == false) {
            $msg = "upload pkg failed";
            $this->logger->error($msg);
            $this->setTaskFailed($ipList, $taskId, $msg);
            return false;
        }
        $path = "/$product/$name/";
        $pkgStr = $path.'/'.$name.'-'.$version;
        $paraStr = "";
        //拼凑出 /product/name/name-1.0.X xx=xx xx=xx
        foreach ($para as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $paraStr .= " $key='$value'";
        }
        if (strtolower($frameworkType) == 'plugin') {
            $cmd = "./execute_install_spp.sh pkg_path='" . $pkgStr . "' " . $paraStr;
        } else {
            $cmd = "./execute_install.sh pkg_path='".$pkgStr."' ".$paraStr;
        }
        $this->logger->info("command:$cmd");
        // $runRes = $this->runTask($taskId, $ipList, $cmd, $timeout);
        //分批逻辑
        //devide ip group, and run task by groups
        $ipList = array_values(array_unique($ipList));
        if (count($ipList) > $batchNum) {
            $ipBatchList = array_chunk($ipList, $batchNum);
            foreach ($ipBatchList as $batchList) {
                $runRes = $this->runTask($taskId, $batchList, $cmd, $timeout);
                sleep($batchInterval);
            }
        } else {
            $runRes = $this->runTask($taskId, $ipList, $cmd, $timeout);
        }

        $this->logger->info('run result:' . json_encode($runRes));
        return true;
    }

    /**
     * [update 升级包任务 ]
     * @param  [type] $taskId  [description]
     * @param  [type] $timeout [description]
     * @return [type]          [description]
     */
    public function update($taskId, $timeout=null)
    {
        if (empty($timeout)) {
            $timeout = $this->timeout;
        }
        $this->logger->info("START UPDATE:$taskId");
        $toSelect = array('task_id'=>$taskId);
        $dbRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskTableName'));
        if (empty($dbRes) || count($dbRes) > 1) {
            $this->logger->error("search result for task should not be empty or more than 1");
            return false;
        }
        $data = $dbRes[0];
        $ipList = explode(";", $data['ip_list']);
        $taskParam = json_decode(($data['param']), true);
        $product = $taskParam['product'];
        $name = $taskParam['name'];
        $fromVersion = $taskParam['fromVersion'];
        $toVersion = $taskParam['toVersion'];
        //command parameters , collect parameters
        $param = array();
        $param['install_path']= $taskParam['installPath'];
        $param['stop'] = $taskParam['stopBeforeUpdate'];
        $param['force'] = $taskParam['forceUpdate'];
        $param['restart'] = $taskParam['restartAfterUpdate'];
        $param['graceful'] = $taskParam['hotRestart'];
        $param['update_appname']= $taskParam['updateAppName'];
        $param['update_port']= $taskParam['updatePort'];
        $param['update_start_stop']= $taskParam['updateStartStopScript'];
        $param['install_cp']= $taskParam['copyFileInstallOrCp'];
        $ignore =  $taskParam['ignoreFileList'];
        $batchNum = intval($taskParam['batchNum']);
        $batchInterval= intval($taskParam['batchInterval']);

        $ipList = $this->ignoreDoneIp($taskId, $ipList);
        if (empty($ipList)) {
            $this->logger->info("task has already done:$taskId");
            $this->updateTaskStatus($taskId, $ipList);
            return true;
        }

        $exportRes = $this->exportUpdatePkg($product, $name, $fromVersion, $toVersion);
        if (!$exportRes) {
            $msg = "call exportUpdatePkg api fail";
            $this->logger->error($msg);
            $this->setTaskFailed($ipList, $taskId, $msg);
            return false;
        }
        if (empty($ignore)) {
            $this->logger->info("ignore is empty");
            $ignoreRes = $this->ignoreFile(
                $product,
                $name,
                $fromVersion,
                $toVersion,
                $ignore,
                $taskId);
            $exportRes = $this->exportUpdatePkg($product, $name, $fromVersion, $toVersion, 'true');
            if (!$exportRes) {
                $msg = "call exportUpdatePkg api fail";
                $this->logger->error($msg);
                $this->setTaskFailed($ipList, $taskId, $msg);
                return false;
            }
            $param['ignore'] = 'true';
            $param['task_id'] = $taskId;
        }
        $path = "/$product/$name";
        $pkgPath = "$path/$fromVersion-$toVersion";
        $paraStr = "";
        foreach ($param as $key => $value) {
            $paraStr .= "$key='$value' ";
        }
        if ($batchNum <= 0) {
            $batchNum = 500;
        }
        if ($batchInterval <= 0) {
            $batchInterval = 5;
        }
        //devide ip group, and run task by groups
        $ipList = array_values(array_unique($ipList));
        if (count($ipList) > $batchNum) {
            $ipBatchList = array_chunk($ipList, $batchNum);
            foreach ($ipBatchList as $batchList) {
                $cmd = "./execute_update.sh pkg_path='$pkgPath' $paraStr";
                $this->logger->info("begin batch run task");
                $this->logger->info("task_id:$taskId ip_list:" . json_encode($batchList));
                $this->logger->info("command:$cmd");
                $runRes = $this->runTask($taskId, $batchList, $cmd, $timeout);
                $this->logger->info('run result:' . json_encode($runRes));
                sleep($batchInterval);
            }
        } else {
            $cmd = "./execute_update.sh pkg_path='$pkgPath' $paraStr";
            $this->logger->info("begin batch run task");
            $this->logger->info("task_id:$taskId ip_list:" . json_encode($ipList));
            $this->logger->info("command:$cmd");
            $runRes = $this->runTask($taskId, $ipList, $cmd, $timeout);
            $this->logger->info('run result:' . json_encode($runRes));
        }
        if (!empty($ignore)) {
            $runRes = $this->deleteIgnoreFile($product, $name, $fromVersion, $toVersion, $taskId);
        }
        return true;
    }

    /**
     * [rollback 回滚任务]
     * @param  [type] $taskId  [description]
     * @param  [type] $timeout [description]
     * @return [type]          [description]
     */
    public function rollback($taskId, $timeout=null)
    {
        if (empty($timeout)) {
            $timeout = $this->timeout;
        }
        $this->logger->info("START ROLLBACK:$taskId");
        $toSelect = array('task_id'=>$taskId);
        $dbRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskTableName'));
        if (empty($dbRes) || count($dbRes) > 1) {
            $this->logger->error("search result for task should not be empty or more than 1");
            return false;
        }
        $data = $dbRes[0];
        $ipList = explode(";", $data['ip_list']);
        $taskParam = json_decode(($data['param']), true);
        $product = $taskParam['product'];
        $name = $taskParam['name'];
        $currentVersion = $taskParam['currentVersion'];
        $installPath = $taskParam['installPath'];
        $ipList = $this->ignoreDoneIp($taskId, $ipList);
        if (empty($ipList)) {
            $this->logger->info("task has already done:$taskId");
            $this->updateTaskStatus($taskId, $ipList);
            return true;
        }
        $cmd = "./rollback_pkg.sh '$installPath' '$name' '$currentVersion'";
        $this->logger->info("command:$cmd");
        $runRes = $this->runTask($taskId, $ipList, $cmd, $timeout);
        $this->logger->info('rollback run result:' . json_encode($runRes));
        return ;
    }

    /**
     * [maintenance 日常操作:启动停止重启]
     * @param  [type] $taskId  [description]
     * @param  [type] $timeout [description]
     * @return [type]          [description]
     */
    public function maintenance($taskId, $timeout=null)
    {
        if (empty($timeout)) {
            $timeout = $this->timeout;
        }
        $this->logger->info("START maintenance:$taskId");
        $toSelect = array('task_id'=>$taskId);
        $dbRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskTableName'));
        if (empty($dbRes) || count($dbRes) > 1) {
            $this->logger->error("search result for task should not be empty or more than 1");
            return ;
        }
        $data = $dbRes[0];
        $ipList = explode(";", $data['ip_list']);
        $taskParam = json_decode(($data['param']), true);
        $installPath = $taskParam['installPath'];
        $packageUser = $taskParam['packageUser'];
        $frameworkType= $taskParam['frameworkType'];
        $operation = $taskParam['operation'];
        $restartGraceful= $taskParam['hotRestart'];
        $batchNum = intval($taskParam['batchNum']);
        $batchInterval= intval($taskParam['batchInterval']);
        $ipList = $this->ignoreDoneIp($taskId, $ipList);
        if (empty($ipList)) {
            $this->logger->info("task has already done:$taskId");
            $this->updateTaskStatus($taskId, $ipList);
            return ;
        }
        if (strtolower($frameworkType) == 'plugin') {
            if ($operation == 'uninstall') {
                $cmd = "./runinstall_pkg_spp.sh '$installPath'";
            } elseif ($restartGraceful == 'true') {
                $cmd = "./rmaintance.sh '$installPath' '$operation' operParentGraceful ";
            } else {
                $cmd = "./rmaintance.sh '$installPath' '$operation' operParent";
            }
        } else {
            $cmd = "./rmaintance.sh '$installPath' '$operation'";
        }
        $this->logger->info("command:$cmd");
        if ($batchNum <= 0) {
            $batchNum = $this->batchNum;
        }
        if ($batchInterval <= 0) {
            $batchInterval = $this->batchInterval;
        }
        $ipList = array_values(array_unique($ipList));
        if (count($ipList) > $batchNum) {
            $ipBatchList = array_chunk($ipList, $batchNum);
            foreach ($ipBatchList as $batchList) {
                $this->logger->info("batch task start");
                $this->logger->info("ip list:" . json_encode($batchList));
                $this->logger->info("batch task start");
                $runRes = $this->runTask($taskId, $batchList, $cmd, $timeout);
                $this->logger->info('batch run result:' . json_encode($runRes));
                sleep($batchInterval);
            }
        } else {
            $this->logger->info("task start");
            $this->logger->info("ip list:" . json_encode($ipList));
            $runRes = $this->runTask($taskId, $ipList, $cmd, $timeout);
            $this->logger->info('batch run result:' . json_encode($runRes));
        }
        return ;
    }

    /**
     * [runTask 运行任务]
     * @param  [type] $taskId  [description]
     * @param  [type] $ipList  [description]
     * @param  [type] $cmd     [description]
     * @param  [type] $timeout [description]
     * @return [array]          [ip:ok, failed, overtime]
     */
    public function runTask($taskId, $ipList, $cmd, $timeout=null)
    {
        $this->logger->info("START RUN TASK:$taskId");
        if (empty($timeout)) {
            $timeout = $this->timeout;
        }
        //执行命令
        $shellCmd = 'mkdir -p /tmp/pkg_tools_v3/;'
                    .'rsync -a #rsyncd_svr#::pkg_home/pkg_tools/ /tmp/pkg_tools_v3/;'
                    .'cd /tmp/pkg_tools_v3/;'
                    ."$cmd;"
                    .'cd scan_pkg;'
                    .'./scan_packages_info.sh >/dev/null 2>&1 & ';
        $this->logger->info("command:$shellCmd");
        $shellRun = new ShellExec();
        $taskList = $shellRun->runCmd($shellCmd, $ipList);
        $this->logger->info('TASK LIST:'.json_encode($taskList));
        //初始化任务状态 已启动
        foreach ($taskList as $shellTaskId => $taskInfo) {
            foreach ($taskInfo['ip_status'] as $ip => $info) {
                $status = 'started';
                $msg = 'task type:' . $taskInfo['type'] . ';'
                    . 'shell taskid:' . $shellTaskId;
                $this->logger->info("ip:$ip, msg:$msg, status:$status");
                $this->updateStatus($ip, $taskId, $status, $msg, $msg);
            }
        }
        $startTime = time();
        $ipStatus = array();
        $done = array();
        $overtime = false;
        while (true) {
            $useTime = time() - $startTime;
            if ($useTime >= $timeout) {
                $overtime = true;
                break;
            }
            //获取任务执行情况
            $taskList = $shellRun->getTaskInfo($taskList);
            $this->logger->info("taskList info" . json_encode($taskList));
            $finished = true;
            foreach ($taskList as $shellTaskId => $taskInfo) {
                if ($taskInfo['status'] != 'ok') {
                    $finished = false;
                }
                foreach ($taskInfo['ip_status'] as $ip => $info) {
                    if (array_key_exists($ip, $done) && $done[$ip] == true) {
                        continue;
                    }
                    if (strpos($info['status'], 'fail') !== false) {
                        //失败的
                        $done[$ip] = true;
                        $status = 'failed';
                        $msg = $info['msg'];
                        $this->logger->info("ip:$ip, taskid:$taskId, status:$status, msg:$msg");
                        $this->updateStatus($ip, $taskId, $status, $msg, $msg);
                    } elseif ($info['status'] == 'ok') {
                        //成功的则获取命令输出并更新到数据库
                        $done[$ip] = true;
                        //匹配成功的输出 两种:result 或 resultLine
                        $matchCount = preg_match_all('/result%%(\w+)%%(.*)%%/', $info['msg'], $matches);
                        if ($matchCount > 0) {
                            $result = end($matches[1]);
                            $msg = end($matches[2]);
                        }
                        if ($result == 'success') {
                            $status = 'ok';
                        } else {
                            $status = 'failed';
                        }
                        $matchCount = preg_match_all('/%%resultLine%%([^%]*)%%([^%]*)%%([^%]*)%%([^%]*)%%([^%]*)%%([^%]*)%%([^%]*)%%/',$info['msg'],$matches);
                        if ($matchCount > 0) {
                            $result = end($matches[6]);
                            $start = end($matches[7]);
                            if ($result == 'success') {
                                if (empty($start)) {
                                    $status = 'ok';
                                } else {
                                    $status = 'failed';
                                    $msg = $start;
                                }
                            } else {
                                $status = 'failed';
                            }
                        }
                        $this->logger->info("ip:$ip, taskid:$taskId, status:$status, msg:$msg");
                        $this->updateStatus($ip, $taskId, $status, $msg, $info['msg']);
                    }
                }
            }
            if ($finished == true) {
                break;
            }
            sleep(3);
        }
        $this->logger->info('task list:' . json_encode($taskList));
        $resultArray = array();
        //因为超时退出时, 将未结束的ip设置为fail
        if ($overtime == true) {
            $this->logger->info('overtime, set undone ip failed');
            foreach ($ipList as $ip) {
                $toSelect = array('task_id'=>$taskId, 'ip'=>$ip);
                $dbRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskResultTableName'));
                $status = 'ok';
                foreach ($dbRes as $index => $ipTaskInfo) {
                    if ($ipTaskInfo['status'] != 'ok' && $ipTaskInfo['status'] != 'failed') {
                        $ip = $ipTaskInfo['ip'];
                        $status = 'failed';
                        $msg = 'shell run timeout';
                        $resultArray[$ip] = 'overtime';
                        $this->logger->info("ip:$ip, taskid:$taskId, status:$status, msg:$msg");
                        $this->updateStatus($ip, $taskId, $status, $msg, $msg);
                    } else {
                        $resultArray[$ip] = $ipTaskInfo['status'];
                    }
                }
            }

        }
        $this->updateTaskStatus($taskId);
        return $resultArray;
    }

    /**
     * [ignoreFile 忽略文件]
     * @param  [type] $product     [description]
     * @param  [type] $name        [description]
     * @param  [type] $fromVersion [description]
     * @param  [type] $toVersion   [description]
     * @param  [type] $ignore      [description]
     * @param  [type] $taskId      [description]
     * @return [type]              [description]
     */
    public function ignoreFile($product, $name, $fromVersion, $toVersion, $ignore, $taskId)
    {
        $this->logger->info("IGNORE FILE");
        $url = $this->fileManageUrl . Conf::get('suburlIgnoreFile');
        $opt = array(
            'ip'=>$this->fileManageHost,
            'method'=>'GET',
            'timeout'=>$this->requestTimeout,
            'data'=>array(
                'product'=>$product,
                'name'=>$name,
                'fromVersion'=>$fromVersion,
                'toVersion'=>$toVersion,
                'ignore'=>json_encode($ignore),
                'taskId'=>$taskId));
        $this->logger->info("begin ignore /$product/$name-$fromVersion to $toVersion");
        $result = $this->magic->httpRequest($url, $opt);
        // var_dump($result);
        if ($result === false) {
            $this->logger->error("request error");
            return false;
        } else {
            $returnData = json_decode($result,true);
            if (empty($returnData['error'])) {
                $this->logger->info("end ignore process /$product/$name-$fromVersion to $toVersion");
                return true;
            } else {
                $this->logger->error("ignore error {$returnData['error']}");
                return false;
            }
        }
    }

    /**
     * [deleteIgnoreFile 删除忽略文件]
     * @param  [type] $product     [description]
     * @param  [type] $name        [description]
     * @param  [type] $fromVersion [description]
     * @param  [type] $toVersion   [description]
     * @param  [type] $taskId      [description]
     * @return [type]              [description]
     */
    public function deleteIgnoreFile($product, $name, $fromVersion, $toVersion, $taskId)
    {
        $this->logger->info("DELETE IGNORE FILE");
        $url = $this->fileManageUrl . Conf::get('suburlDeleteIgnore');
        $opt = array(
            'ip'=>$this->fileManageHost,
            'method'=>'GET',
            'timeout'=>$this->requestTimeout,
            'data'=>array(
                'product'=>$product,
                'name'=>$name,
                'fromVersion'=>$fromVersion,
                'toVersion'=>$toVersion,
                'taskId'=>$taskId));
        $this->logger->info("begin delete /$product/$name-$fromVersion to $toVersion");
        $result = $this->magic->httpRequest($url, $opt);
        if ($result === false) {
            $this->logger->error("request error");
            return false;
        } else {
            $returnData = json_decode($result,true);
            if (empty($returnData['error'])) {
                $this->logger->info("end delete process /$product/$name-$fromVersion to $toVersion");
                return true;
            } else {
                $this->logger->error("delete error {$returnData['error']}");
                return false;
            }
        }
    }

    /**
     * [exportUpdatePkg 导出升级包文件]
     * @param  [type] $product     [description]
     * @param  [type] $name        [description]
     * @param  [type] $fromVersion [description]
     * @param  [type] $toVersion   [description]
     * @param  string $tar         [description]
     * @return [bool]              [true false]
     */
    public function exportUpdatePkg($product, $name, $fromVersion, $toVersion, $tar='false')
    {
        $this->logger->info("EXPORT UPDATE PKG");
        $url = $this->fileManageUrl . Conf::get('suburlExport');
        $opt = array(
            'ip'=>$this->fileManageHost,
            'method'=>'GET',
            'timeout'=>$this->requestTimeout,
            'data'=>array(
                'product'=>$product,
                'name'=>$name,
                'fromVersion'=>$fromVersion,
                'toVersion'=>$toVersion,
                'tar'=>$tar));
        $this->logger->info("begin export update pkg /$product/$name-$fromVersion to $toVersion");
        $result = $this->magic->httpRequest($url, $opt);
        if ($result === false) {
            $this->logger->error("export update pkg request error");
            return false;
        } else {
            $returnData = json_decode($result,true);
            if (empty($returnData['error'])) {
                $this->logger->info("end export update pkg /$product/$name-$version");
                return true;
            } else {
                $this->logger->error("export update pkg error {$returnData['error']}");
                return false;
            }
        }

    }

    /**
     * [uploadPkg 上传包到下发的中心服务器]
     * @param  [type] $ipList    [description]
     * @param  [type] $product   [description]
     * @param  [type] $name      [description]
     * @param  [type] $version   [description]
     * @param  string $type      [description]
     * @param  string $toVersion [description]
     * @return [bool]            [true false]
     */
    public function uploadPkg($ipList, $product, $name, $version, $type='pkg', $toVersion='')
    {
        $this->logger->info("UPLOAD PKG");
        $url = $this->fileManageUrl . Conf::get('suburlUploadPkg');
        $opt = array(
            'ip'=>$this->fileManageHost,
            'method'=>'POST',
            'timeout'=>$this->requestTimeout,
            'data'=>array(
                'product'=>$product,
                'name'=>$name,
                'version'=>$version,
                'toVersion'=>$toVersion,
                'type'=>$type)
            );
        $this->logger->info("begin upload /$product/$name-$version");
        $result = $this->magic->httpRequest($url, $opt);
        if ($result === false) {
            $this->logger->error("upload request error");
            return false;
        } else {
            $returnData = json_decode($result,true);
            if (empty($returnData['error'])) {
                $this->logger->info("end upload /$product/$name-$version");
                return true;
            } else {
                $this->logger->error("upload error {$returnData['error']}");
                return false;
            }
        }
    }

    /**
     * [checkCache 导出包操作]
     * @param  [type] $product [description]
     * @param  [type] $name    [description]
     * @param  [type] $version [description]
     * @return [bool]          [true成功 false失败]
     */
    public function checkCache($product, $name, $version)
    {
        $this->logger->info("CHECK CACHE");
        $url = $this->fileManageUrl . Conf::get('suburlCheckCache');
        $opt = array(
            'ip'=>$this->fileManageHost,
            'method'=>'GET',
            'timeout'=>$this->requestTimeout,
            'data'=>array(
                'product'=>$product,
                'name'=>$name,
                'version'=>$version));
        $this->logger->info("begin checkcache /$product/$name-$version");
        $result = $this->magic->httpRequest($url, $opt);
        if ($result === false) {
            $this->logger->error("check cache request error");
            return false;
        } else {
            $returnData = json_decode($result,true);
            if (empty($returnData['error'])) {
                $this->logger->info("end checkcache /$product/$name-$version");
                return true;
            } else {
                $this->logger->error("check cache error {$returnData['error']}");
                return false;
            }
        }
    }

    /**
     * [updateStatus 更新单个ip的执行结果]
     * @param  [type] $ip       [description]
     * @param  [type] $taskId   [description]
     * @param  [type] $status   [description]
     * @param  [type] $msg      [description]
     * @param  [type] $taskInfo [description]
     * @return [type]           [description]
     */
    public function updateStatus($ip, $taskId, $status, $msg, $taskInfo)
    {
        $endTime = date("Y-m-d H:i:s");
        if (strpos($msg, '同名包已安装') !== false) {

        }
        $keyArray = array('ip'=>$ip, 'task_id'=>$taskId);
        $task = $this->database->selectValue($keyArray, Conf::get('mysqlTaskResultTableName'));
        $this->logger->info("get task ip result : $ip $taskId");
        $this->logger->info(json_encode($task));
        if (empty($task)) {
            $this->logger->error("task result empty");
        }
        $taskTable = Conf::get('mysqlTaskTableName');
        $resultTaskTable = Conf::get('mysqlTaskResultTableName');

        $startTime = $task[0]['start_time'];
        $useTime = strtotime($endTime) - strtotime($startTime);
        $toUpdate = array(
            'status'=>$status,
            'error'=>addslashes($msg),
            'end_time'=>$endTime,
            'used_time'=>$useTime,
            'task_info'=>addslashes($taskInfo));
        $updateRes = $this->database->updateValue($keyArray, $toUpdate, Conf::get('mysqlTaskResultTableName'));
        //update whole task status also
        $sql = null;
        switch ($status) {
            case 'failed':
                $sql = "UPDATE $taskTable SET `fail_num`=(SELECT COUNT(*) FROM `$resultTaskTable` WHERE `status` ='failed' AND `task_id`=:taskId1 ) WHERE `task_id`=:taskId2 ";
                break;
            case 'ok':
                $sql = "UPDATE $taskTable SET `success_num`=(SELECT COUNT(*) FROM `$resultTaskTable` WHERE `status` ='ok' AND `task_id`=:taskId1 ) WHERE `task_id`=:taskId2 ";
                break;
        }
        $keys = array('taskId1'=>$taskId, 'taskId2'=>$taskId);
        if (!empty($sql)) {
            $taskModified = $this->database->executeSql($sql, $keys, false) == true ? 'true':'false';
            $this->logger->info("UPDATE TASK $status $taskModified");
        }
        return $updateRes;
    }

    /**
     * [updateTaskStatus 更新任务状态]
     * @param  [type] $taskId [任务id]
     * @return [type]         [description]
     */
    public function updateTaskStatus($taskId)
    {
        $this->logger->info("UPDATE TASK INFO");
        $toSelect = array('task_id'=>$taskId);
        $dbRes = $this->database->selectValue($toSelect, Conf::get('mysqlTaskResultTableName'));
        $allStatus = 'ok';
        // $okNum = 0;
        foreach ($dbRes as $index => $ipTaskInfo) {
            if (strpos($ipTaskInfo['status'], 'fail') !== false) {
                $allStatus = 'failed';
                break;
            }
            
            if ($ipTaskInfo['status'] == 'started' || $ipTaskInfo['status'] == 'wait') {
                $allStatus = 'wait';
            }

            // if ($ipTaskInfo['status'] == 'ok') {
            //     $okNum ++;
            // }
        }
        //修改任务状态
        $taskTable = Conf::get('mysqlTaskTableName');
        $resultTaskTable = Conf::get('mysqlTaskResultTableName');
        $sql1 = "UPDATE $taskTable SET `fail_num`=(SELECT COUNT(*) FROM `$resultTaskTable` WHERE `status` ='failed' AND `task_id`=:taskId1 ) WHERE `task_id`=:taskId2 ";
        $sql2 = "UPDATE $taskTable SET `success_num`=(SELECT COUNT(*) FROM `$resultTaskTable` WHERE `status` ='ok' AND `task_id`=:taskId1 ) WHERE `task_id`=:taskId2 ";
        $keys = array('taskId1'=>$taskId, 'taskId2'=>$taskId);
        $taskModified = $this->database->executeSql($sql1, $keys, false) == true ? 'true':'false';
        $taskModified = $this->database->executeSql($sql2, $keys, false) == true ? 'true':'false';
        //
        $taskInfo = $this->database->selectValue($toSelect, Conf::get('mysqlTaskTableName'));
        $startTime = $taskInfo[0]['start_time'];
        $endTime = date('Y-m-d H:i:s');
        $useTime = strtotime($endTime) - strtotime($startTime);
        $toUpdate = array('task_status'=>$allStatus, 'end_time'=>$endTime, 'used_time'=>$useTime);
        $keyArray = array('task_id'=>$taskId);
        $updateRes = $this->database->updateValue($keyArray, $toUpdate, Conf::get('mysqlTaskTableName'));
        $this->logger->info("update result:" . strval($updateRes));
        return $updateRes;
    }

    /**
     * [setTaskFailed 设置任务失败]
     * @param [type] $ipList   [description]
     * @param [type] $taskId   [description]
     * @param [type] $msg      [description]
     * @param [type] $taskInfo [description]
     * @param string $status   [description]
     */
    public function setTaskFailed($ipList, $taskId, $msg, $status = 'failed')
    {
        $success = true;
        $taskInfo = $msg;
        foreach ($ipList as $ip) {
            $updateRes = $this->updateStatus($ip, $taskId, $status, $msg, $taskInfo);
            if(!$updateRes) {
                $success = false;
            }
        }
        $updateRes = $this->updateTaskStatus($taskId);
        if(!$updateRes) {
            $success = false;
        }
        return $updateRes;
    }
}




