<?php
class ShellExec
{
    private $timeout;
    public function __construct()
    {
        require_once __DIR__ .'/MagicTool.php';
        require_once __DIR__ .'/../conf/Conf.php';
        require_once __DIR__ .'/log4php/Log.php';
        $this->magic = new MagicTool();
        $this->commandIp = Conf::get('commandIp');
        $this->rsyncServer = Conf::get('rsyncServer');
        $this->cmdUrl = Conf::get('commandUrl');
        $this->cmdQueryUrl = Conf::get('commandQueryUrl');
        $this->timeout = Conf::get('defaultTimeout');
        // $this->logger = new Log('ShellExec');
        $this->logger = new Log(__CLASS__);
        $this->logger = $this->logger->getLogger();
    }

    /**
     * [runCmd 返回任务列表]
     * @param  [type] $shell   [description]
     * @param  [type] $ipList  [description]
     * @param  [type] $timeout [description]
     * @return [type]          [description]
     */
    public function runCmd($shell, $ipList, $timeout = null)
    {
        if(empty($ipList))
        {
             return array();
        }
        $this->logger->info("run command: $shell, ip:" . json_encode($ipList));
        //直接执行 //非cae机器，
        $this->logger->info("rsync server: " . $this->rsyncServer);
        $cmdShell = preg_replace('/#rsyncd_svr#/', $this->rsyncServer, $shell);
        $this->logger->info("command used: " . $cmdShell);
        $cmdUrl = $this->cmdUrl;
        $hostIp = $this->commandIp;
        $para = array(
            'dst_ips'=>implode(';',$ipList),
            'input_cmd'=>$cmdShell,
            'user_name'=>'root'
            );
        $requestResult = $this->magic->httpRequest($cmdUrl, array(
                'method'=>'POST',
                'data'=>$para,
                'ip'=>$hostIp)
            );
        $this->logger->info("rum command response: $requestResult");
        $result = json_decode($requestResult,true);
        $taskList = array();
        $shellTaskId = $result['data']['task_id'];
        $task = array(
            'shell_task_id'=>$shellTaskId,
            'type'=>'ssh',
            'status'=>'run'
            );
        $ipStatusList = array();
        foreach($ipList as $ip)
        {
            $ipStatus['status'] = 'run';
            $ipStatus['msg'] = '';
            $ipStatus['ip'] = $ip;
            $ipStatusList[$ip] = $ipStatus;
        }
        $task['ip_status'] = $ipStatusList;
        $taskList[$shellTaskId] = $task;
        /*
        {
            "xx":{
                "shell_task_id":"xx",
                "type":"ssh",
                "status":"run"
                "ip_status":{
                    "ip1":{
                        "status":"run",
                        "msg":"",
                        "ip":"ip1"
                    }
                }
            }
        }
         */
        return $taskList;
    }

    public function getTaskInfo($taskList)
    {
        /*
        {
            "xx":{
                "shell_task_id":"xx",
                "type":"ssh",
                "status":"run"
                "ip_status":{
                    "ip1":{
                        "status":"run",
                        "msg":"",
                        "ip":"ip1"
                    }
                }
            }
        }
         */
        $ipStatus = array();
        $this->logger->info("rum command query:" . json_encode($taskList));
        foreach($taskList as $shellTaskId => $task)
        {
            //任务已全部结束
            if($task['status'] == 'ok')
            {
                continue;
            }
            //ssh2 shell通道
            $cmdQueryUrl = $this->cmdQueryUrl;
            $hostIp = $this->commandIp;
            $para = array('task_id' => $task['shell_task_id']);
            $requestResult = $this->magic->httpRequest($cmdQueryUrl,
                array(
                    'method'=>'POST',
                    'data'=>$para,
                    'ip'=>$hostIp)
                );
            $this->logger->info("rum command query result:$requestResult");
            $queryResult = json_decode($requestResult, true);
            /*
            {
                "code":0
                "data":{
                    "finish_all":true,
                    "result":{
                        "ip1":{
                            "task_status":"xx",
                            "msg":"xx"
                        }
                        "ip2":{
                            "task_status":"xx",
                            "msg":"xx"
                        }
                    }
                }
            }
             */
            //直接改写传进来的值$taskList 更新
            if($queryResult['data']['finish_all'] == true)
            {
                $taskList[$shellTaskId]['status'] = 'ok';
            }
            else
            {
                $taskList[$shellTaskId]['status'] = 'run';
            }
            //以下什么逻辑??!
            if($queryResult['code'] != 0)
            {
                $this->logger->error("return code not valid");
                return -1;
            }
            $ipStatusList = array();
            if (!isset($queryResult['data']['result']) ||
                !is_array($queryResult['data']['result'])) {
                $this->logger->error("return data not array or key not set");
                $this->logger->error(json_encode($queryResult));
                // return -1;
            }
            foreach($queryResult['data']['result'] as $ip => $result)
            {
                if($result['task_status'] == 'suc')
                {
                    $status ='ok';
                }
                else if($result['task_status'] == 'fail')
                {

                    $status ='fail';
                }
                else
                {
                    $status = 'run';
                }
                $ipStatus['status'] = $status;
                $ipStatus['msg'] = $result['result'];
                $ipStatus['ip'] = $ip;
                $ipStatusList[$ip] = $ipStatus;
            }
            $taskList[$shellTaskId]['ip_status'] = $ipStatusList;
        }
        /*
        返回
        {
            "xx":{
                "shell_task_id":"xx",
                "type":"ssh",
                "status":"run"
                "ip_status":{
                    "ip1":{
                        "status":"run",
                        "msg":"",
                        "ip":"ip1"
                    }
                }
            }
        }
         */
        return $taskList;
    }
}
