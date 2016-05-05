<?php
namespace pack\src;

use pack\src\CopyPackage;
use publicsrc\lib\Database;
use publicsrc\lib\Log;
use publicsrc\conf\Conf;
use publicsrc\src\ExecShell;
use publicsrc\src\MagicTool;
use publicsrc\src\Package;
use publicsrc\src\UserFavorite;
use publicsrc\src\Instance;
use publicsrc\src\PkgSvn;
use Flight;

class PackFunction
{
    private $pkgHome;
    private $pkgCodeHome;
    private $installCopyHome;
    public $errorMsg;
    private $log;
    private $shellRun;

    function __construct()
    {
        $this->log = new Log(__CLASS__);
        $this->pkgHome = Conf::get('package_home');
        $this->errorMsg = null;
        $this->fileMaxSize = intval(Conf::get('max_file_size'));
        $this->pkgCodeHome = Conf::get('package_framework');
    }

    /**
     * [checkParameter 检查参数]
     * @param  [type] $paraNameArray        [必填参数]
     * @param  [type] &$realParametersArray [具体传入参数]
     * @param  [type] $optionalPara         [可选参数]
     * @return [type]                       [description]
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
     * 格式化文件大小
     *
     * @param int $size
     * @param int $format
     * @return string
     */
    public function formatSize($size, $format = 2)
    {
        if (!is_numeric($size) || $size < 0) {
            return false;
        }
        if ($size < 1024) {
            return $size;
        } elseif ($size < 1048576) {
            return round($size / 1024, $format) . "K";
        } elseif ($size < 1073741824) {
            return round($size / 1048576, $format) . "M";
        } elseif ($size < 1099511627776) {
            return round($size / 1073741824, $format) . "G";
        } else {
            return round($size / 1099511627776, $format) . "T";
        }
    }

    /**
     * [uploadFile 上传文件到包目录]
     * @return [array] [code msg data]
     */
    public function uploadFile()
    {
        //获取post数据 调用
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $errorArray = array('error'=>'');
        //检查参数
        if (!isset($data['path']) || !isset($data['unCompress'])) {
            $errorArray['error'] = "missing parameter path or unCompress";
            Flight::json($errorArray, 400);
        }

        //检查目录
        $storePath = realpath($this->pkgHome.$data['path']);
        if (!is_dir($storePath)) {
            $errorArray['error'] = "path not exists:$storePath";
            Flight::json($errorArray, 404);
        }

        //默认权限644
        if (isset($data['chmod'])) {
            $filePermission = $data['chmod'];
        } else {
            $filePermission = "644";
        }
        //八进制生成
        eval("\$filePermission = 0$filePermission;");

        $errorFiles = array();
        foreach ($_FILES["ScriptFile"]["error"] as $key => $error) {
            if ($error == UPLOAD_ERR_OK) {
                //文件上传成功, 移动到包目录下
                $tmpName = $_FILES["ScriptFile"]["tmp_name"][$key];
                $fileName = $_FILES["ScriptFile"]["name"][$key];
                $srcCode = mb_detect_encoding($fileName);
                $fileName = iconv($srcCode, "utf-8", $fileName);
                $desFile = $storePath."/".basename($fileName);
                move_uploaded_file($tmpName, $desFile);
                chmod($desFile, $filePermission);
                $shellRun = new ExecShell('check_upload_file.sh', Conf::get('tool_operate'));
                $shellRun->run($desFile);
                $this->log->info('shell run result:' . json_encode($shellRun->getOutput()));
                $returnCode = $shellRun->rtCode();
                //检查文件无异常
                if ($returnCode == 0) {
                    //如需解压
                    if (($data['unCompress'] == "true") &&
                        ((preg_match('/\.tar\.gz$|\.tgz$|\.tar$/', basename($desFile))))) {
                        //tar的解压暂时不支持
                        $shellRun = new ExecShell('decompress.sh', Conf::get('tool_operate'));
                        $shellRun->run($desFile);
                    }
                } else {
                    //文件异常
                    $errorFiles[] = $desFile;
                }
            }
        }
        //含失败
        if (count($errorFiles) > 0) {
            $errorArray['error'] = "以下文件包含无效文件, 请删除. \n(无效文件:包含.svn文件, 管道文件) \n";
            $errorArray['error'] .= implode("\n", $errorFiles);
            Flight::json($errorArray, 400);
        }
        //成功
        Flight::json($errorArray, 201);
    }

    /**
     * [pullFile 拉取文件到包目录]
     * @return [type] [description]
     */
    public function pullFile()
    {
        //获取post数据 调用
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $errorArray = array('error'=>'');
        if (!isset($data['ip']) || !isset($data['dest']) || !isset($data['fileList'])) {
            $errorArray['error'] = "missing parameter ip, dest, fileList";
        } elseif (empty($data['ip']) || empty($data['dest']) || empty($data['fileList'])) {
            $errorArray['error'] = "empty parameter ip, dest, or fileList";
        }
        if (!empty($errorArray['error'])) {
            Flight::json($errorArray, 400);
        }

        //参数正确
        $ip = $data['ip'];
        $destPath = $this->pkgHome.$data['dest'];
        $fileListArr = explode(";", $data['fileList']);

        //检查目录
        $storePath = realpath($destPath);
        if (!is_dir($storePath)) {
            $errorArray['error'] = "path not exists:$storePath";
            Flight::json($errorArray, 404);
        }

        //格式
        foreach ($fileListArr as &$filePath) {
            $filePath = trim($filePath);
        }
        //跑脚本
        $srcPaths = join(' ', $fileListArr);
        $shellRun = new ExecShell('download.sh', Conf::get('tool_operate'));
        $shellRun->run($ip, $srcPaths, $destPath);
        $shellOutput = $shellRun->getOutput();
        $this->log->info('shell run result:' . json_encode($shellOutput));
        $shellRunCode = $shellRun->rtCode();
        if ($shellRunCode == 0) {
            //成功
            Flight::json($errorArray, 201);
        }
        else {
            $errorArray['error'] = implode(";", $shellOutput);
            Flight::json($errorArray, 400);
        }

    }

    /**
     * [editFile 编辑文件]
     * @return [type] [description]
     */
    public function getFileContent()
    {
        //获取post数据 调用
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->query->getData();
        $errorArray = array('error'=>'');
        $this->log->info('data:' . json_encode($data));
        //参数检查
        $paraNameArray = array('path');
        $optionalPara = array('home', 'charset');
        $this->checkParameter($paraNameArray, $data, $optionalPara);

        //提取
        $home = $data['home'];
        if (empty($home)) {
            $home = $this->pkgHome;
        }
        $this->log->info("home : $home");
        $inPkgPath = ltrim($data['path'], '/');
        $editFilePath = realpath("$home/$inPkgPath");
        $this->log->info("realpath : $editFilePath");
        $charSet = null;
        if (isset($data['charset'])) {
            $charSet = $data['charset'];
        }

        //路径不存在
        if ((!is_file($editFilePath) ) || (!file_exists($editFilePath))) {
            $errorArray['error'] = "file not exists:{$data['home']} {$data['path']}";
            Flight::json($errorArray, 404);
        }
        //文件大小规范
        $maxSize = $this->fileMaxSize;
        $editFileSize = filesize($editFilePath);
        if ($editFileSize > $maxSize) {
            $errorArray['error'] = "file too big to edit, please download";
            Flight::json($errorArray, 400);
        }
        //打开文件
        $openFile = fopen($editFilePath, 'r');
        $fileContent = fread($openFile, $maxSize);
        //文件编码
        $fileEncode = mb_detect_encoding($fileContent, array('UTF-8', 'CP936'));
        if ($fileEncode === 'CP936') {
            $fileEncode = 'GBK';
        }
        $fileEncode = strtolower($fileEncode);
        if ($charSet == null || empty($charSet)) {
            $fileContent = (@iconv('GBK', 'UTF-8//IGNORE', $fileContent));
        } elseif (strtoupper($charSet) != 'UTF-8') {
            $fileContent = (@iconv($charSet,"UTF-8//IGNORE", $fileContent));
        }
        fclose($openFile);
        //返回数据
        $resultArray = array(
            'path'=>$inPkgPath,
            'name'=>basename($editFilePath),
            'encoding'=>$fileEncode,
            'content'=>$fileContent);
        Flight::json($resultArray, 200);
    }

    /**
     * [saveFile 保存文件接口 失败则回滚]
     * @return [type] [description]
     */
    public function saveFile()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('path', 'content', 'charset');
        $this->checkParameter($needPara, $data);
        extract($data);
        $errorArray = array('error'=>'');
        $realPackagePath = $this->pkgHome . $path;
        $realPackagePath = realpath($realPackagePath);
        if ($realPackagePath == false || !file_exists($realPackagePath)) {
            $errorArray['error'] = '文件不存在';
            Flight::json($errorArray, 404);
        }
        $pathTmp = $realPackagePath . date("U") . rand(1000,100000);
        //备份
        if (!copy($realPackagePath, $pathTmp)) {
            $errorArray['error'] = '备份文件失败, 取消保存';
            Flight::json($errorArray, 400);
        }
        $fileEncode = strtoupper($charset);
        $content = str_replace("\r\n", "\n", $content);
        if ($fileEncode != 'UTF-8') {
            $content = iconv('UTF-8', $fileEncode . '//IGNORE', $content);
        }
        $writeResult = file_put_contents($realPackagePath, $content);
        //写失败
        if ($writeResult != strlen($content)) {
            $runRes1 = unlink($realPackagePath);
            $runRes2 = rename($pathTmp, $realPackagePath);
            if ($runRes1 && $runRes2) {
                $errorArray['error'] = '保存文件失败, 文件已回滚';
                Flight::json($errorArray, 400);
            } else {
                $errorArray['error'] = '原始文件已被清空,恢复失败.请手工处理,备份文件名为:'
                . basename($pathTmp);
                Flight::json($errorArray, 400);
            }
        }
        unlink($pathTmp);
        Flight::json($errorArray, 200);
    }

    /**
     * [removeDir 删除目录]
     * @param  [type] $dir [description]
     * @return [type]      [description]
     */
    public function removeDir($dir)
    {
        if (is_dir($dir))
        {
            if ($dp = opendir($dir))
            {
                while (($file=readdir($dp)) != false)
                {
                    $elem = $dir . '/' . $file;
                    if ($file == '.' || $file == '..')
                        continue;
                    if (is_dir($elem))
                        $this->removeDir($elem);
                    if(is_file($elem) || is_link($elem))
                        unlink($elem);
                }
                closedir($dp);
            }
            rmdir($dir);
        }
        return true;
    }

    /**
     * [operateFile 操作文件 rm mkdir chmod rename]
     * @return [type] [description]
     */
    public function operateFile()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('path', 'cmd');
        $optionalPara = array('mode', 'newName');
        $this->checkParameter($needPara, $data, $optionalPara);
        extract($data);
        $errorArray = array('error'=>'');
        $path = iconv('UTF-8', 'GBK', $path);
        $realFilePath = $this->pkgHome . '/'. $path;
        $this->log->info("file path $$realFilePath");
        switch ($cmd) {
            case 'rm':
                if (!file_exists($realFilePath)) {
                    $errorArray['error'] = '目录或文件不存在';
                    Flight::json($errorArray, 404);
                }
                if (is_dir($realFilePath)) {
                    // $deleteRes = rmdir($realFilePath);
                    $deleteRes = $this->removeDir($realFilePath);
                    if ($deleteRes != true) {
                        $errorArray['error'] = '删除目录失败';
                        Flight::json($errorArray, 400);
                    }
                } else {
                    $deleteRes = unlink($realFilePath);
                    if ($deleteRes != true) {
                        $errorArray['error'] = '删除文件失败';
                        Flight::json($errorArray, 400);
                    }
                }
                break;

            case 'mkdir':
                if (is_dir($realFilePath)) {
                    $errorArray['error'] = '目录已存在';
                    Flight::json($errorArray, 400);
                } else {
                    mkdir($realFilePath, 0755);
                    if (!is_dir($realFilePath)) {
                        $errorArray['error'] = '创建目录失败';
                        Flight::json($errorArray, 400);
                    }
                }
                break;

            case 'chmod':
                if (!file_exists($realFilePath)) {
                    $errorArray['error'] = '文件或目录不存在';
                    Flight::json($errorArray, 404);
                }
                if (empty($mode)) {
                    $errorArray['error'] = '缺少mode参数';
                    Flight::json($errorArray, 404);
                }
                //这里eval有问题, $mode比较危险, 用户可以自己随便填，另外，这里没必要用eval
                //eval("\$pope = 0$mode;");
                $pope = '0' . $mode;
                chmod($realFilePath, $pope);
                break;

            case 'rename':
                if (!file_exists($realFilePath)) {
                    $errorArray['error'] = '文件或目录不存在';
                    Flight::json($errorArray, 404);
                }
                if (empty($newName)) {
                    $errorArray['error'] = '缺少newName参数';
                    Flight::json($errorArray, 404);
                }
                $newName = iconv('GBK', 'UTF-8', $newName);
                $newName = dirname($realFilePath) . '/' . $newName;
                rename($realFilePath, $newName);
                break;
            default:
                $errorArray['error'] = '无该操作类型';
                Flight::json($errorArray, 400);
                break;
        }
        Flight::json($errorArray, 200);
    }

    /**
     * [getSvnStatus 获取路径的svn状态]
     * @return [type] [description]
     */
    public function getSvnStatus()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->query->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('path');
        $optionalPara = array();
        $this->checkParameter($needPara, $data, $optionalPara);
        extract($data);
        $errorArray = array('error'=>'');
        $path = $this->pkgHome . $path;
        if (realpath($path) == false || !file_exists($path)) {
            $errorArray['error'] = '路径不存在';
            Flight::json($errorArray, 404);
        }
        $resultArray = array();
        $path = iconv('UTF-8', 'GBK', $path);
        $path = realpath($path) . "/";
        $pkgSvnScript = new PkgSvn;
        $shellRunRes = $pkgSvnScript->status($path);
        $this->log->info('shell run code:' . $shellRunRes['code']);
        $this->log->info('shell run result:' . $shellRunRes['msg']);
        if ($shellRunRes['code'] != 0) {
            $errorArray['error'] = '获取svn状态出错' . $shellRunRes['msg'];
            Flight::json($errorArray, 400);
        }

        $resultArray = $shellRunRes['data'];
        foreach ($resultArray as $key => $value) {
            $pos = strpos($value['file'], $path);
            if ($pos !== false) {
                $resultArray[$key]['file'] = substr($resultArray[$key]['file'], strlen($path));
            }
        }
        Flight::json($resultArray, 200);
    }

    /**
     * [svnUpdate svn更新]
     * @return [type] [description]
     */
    public function svnUpdate()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('path');
        $optionalPara = array();
        $this->checkParameter($needPara, $data, $optionalPara);
        extract($data);
        $errorArray = array('error'=>'');
        $path = iconv('UTF-8', 'GBK', $path);
        $path = $this->pkgHome . '/' . $path;
        if (realpath($path) == false || !file_exists($path)) {
            $errorArray['error'] = '路径不存在';
            Flight::json($errorArray, 404);
        }
        //改成调用php脚本的
        $shellRun = new PkgSvn;
        $shellRunRes = $shellRun->update($path);
        $shellRunCode = $shellRunRes['code'];
        $shellOutput = $shellRunRes['msg'];
        $this->log->info('shell run result:' . $shellOutput);

        if ($shellRunCode != 0) {
            $errorArray['error'] = implode(';', $shellOutput) . ";errorCode $shellRunCode";
            Flight::json($errorArray, 400);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * [savePackageConfig 保存包配置与信息]
     * @return [type] [description]
     */
    public function savePackageConfig()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array(
            'path',
            'isShell',
            'confProduct',
            'confName',
            'confVersion',
            'confContent',
            'confUser',
            'confRemark',
            'confAuthor',
            'confFrameworkType'
            );
        $optionalPara = array('confStateless', 'confOS');
        $this->checkParameter($needPara, $data, $optionalPara);
        extract($data);
        $errorArray = array('error'=>'');
        $realPackagePath = $this->pkgHome . "/$path";
        $realPackagePath = realpath($realPackagePath);
        if ($realPackagePath == false || !file_exists($realPackagePath)) {
            $errorArray['error'] = "目录不存在 $path";
            Flight::json($errorArray, 404);
        }
        $confContent = str_replace('\r\n', '\n', $confContent);
        $confContent = iconv('UTF-8', 'GBK//IGNORE', $confContent);
        $xmlPath = $realPackagePath . '/init.xml';
        if (!file_exists($xmlPath)) {
            $errorArray['error'] = "目录不存在 $xmlPath";
            Flight::json($errorArray, 404);
        }
        $xmlTmp = $xmlPath . date('U') . rand(1000,100000);
        //备份
        $copyResult = copy($xmlPath, $xmlTmp);
        if (!$copyResult) {
            $errorArray['error'] = "备份文件失败, 取消保存";
            Flight::json($errorArray, 400);
        }
        $writeResult = file_put_contents($xmlPath, $confContent);
        //出错处理
        if ($writeResult != strlen($confContent)) {
            $runRes1 = unlink($xmlPath);
            $runRes2 = rename($xmlTmp, $xmlPath);
            if ($runRes1 && $runRes2) {
                $errorArray['error'] = '保存文件失败, 文件已回滚';
                Flight::json($errorArray, 400);
            } else {
                $errorArray['error'] = '原始文件已被清空,恢复失败.请手工处理,备份文件名为:'
                . basename($xmlTmp);
                Flight::json($errorArray, 400);
            }
        }
        unlink($xmlTmp);
        //处理完配置文件, 修改数据库信息
        $packageItem = new Package;
        $packageItem->packageInfo['product'] = $confProduct;
        $packageItem->packageInfo['name'] = $confName;
        $packageItem->packageInfo['version'] = $confVersion;
        $packageItem->packageInfo['path'] = "/$confProduct/$confName";
        $packageItem->packageInfo['user'] = $confUser;
        $packageItem->packageInfo['remark'] = $confRemark;
        $packageItem->packageInfo['author'] = $confAuthor;
        $packageItem->packageInfo['status'] = 200;
        if ($isShell != 'true') {
            $confFrameworkType = $confFrameworkType;
            $confStateless = ($confStateless === 'on') ? 'true':'false';
            $confOS = $confOS;
        } else {
            $confFrameworkType = $confStateless = $confOS = 'undefined';
        }
        $packageItem->packageInfo['frameworkType'] = $confFrameworkType;
        $packageItem->packageInfo['stateless'] = $confStateless;
        $packageItem->packageInfo['os'] = $confOS;
        //此时要注意svnVersion的信息也清空了.!!
        $saveResult = $packageItem->savePackage();
        if (!$saveResult) {
            $errorArray['error'] = '修改数据库信息失败';
            Flight::json($errorArray, 400);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * [exportPackageToCache 导出包到缓存]
     * @return [type] [description]
     */
    public function exportPackageToCache()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->query->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('product','name','version');
        $optionalPara = array();
        $this->checkParameter($needPara, $data, $optionalPara);
        extract($data);
        $errorArray = array('error'=>'');
        $packageItem = new Package;
        $packageInfo = $packageItem->getInfo($product, $name, $version);
        if (empty($packageInfo)) {
            $errorArray['error'] = '指定的包不存在';
            Flight::json($errorArray, 404);
        }
        $svnVersion = $packageInfo['svnVersion'];
        $svnPath = $packageInfo['path'];
        //开始导出到缓存

        //使用php脚本
        $shellRun = new PkgSvn;
        $shellRunRes = $shellRun->exportPkg($svnPath, $svnVersion, $version, $packageInfo['frameworkType']);
        $this->log->info('shell run result:' . json_encode($shellRunRes));
        $shellRunCode = $shellRunRes['code'];
        if ($shellRunCode != 0) {
            $errorArray['error'] = '导出包失败:' . $shellRunRes['msg'];
            Flight::json($errorArray, 400);
        }
        $exportPath = $shellRunRes['data']['exportPath'];

        $errorArray['exportPath'] = $exportPath;
        Flight::json($errorArray, 200);
    }

    /**
     * [copyPackage description]
     * @return [type] [description]
     */
    public function copyPackage()
    {
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('srcPath','srcVersion','targetPath');
        $optionalPara = array();
        $this->checkParameter($needPara, $data, $optionalPara);
        extract($data);
        $errorArray = array('error'=>'');
        $copyPackageItem = new CopyPackage;
        $copyResult = $copyPackageItem->copyMethod($srcPath, $srcVersion, $targetPath);
        if (strpos($copyResult['error'], '不存在') !== false) {
            $errorArray['error'] = $copyResult['error'];
            Flight::json($errorArray, 404);
            break;
        }
        switch ($copyResult['result']) {
            case true:
                Flight::json($errorArray, 200);
                break;
            case false:
                $errorArray['error'] = $copyResult['error'];
                Flight::json($errorArray, 400);
                break;
        }
    }

    /**
     * [createPackage 创建包]
     * @return [type] [description]
     */
    public function createPackage()
    {
        $errorArray = array('error'=>'');
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('product', 'name', 'isShell', 'frameworkType');
        $this->checkParameter($needPara, $data);
        $packageItem = new Package;
        $resultArray = $packageItem->getVersionList($data['name'], $data['product']);
        if (!empty($resultArray)) {
            $errorArray['error'] = '指定包名已存在, 如需修改请选择升级';
            Flight::json($errorArray, 400);
        }
        $existInfo = $this->checkPackageExistMethod($data['product'], $data['name']);
        if ($existInfo['exist'] == true) {
            $errorArray['error'] = $existInfo['msg'];
            Flight::json($errorArray, 400);
        }
        //初始化包
        extract($data);
        $realPackagePath = $this->pkgHome . "/$product/$name";
        if (!is_dir(dirname($realPackagePath))) {
            mkdir(dirname($realPackagePath));
        }
        if (!is_dir($realPackagePath)) {
            mkdir($realPackagePath);
        } else {
            $errorArray['error'] = '当前包已经被编辑过, 但未提交创建, 请留意';
            Flight::json($errorArray, 200);
        }
        if (($frameworkType !== 'plugin') && (!is_dir($realPackagePath . './bin'))) {
            mkdir($realPackagePath . '/bin');
        }
        if (!is_dir($realPackagePath . '/conf')) {
            mkdir($realPackagePath . '/conf');
        }
        if (!is_dir($realPackagePath . '/lib')) {
            mkdir($realPackagePath . '/lib');
        }
        if ($frameworkType == 'plugin') {
            $yamlPath = $path . '/conf/service.yaml';
            if (!is_file($yamlPath)) {
                $yamlSrc = $this->pkgCodeHome . "/template/service.yaml";
                copy($yamlSrc, $yamlPath);
            }
        }
        $realPackagePath = realpath($realPackagePath);
        //初始化init.xml
        $xmlPath = "$realPackagePath/init.xml";
        if (!is_file($xmlPath)) {
            $xmlSrc = $this->pkgCodeHome . "/template/init.xml";
            if ($isShell == 'true') {
                $xmlSrc = $this->pkgCodeHome . "/template/shell_init.xml";
            }
            if ($frameworkType == 'plugin') {
                $xmlSrc = $this->pkgCodeHome . "/template/plugin_init.xml";
            }
            $a = copy($xmlSrc, $xmlPath);
        }

        if (!is_file($xmlPath)) {
            $errorArray['error'] = "初始化xml失败:$xmlSrc to $xmlPath";
            Flight::json($errorArray, 400);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * [createPackage 创建新包接口 后台创建对应文件目录]
     * @return [type] [description]
     */
    public function submitCreatePackage()
    {
        //获取post数据 调用
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        $errorArray = array('error'=>'');
        //参数检查
        $needPara = array('confProduct','confName','confVersion', 'path', 'confUser', 'confRemark', 'confFrameworkType', 'confAuthor', 'isShell', 'confContent');
        $optionalPara = array();
        $this->checkParameter($needPara, $data, $optionalPara);
        $path = $data['path'];
        $pkgPath = $this->pkgHome.$path;
        $realPackagePath = realpath($pkgPath);
        if (empty($realPackagePath) || !is_dir($realPackagePath)) {
            $errorArray['error'] = "Package directory not exists";
            Flight::json($errorArray, 404);
        }

        $shellRun = new ExecShell('check_svn_dir.sh', Conf::get('tool_operate'));
        $shellRun->run($realPackagePath);
        $this->log->info('shell run result:' . json_encode($shellRun->getOutput()));
        $this->log->info('shell run code:' . $shellRun->rtCode());
        if ($shellRun->rtCode() != 0) {
            $errorArray['error'] = "目录中存在无效文件，如：管道文件、块设备文件、字符设备文件等";
            Flight::json($errorArray, 400);
        }

        $confContent = $data['confContent'];
        $confContent = str_replace("\r\n", "\n", $confContent);
        $confContent = iconv("UTF-8", "GBK//IGNORE", $confContent);
        $xmlPath = $realPackagePath."/init.xml";
        if (!file_exists($xmlPath)) {
            $errorArray['error'] = "配置文件init.xml不存在:$path/init.xml";
            Flight::json($errorArray, 404);
        }
        $xmlTmp = $xmlPath.date("U").rand(1000, 100000);
        if (!copy($xmlPath, $xmlTmp)) {
            $errorArray['error'] = "备份文件失败, 取消保存";
            Flight::json($errorArray, 400);
        }

        $copyLen = file_put_contents($xmlPath, $confContent);
        if ($copyLen != strlen($confContent)) {
            $rollBack = unlink($realPackagePath) && rename($xmlTmp, $xmlPath);
            if ($rollBack) {
                $errorArray['error'] = "保存文件失败,文件已回滚";
            } else {
                $errorArray['error'] = "原始文件已被清空,无法恢复.备份文件名为:" .basename($xmlTmp). ",请手工处理";
            }
            Flight::json($errorArray, 400);
        }
        unlink($xmlTmp);

        $packageItem = new Package;
        $packageItem->packageInfo['product'] = $data['confProduct'];
        $packageItem->packageInfo['name'] = $data['confName'];
        $packageItem->packageInfo['version'] = $data['confVersion'];
        $packageItem->packageInfo['path'] = "/{$data['confProduct']}/{$data['confName']}";
        $packageItem->packageInfo['user'] = $data['confUser'];
        $packageItem->packageInfo['remark'] = $data['confRemark'];
        $packageItem->packageInfo['author'] = $data['confAuthor'];
        $packageItem->packageInfo['status'] = 200;

        if ($data['isShell'] != 'true') {
            $packageItem->packageInfo['frameworkType'] = $data['confFrameworkType'];
        } else {
            $packageItem->packageInfo['frameworkType'] = "undefined";
        }

        $pkgSvnScript = new PkgSvn;
        $shellRunRes = $pkgSvnScript->buildPackage($path, $realPackagePath);
        $this->log->info(json_encode($shellRunRes));
        $shellRunCode = $shellRunRes['code'];
        $shellRunMsg = $shellRunRes['msg'];
        $this->log->info('shell run code:' . $shellRunCode);
        $this->log->info('shell run result:' . $shellRunMsg);
        if ($shellRunCode != 0 ) {
            $errorArray['error'] = '创建失败'.$shellRunMsg;
            Flight::json($errorArray, 400);
        } else {
            $svnVersion = $shellRunRes['data']['svnVersion'];
            $packageItem->packageInfo['status'] = 1;
            $packageItem->packageInfo['svnVersion'] = $svnVersion;
            $packageItem->savePackage();
        }
        //用户信息获取
        // $user = new UserFavorite;
        // $user->insert($data['userId'], $data['confProduct'], $data['confName']);
        Flight::json($errorArray, 201);
    }

    public function submitUpdateVersion()
    {
        //获取post数据 调用
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        // var_dump($data);
        $errorArray = array('error'=>'');
        //参数检查
        if (!isset($data['confProduct']) ||
            !isset($data['confName']) ||
            !isset($data['confVersion']) ||
            !isset($data['path']) ||
            !isset($data['confUser']) ||
            !isset($data['confRemark']) ||
            !isset($data['confFrameworkType']) ||
            !isset($data['confAuthor']) ||
            !isset($data['isShell']) ||
            !isset($data['confContent'])) {
            $errorArray['error'] = "missing parameters, please check. confProduct confName confVersion path...";
            Flight::json($errorArray, 400);
        }

        $confProduct = $data['confProduct'];
        $confName = $data['confName'];
        $confVersion = $data['confVersion'];
        $confContent = $data['confContent'];
        $confUser = $data['confUser'];
        $confRemark = $data['confRemark'];
        $confAuthor = $data['confAuthor'];
        $confFrameworkType = $data['confFrameworkType'];
        $isShell = $data['isShell'];
        $path = $data['path'];

        $packageItem = new Package;
        $packageInfo = $packageItem->getInfo($confProduct, $confName, $confVersion);
        $packagePath = $this->pkgHome.$data['path'];
        $realPackagePath = realpath($packagePath);
        if (empty($realPackagePath) || !is_dir($realPackagePath)) {
            $errorArray['error'] = "目录不存在:{$data['path']}";
            Flight::json($errorArray, 404);
        }
        //运行脚本提交
        $shellRun = new ExecShell('check_svn_dir.sh', Conf::get('tool_operate'));
        $shellRun->run($realPackagePath);
        $this->log->info('shell run result:' . json_encode($shellRun->getOutput()));
        $shellRunCode = $shellRun->rtCode();
        //提交出错
        if ($shellRunCode != 0) {
            $errorArray['error'] = "目录中存在无效文件，如：管道文件、块设备文件、字符设备文件等";
            Flight::json($errorArray, 400);
        }

        $confContent = str_replace("\r\n", "\n", $confContent);
        $confContent = iconv("UTF-8", "GBK//IGNORE", $confContent);
        $xmlPath = $realPackagePath."/init.xml";
        if (!file_exists($xmlPath)) {
            $errorArray['error'] = "配置文件init.xml不存在:$path/init.xml";
            Flight::json($errorArray, 404);
        }
        $xmlTmp = $xmlPath.date("U").rand(1000, 100000);
        if (!copy($xmlPath, $xmlTmp)) {
            $errorArray['error'] = "备份文件失败, 取消保存";
            Flight::json($errorArray, 400);
        }

        $copyLen = file_put_contents($xmlPath, $confContent);
        if ($copyLen != strlen($confContent)) {
            $rollBack = unlink($realPackagePath) && rename($xmlTmp, $xmlPath);
            if ($rollBack) {
                $errorArray['error'] = "保存文件失败,文件已回滚";
            } else {
                $errorArray['error'] = "原始文件已被清空,无法恢复.备份文件名为:" .basename($xmlTmp). ",请手工处理";
            }
            Flight::json($errorArray, 400);
        }
        unlink($xmlTmp);

        $packageItem->packageInfo['product'] = $confProduct;
        $packageItem->packageInfo['name'] = $confName;
        $packageItem->packageInfo['version'] = $confVersion;
        $packageItem->packageInfo['path'] = $packageItem->getSvnPath($confProduct, $confName);
        $packageItem->packageInfo['user'] = $confUser;
        $packageItem->packageInfo['remark'] = $confRemark;
        $packageItem->packageInfo['author'] = $confAuthor;
        $packageItem->packageInfo['status'] = 200;

        if ($isShell != 'true') {
            $packageItem->packageInfo['frameworkType'] = $confFrameworkType;
        } else {
            $packageItem->packageInfo['frameworkType'] = "undefined";
        }

        $pkgSvnScript = new PkgSvn;
        $shellRunRes = $pkgSvnScript->buildUpdatePackage($path, $realPackagePath);
        $shellRunCode = $shellRunRes['code'];
        $shellRunMsg = $shellRunRes['msg'];
        $this->log->info('shell run code:' . $shellRunCode);
        $this->log->info('shell run result:' . $shellRunMsg);
        if ($shellRunCode != 0 ) {
            $errorArray['error'] = '升级失败'.$shellRunMsg;
            Flight::json($errorArray, 400);
        }  else {
            $svnVersion = $shellRunRes['data']['svnVersion'];
            $packageItem->packageInfo['status'] = 1;
            $packageItem->packageInfo['svnVersion'] = $svnVersion;
            $packageItem->savePackage();
        }
        //用户信息获取没确认!
        // $user = new UserFavorite;
        // $user->insert($data['userId'], $confProduct, $confName);
        Flight::json($errorArray, 200);

    }

    /**
     * [submitVersion 撤销版本]
     * @return [type] [description]
     */
    public function deleteVersion()
    {
        //获取post数据 调用
        $data = Flight::request()->data->getData();
        $errorArray = array('error'=>'');
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->query->getData();
        $this->log->info('data:' . json_encode($data));

        //参数检查
        if (!isset($data['product']) ||
            !isset($data['name']) ||
            !isset($data['version'])) {
            $errorArray['error'] = "missing parameters:product, name, version, last";
            Flight::json($errorArray, 400);
        }

        $this->log->info(json_encode($data));
        $packageProduct = $data['product'];
        $packageName = $data['name'];
        $packageVersion = $data['version'];
        //是否还是要checkout svn路径先 也就是checkoutmethod
        $checkoutRes = $this->checkOutMethod($packageProduct, $packageName, $packageVersion);
        if ($checkoutRes['code'] != 0) {
            $errorArray['error'] = $checkoutRes['error'];
            $this->log->info("package checkout failed:".$checkoutRes['error']);
            Flight::json($errorArray, 400);
        }
        //to delete cache
        $hostIp = Conf::get('file_manage_host');
        $hostName = Conf::get('file_manage_hostname');
        $url = $hostName . Conf::get('file_manage_mainurl') . Conf::get('file_manage_suburl_deletecache');
        $requestData = array(
            'product'=>$packageProduct,
            'name'=>$packageName,
            'version'=>$packageVersion
            );
        $option = array(
            'ip'=>$hostIp,
            'method'=>'GET',
            'data'=>$data,
            'decode'=>true);
        $magic = new MagicTool();
        $deleteCacheResult = $magic->httpRequest($url, $option);
        //判断delete cache是否成功
        if ($deleteCacheResult == null ||
            (isset($deleteCacheResult['error']) &&
            !empty($deleteCacheResult['error']))) {
            $errorArray['error'] = 'delete cache step failed';
            if (isset($deleteCacheResult['error'])) {
                $errorArray['error'] .= $deleteCacheResult['error'];
            }
            $this->log->info("package delete cache failed:" . $errorArray['error']);
            Flight::json($errorArray, 400);
        }
        //获取数据库的记录
        $packageItem = new Package;
        $packageInfo = $packageItem->getInfo($packageProduct, $packageName, $packageVersion);
        //无该包
        if (!is_array($packageInfo) || count($packageInfo) == 0) {
            $errorArray['error'] = "指定的包不存在 product=$packageProduct, name=$packageName, version=$packageVersion";
            $this->log->info("package not exists");
            Flight::json($errorArray, 404);
        }
        $svnPath = $packageInfo['path'];
        $instanceItem = new Instance;
        $instanceList = $instanceItem->getInstance($svnPath, $packageVersion);
        if ($instanceList && (count($instanceList) > 0)) {
            $errorArray['error'] = "该版本存在已安装的实例,如需撤销,请先到实例管理中卸载或删除相关记录后重试";
            $this->log->info("package has instance using it");
            Flight::json($errorArray, 400);
        }

        $shellRun = new ExecShell('pkg_delete.sh', Conf::get('tool_operate'));
        $shellRun->run("pkg_path=$svnPath", "version=$packageVersion");
        $this->log->info('shell run result:' . json_encode($shellRun->getOutput()));
        $returnCode = $shellRun->rtCode();
        if ($returnCode != 0 || $shellRun->result(1) != "success") {
            $errorArray['error'] = "撤销失败:".$shellRun->result(2);
            $this->log->info("run script pkg_delete fail:".$shellRun->result(2));
            Flight::json($errorArray, 400);
        }
        $packageId = $packageInfo['packageId'];
        $svnVersion = $packageInfo['packageId'];
        // 是否包剩下的唯一版本
        $last = $packageItem->isLastPackage($svnPath);
        if ($last != true) {
            $this->log->info("not the only valid package");
            //撤销时 将包回到上一版本提交
            $backupPath = Conf::get('package_backup');
            $operateScriptDir = Conf::get('tool_operate');
            $svnScriptDir = Conf::get('tool_svn');
            $pkgTmpPath = Conf::get('package_tmp');
            $srcDir = $this->pkgHome;
            $srcPath = $srcDir.$svnPath;
            $tmpSvnExportPath = $pkgTmpPath.$svnPath;
            $previousPackage = $packageItem->getLastValidPackage($svnPath, $packageId, $packageVersion);
            if ($previousPackage != null) {
                //存在上个版本 可以继续
                $this->log->info("has a previous version, can do revert ");
                $previousPackageVersion = $previousPackage['version'];
                $previousPackageSvnVersion = $previousPackage['svnVersion'];
                if (intval($previousPackageSvnVersion) == 0) {
                    //上个版本svn版本号不正确 撤销失败
                    $errorArray['error'] = "撤销失败:上个版本svn版本号不正确";
                    $this->log->info("revert failed:svn tag incorrect");
                    Flight::json($errorArray, 400);
                }
                $pkgsvn = new PkgSvn;
                $svnRunRes = $pkgsvn->export($svnPath, $tmpSvnExportPath, $previousPackageSvnVersion);
                if ($svnRunRes['code'] != 0) {
                    $errorArray['error'] = "撤销失败:导出上个版本包失败:{$svnRunRes['msg']}";
                    $this->log->info("导出上个版本包失败");
                    Flight::json($errorArray, 400);
                }
                $commandList = array(
                    "cp $srcPath $backupPath -r -f ; echo $?",
                    "cd $srcPath ; rm -rf `ls|egrep -v '(.svn)'`; echo $?",
                    "cp $tmpSvnExportPath/* $srcPath/ -r ; echo $?",
                    // "cd $svnScriptDir; ./commit.sh $srcPath 'system update';echo $?"
                    );
                $stepPass = true;
                foreach ($commandList as $singleCommand) {
                    // $singleCommand = escapeshellcmd($singleCommand);
                    $runRes = shell_exec($singleCommand);
                    $this->log->info($singleCommand);
                    $this->log->info("result:".$runRes);
                    if (strpos($runRes, 'fail') !== false) {
                        $stepPass = false;
                    }
                    if (strpos($runRes, 'success') !== false) {
                        $this->log->info("step ok");
                    } elseif (strpos($runRes, '0') !== false) {
                        $this->log->info("step ok");
                    } else {
                        $this->log->info("step fail");
                        $stepPass = false;
                    }
                    if (!$stepPass) {
                        $errorArray['error'] = "撤销失败:恢复上个版本包步骤失败";
                        Flight::json($errorArray, 400);
                    }
                }
                //提交版本
                $svnRunRes = $pkgsvn->commit($srcPath, "go back to previous version");
                $this->log->info(json_encode($svnRunRes));
                if ($svnRunRes['code'] != 0) {
                    $errorArray['error'] = "撤销失败:提交为上个版本包失败:{$svnRunRes['msg']}";
                    $this->log->info("提交失败");
                    Flight::json($errorArray, 400);
                }
            }
        }

        $packageItem->setStatus($packageId, 4);//标记包状态作废
        //唯一的包版本被撤销 则删掉关联svn文件夹
	if (($last == true) && (!empty($svnPath))) {
	    $this->log->info("delete the svn dir too");
		$pkgSvnScript = new PkgSvn;
		$shellRunRes = $pkgSvnScript->allDelete($svnPath);
            $this->log->info('svn delete' . json_encode($shellRunRes));
            if ($shellRunRes['code']!= 0) {
                $errorArray['error'] = "删除包svn文件失败";
                $this->log->info("run script:pkg_all_delete.sh fail");
                Flight::json($errorArray, 400);
            }
        }
        $this->log->info("delete version completed");
        //成功
        Flight::json($errorArray, 200);
    }

    /**
     * [revertChange 撤销变更]
     * @return [type] [description]
     */
    public function revertChange()
    {
        $errorArray = array('error'=>'');
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        //参数检查
        if (!isset($data['path'])) {
            $errorArray['error'] = "missing parameters:path";
            Flight::json($errorArray, 400);
        }
        $packagePath = iconv('UTF-8', 'GBK', $data['path']);
        $realPackagePath = $this->pkgHome.$packagePath;
        $this->log->info('revert path' . $realPackagePath);
        //使用php脚本
        $shellRun = new PkgSvn;
        $shellRunRes = $shellRun->revert($realPackagePath);
        $this->log->info('shell run result:' . json_encode($shellRunRes));
        if ($shellRunRes['code'] != 0) {
            $errorArray['error'] = "撤销变更脚本执行失败, {$shellRunRes['msg']}";
            Flight::json($errorArray, 400);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * [checkOut 将包从svn服务器checkout到包库中 接口]
     * @return [type] [description]
     */
    public function checkOut()
    {
        $errorArray = array('error'=>'');
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->data->getData();
        $this->log->info('data:' . json_encode($data));
        //参数检查
        if (!isset($data['product']) ||
            !isset($data['name']) ||
            !isset($data['version'])) {
            $errorArray['error'] = "missing parameters:product, name, or version";
            Flight::json($errorArray, 400);
        }
        //调用checkout方法
        $result = $this->checkOutMethod($data['product'], $data['name'], $data['version']);
        if ($result['code'] == 0) {
            Flight::json($errorArray, 200);
        } elseif ($result['code'] == 2) {
            $errorArray['error'] = $result['error'];
            Flight::json($errorArray, 404);
        } else {
            $errorArray['error'] = $result['error'];
            Flight::json($errorArray, 400);
        }
    }

    /**
     * [checkOutMethod 导出/更新包库中的文件]
     * @param  [type] $product [产品]
     * @param  [type] $name    [包名]
     * @param  [type] $version [版本]
     * @return [array]          [code:0成功 1失败 error:失败信息 2找不到失败]
     */
    public function checkOutMethod($product, $name, $version)
    {
        $result = array('code'=>0, 'error'=>'');
        $packageItem = new Package();
        $packageInfo = $packageItem->getInfo($product, $name, $version);
        if (empty($packageInfo)) {
            $result['code'] = 2;
            $result['error'] = "找不到包记录";
            return $result;
        }
        $path = $packageInfo['path'];
        $packagePath = $this->pkgHome."/".$path;
        $packageSvnPath = $packagePath."/.svn";
        //跑脚本导出
        if (!is_dir($packagePath) || !is_dir($packageSvnPath)) {
            //use pkgsvn script
            $shellRun = new PkgSvn;
            $shellRunRes = $shellRun->checkout($path);
            if ($shellRunRes['code'] != 0) {
                $result['code'] = 1;
                $result['error'] = "导出包副本到workplace失败:$path" . $shellRunRes['msg'];
                return $result;
            }
        } else {
            $shellRun = new PkgSvn;
            $shellRunRes = $shellRun->update($path);
            if ($shellRunRes['code'] != 0) {
                $result['code'] = 1;
                $result['error'] = "更新包副本到workplace失败:$path;" . $shellRunRes['msg'];
                return $result;
            }
        }
        return $result;
    }


    /**
     * [listDirectory 列出目录下文件列表]
     * @return [type] [description]
     */
    public function listDirectory()
    {
        $errorArray = array();
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->query->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('path', 'base', 'type');
        $this->checkParameter($needPara, $data);
        $pkgHome = $this->pkgHome;
        //查看旧版本 路径不同
        if ($data['type'] == 'check') {
            $pkgHome = "";
        }
        $pathStr = $pkgHome . '/' . $data['base'] . '/' . $data['path'];
        $path = realpath($pkgHome . '/' . $data['base'] . '/' . $data['path']);
        //检查目录
        if (!is_dir($path)) {
            $errorArray['error'] = "目录不存在:$pathStr";
            Flight::json($errorArray, 404);
        }

        $fileList = scandir($path);
        $fileArray = array();
        $dirArray = array();
        foreach ($fileList as $index => $file) {
            //忽略显示
            if ($file == '.svn' || $file == '.') {
                continue;
            }
            if (empty($data['path']) && $file == '..') {
                continue;
            }
            $fileName = "$path/$file";
            $type = filetype($fileName);
            //显示文件类型
            if (!in_array($type, array('file', 'dir', 'link'))) {
                continue;
            }
            $fileModifyTime = date('Y-m-d H:i:s', filemtime($fileName));
            $fileMode = substr(sprintf('%o', fileperms($fileName)), -4);
            $nameStr = iconv('GB2312', 'UTF-8', $file);
            $size = 0;
            switch ($type) {
                case 'file':
                    $size = filesize($fileName);
                    $size = $this->formatSize($size) . " [$size bytes]";
                    $md5 = md5_file($fileName);
                    break;

                case 'link':
                    $fileModifyTime = readlink($fileName);
                    break;
            }
            $tmpArray = array(
                'name'=>$nameStr,
                'type'=>$type,
                'size'=>"$size",
                'mtime'=>$fileModifyTime,
                'mode'=>$fileMode);
            if ($type == 'dir') {
                $dirArray[] = $tmpArray;
            } elseif ($type == 'file') {
                $tmpArray['md5'] = $md5;
                $fileArray[] = $tmpArray;
            } else {
                $fileArray[] = $tmpArray;
            }
        }

        $resultArray = array_merge($fileArray, $dirArray);
        Flight::json($resultArray, 200);
    }

    /**
     * [downloadFile 下载文件]
     * @return [type] [description]
     */
    public function downloadFile()
    {
        $errorArray = array();
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->query->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('path');
        $optionalPara = array('home');
        $this->checkParameter($needPara, $data, $optionalPara);
        extract($data);
        if (empty($home)) {
            $home = $this->pkgHome;
        }
        $filePath = $home . $path;
        if (!file_exists($filePath)) {
            $errorArray['error'] = "路径不存在:$filePath";
            Flight::json($errorArray, 404);
        }
        $maxSize = $this->fileMaxSize;
        $fileSize = filesize($filePath);
        if ($fileSize > $maxSize) {
            $errorArray['error'] = "文件大小超出规范40M:$filePath";
            Flight::json($errorArray, 400);
        }
        $file = fopen($filePath, 'r');
        $content = fread($file, $maxSize);
        fclose($file);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        echo $content;
    }

    /**
     * [checkPackageExist 检查包是否存在]
     * @return [type] [description]
     */
    public function checkPackageExist()
    {
        $errorArray = array('error'=>'');
        $this->log->info("START FUNCTION " . __FUNCTION__ );
        $data = Flight::request()->query->getData();
        $this->log->info('data:' . json_encode($data));
        $needPara = array('product', 'name');
        $this->checkParameter($needPara, $data);
        $resultArray = $this->checkPackageExistMethod($data['product'], $data['name']);
        Flight::json($resultArray, 200);
    }

    public function checkPackageExistMethod($product, $name)
    {
        $result = array();
        $packageItem = new Package;
        $resultArray = $packageItem->getVersionList($name, $product);
        if (!empty($resultArray)) {
            $result = array('exist'=>true, 'msg'=>'指定包名已存在');
            return $result;
        }
        $directory = $this->pkgHome . "/$product/$name/.svn";
        if (is_dir($directory)) {
            $result = array('exist'=>true, 'msg'=>'指定包名已存在svn服务器');
            return $result;
        }
        $result = array('exist'=>false, 'msg'=>'包不存在');
        return $result;
    }

    public function test()
    {
    }

}


