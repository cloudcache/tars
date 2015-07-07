<?php
namespace filemanage\src;

use publicsrc\conf\Conf;
use publicsrc\conf\PrivateConf;
use publicsrc\src\Package;
use publicsrc\src\MagicTool;
use publicsrc\src\ExecShell;
use publicsrc\lib\Log;
use publicsrc\src\PkgSvn;
use Flight;

class FileManage
{
    private $pkgHome;
    private $pkgCodeHome;
    private $installCopyHome;
    public $errorMsg;
    private $log;

    function __construct()
    {
        $this->pkgHome = Conf::get('package_home');
        $this->errorMsg = null;
        $this->fileMaxSize = intval(Conf::get('max_file_size'));
        $this->pkgPath = Conf::get('package_path_export');
        $this->updatePkgPath = Conf::get('package_path_update');
        $this->log = new Log(__CLASS__);
        $this->open = Conf::get('open');
    }

    public function test()
    {
        // $a = Conf::get('pkg_db_name');
        // var_dump($a);exit;
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
     * [ignoreFile 创建忽略配置文件:创建"包升级变更时要忽略的变更文件"配置 update.conf.taskid]
     * @return [type] [description]
     */
    public function ignoreFile()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        //参数检查
        $needPara = array('product', 'name', 'fromVersion', 'toVersion', 'ignore', 'taskId');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $product = $data['product'];
        $name = $data['name'];
        $fromVersion = $data['fromVersion'];
        $toVersion = $data['toVersion'];
        $taskId = $data['taskId'];
        $ignore = json_decode($data['ignore'], true);
        $uploadPkgPath = $this->updatePkgPath;
        $confPath = $uploadPkgPath . "$product/$name/$fromVersion-$toVersion/update.conf";
        $this->log->info("conf file :$confPath");
        if (!file_exists($confPath)) {
            $this->log->info("conf file not exists");
            $errorArray['error'] = "conf file not exists";
            Flight::json($errorArray, 404);
        }
        $contents = file_get_contents($confPath);
        $ignoreList = array();
        foreach ($ignore as $file) {
            switch ($file['type']) {
                case 'add':
                    $ignoreList[] = "A {$file['path']}";
                    break;

                case 'delete':
                    $ignoreList[] = "D {$file['path']}";
                    break;

                case 'modify':
                    $ignoreList[] = "M {$file['path']}";
                    break;
            }
        }
        //清掉忽略的信息
        foreach ($ignoreList as $line) {
            $contents = str_replace($line . "\n", '', $contents);
        }
        file_put_contents($confPath . "." . $task_id, $contents);
        Flight::json($errorArray, 200);
    }

    /**
     * [deleteIgnoreFile 删除忽略配置文件:将update.conf.taskid 删除]
     * @return [type] [description]
     */
    public function deleteIgnoreFile()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        //参数检查
        $needPara = array('product', 'name', 'fromVersion', 'toVersion', 'taskId');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $product = $data['product'];
        $name = $data['name'];
        $fromVersion = $data['fromVersion'];
        $toVersion = $data['toVersion'];
        $taskId = $data['taskId'];
        $uploadPkgPath = $this->updatePkgPath;
        $confPath = $uploadPkgPath . "$product/$name/$fromVersion-$toVersion/update.conf.$taskId";
        if (!file_exists($confPath)) {
            $this->log->info("file already not exists");
            Flight::json($errorArray, 200);
        }
        unlink($confPath);
        Flight::json($errorArray, 200);
    }

    /**
     * [deleteCache 清除缓存]
     * @return [type] [description]
     */
    public function deleteCache()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        //参数检查
        $needPara = array('product', 'name', 'version');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $this->log->info('data:' . json_encode($data));
        $product = $data['product'];
        $name = $data['name'];
        $version = $data['version'];
        $packageItem = new Package();
        $packageInfo = $packageItem->getInfo($product, $name, $version);
        if ($packageInfo == null) {
            $errorArray['error'] = "package not exists";
            Flight::json($errorArray, 404);
        }
        //run scripts
        $svnPath = $packageInfo['path'];
        $shellRun = new ExecShell('pkg_delete.sh' , Conf::get('tool_operate'));
        $shellRun->run('version='.$version, 'pkg_path='.$svnPath);
        $this->log->info('shell run result:' . json_encode($shellRun->getOutput()));
        if ($shellRun->rtCode() != 0) {
            $errorArray['error'] = $shellRun->result(2);
            Flight::json($errorArray, 400);
        }
        //success
        Flight::json($errorArray, 200);
    }

    /**
     * [uploadPackage 上传包]
     * @return [type] [description]
     */
    public function uploadPackage()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        if ($this->open == true) {
            //开源不需要上传多地
            Flight::json($errorArray, 200);
        }
        //参数检查
        $needPara = array('product', 'name', 'version', 'toVersion', 'type', 'region');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $pkgPath = $this->pkgPath;
        $uploadPkgPath = $this->updatePkgPath;
        $product = $data['product'];
        $name = $data['name'];
        $version = $data['version'];
        $type = $data['type'];
        $toVersion = $data['toVersion'];
        $region = $data['region'];
        //深圳本地 不用调用
        if ($region == 'sz') {
            Flight::json($errorArray, 200);
        }
        //地域的一整套 上传到各地 参数
        $uploadConf = PrivateConf::$upload;
        $uploadCmdConf = PrivateConf::$uploadCmd;
        $destIp = $uploadConf[$region]['ip'];
        $destPort = $uploadConf[$region]['port'];
        $url = 'http://' . $uploadCmdConf[$region]['host'] . '/services/tools/mkdir.php';
        $option = array();
        $option['ip'] = $uploadCmdConf[$region]['ip'];
        //分类处理  pkg  upd_pkg
        $magic = new MagicTool();
        if ($type == 'pkg') {
            $fileName = "/$product/$name/$name-$version--install.tar.gz";
            $option['data'] = 'rsync_dir=pkg_home/pkg' . $fileName;
            $mkdirResult = $magic->httpRequest($url, $option);
            $this->log->info('mkdir request result:' . $mkdirResult);
            //跑命令拉取
            $command = "/usr/bin/rsync -a --port=$destPort $pkgPath$fileName " .
                "user_00@$destIp::pkg_home/pkg$fileName;echo '###'$?'###'";
            $this->log->info('command\n' . $command);
            $result = shell_exec($command);
            //结果匹配
            $matchCount = preg_match('/###(\d+)###/',$result,$matches);
            if (($matchCount > 0) && ($matches[1] == 0)) {
                Flight::json($errorArray, 200);
            } else {
                $errorArray['error'] = $result;
                Flight::json($errorArray, 400);
            }
        } elseif ($type == 'upd_pkg') {
            $fileName = "/$product/$name/$name" .
            "_update-$version-$toVersion-$name.tar.gz";
            $option['data'] = 'rsync_dir=pkg_home/upd_pkg' . $fileName;
            $mkdirResult = $magic->httpRequest($url, $option);
            $this->log->info('mkdir request result:' . $mkdirResult);
            $command = "/usr/bin/rsync -a --port=$destPort $uploadPkgPath$fileName " .
                "user_00@$destIp::pkg_home/upd_pkg$fileName;echo '###'$?'###'";
            $this->log->info('command\n' . $command);
            $result = shell_exec($command);
            //结果匹配
            $matchCount = preg_match('/###(\d+)###/',$result,$matches);
            if (($matchCount > 0) && ($matches[1] == 0)) {
                Flight::json($errorArray, 200);
            } else {
                $errorArray['error'] = $result;
                Flight::json($errorArray, 400);
            }
        }
        $errorArray['error'] = 'unknown error';
        Flight::json($errorArray, 400);
    }

    /**
     * [exportPackage 导出包]
     * @return [type] [description]
     */
    public function exportPackage()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        // get
        $data = Flight::request()->query->getData();
        //参数检查
        $needPara = array('product', 'name', 'version');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $product = $data['product'];
        $name = $data['name'];
        $version = $data['version'];
        $this->log->info("$product $name $version");
        $packageItem = new Package();
        $packageInfo = $packageItem->getInfo($product, $name, $version);
        if ($packageInfo == null) {
            $errorArray['error'] = "package not exists $product $name $version";
            $this->log->info("{$errorArray['error']}");
            Flight::json($errorArray, 404);
        }
        $svnPath = $packageInfo['path'];
        $svnVersion = $packageInfo['svnVersion'];
        if (empty($svnPath)) {
            $svnPath = "/$product/$name";
        }
        $frameworkType = $packageInfo['frameworkType'];
        $copyPath = "/";

        $shellRun = new PkgSvn;
        $shellRunRes = $shellRun->exportPkg($svnPath, $svnVersion, $version, $frameworkType);
        $this->log->info('shell run result:' . json_encode($shellRunRes));
        $shellRunCode = $shellRunRes['code'];
        if ($shellRunCode != 0) {
            $errorArray['error'] = "导出包失败:" . $shellRunRes['msg'];
            $this->log->info("{$errorArray['error']}");
            Flight::json($errorArray, 400);
        }
        //成功
        $this->log->info("export done");
        Flight::json($errorArray, 200);
    }

    /**
     * [exportUpdatePackage 导出升级包]
     * @return [type] [description]
     */
    public function exportUpdatePackage()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        // get
        $data = Flight::request()->query->getData();
        //参数检查
        $needPara = array('product', 'name', 'fromVersion', 'toVersion', 'tar');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $product = $data['product'];
        $name = $data['name'];
        $fromVersion = $data['fromVersion'];
        $toVersion = $data['toVersion'];
        $tar = $data['tar'];
        $this->log->info("$product $name $fromVersion $toVersion");
        $packageItem = new Package();
        $packageOldInfo = $packageItem->getInfo($product, $name, $fromVersion);
        $packageNewInfo = $packageItem->getInfo($product, $name, $toVersion);
        if ($packageOldInfo == null) {
            $errorArray['error'] = "package not exists $product $name $fromVersion";
            $this->log->info("{$errorArray['error']}");
            Flight::json($errorArray, 404);
        } elseif ($packageNewInfo == null) {
            $errorArray['error'] = "package not exists $product $name $toVersion";
            $this->log->info("{$errorArray['error']}");
            Flight::json($errorArray, 404);
        }
        //start to export
        $svnPath = $packageNewInfo['path'];
        $checkSum = crc32($svnPath . $fromVersion . $toVersion);
        $lockFileOpen = fopen("/tmp/$checkSum.lock", "a");
        flock($lockFileOpen, LOCK_EX);

        $shellRun = new PkgSvn;
        $shellRunRes = $shellRun->exportUpdatePkg($svnPath, $packageOldInfo['svnVersion'], $packageNewInfo['svnVersion'], $fromVersion, $toVersion, $packageNewInfo['frameworkType']);
        $this->log->info('shell run result:' . json_encode($shellRunRes));
        $shellRunCode = $shellRunRes['code'];
        if ($shellRunCode != 0) {
            $errorArray['error'] = "导出包失败:" . $shellRunRes['msg'];
            Flight::json($errorArray, 400);
        }

        Flight::json($errorArray, 200);
    }

    public function getUpdateFileList()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__);
        $errorArray = array('error'=>'');
        // get
        $data = Flight::request()->query->getData();
        //参数检查
        $needPara = array('product', 'name', 'fromVersion', 'toVersion');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);

        $product = $data['product'];
        $name = $data['name'];
        $fromVersion = $data['fromVersion'];
        $toVersion = $data['toVersion'];
        $this->log->info("$product $name $fromVersion $toVersion");
        $packageItem = new Package();
        //检查包合法性
        $packageOldInfo = $packageItem->getInfo($product, $name, $fromVersion);
        $packageNewInfo = $packageItem->getInfo($product, $name, $toVersion);
        if ($packageOldInfo == null) {
            $errorArray['error'] = "package not exists $product $name $fromVersion";
            $this->log->info("{$errorArray['error']}");
            Flight::json($errorArray, 404);
        } elseif ($packageNewInfo == null) {
            $errorArray['error'] = "package not exists $product $name $toVersion";
            $this->log->info("{$errorArray['error']}");
            Flight::json($errorArray, 404);
        }
        //start to generate list
        $uploadPkgPath = $this->updatePkgPath;
        $confPath = $uploadPkgPath . "/$product/$name/$name-update-$fromVersion-$toVersion/update.conf";
        $copyPath = $confPath;
        $svnPath = $packageNewInfo['path'];

        $shellRun = new PkgSvn;
        $shellRunRes = $shellRun->exportUpdatePkg($svnPath, $packageOldInfo['svnVersion'], $packageNewInfo['svnVersion'], $fromVersion, $toVersion, $packageNewInfo['frameworkType']);
        $this->log->info('shell run result:' . json_encode($shellRunRes));
        $shellRunCode = $shellRunRes['code'];
        if ($shellRunCode != 0) {
            $errorArray['error'] = "导出包失败:" . $shellRunRes['msg'];
            Flight::json($errorArray, 400);
        }
        $this->log->info($confPath);
        $confContent = file($confPath);

        $realConf = false;
        $fileList = array();
        //解析文本
        foreach ($confContent as $line) {
            if (strpos($line, '##End--') !== false) {
                $realConf = true;
                continue;
            }
            if ($realConf == false) {
                continue;
            }
            if ((strpos($line, 'A /') !== 0) &&
                (strpos($line, 'D /') !== 0) &&
                (strpos($line, 'M /') !== 0)) {
                var_dump($line . "!!!");
                continue;
            }
            $line = trim($line);
            $file = array();
            if (strpos($line, 'A /') === 0) {
                $file['type'] = 'add';
                var_dump($line);
            } elseif (strpos($line, 'D /') === 0) {
                $file['type'] = 'delete';
            } elseif (strpos($line, 'M /') === 0) {
                $file['type'] = 'modify';
            }
            $file['path'] = strstr($line, '/');
            $fileList[] = $file;
        }
        $errorArray = $fileList;
        Flight::json($errorArray, 200);
    }
}
