<?php
namespace pack\src;

use publicsrc\lib\Database;
use publicsrc\lib\Log;
use publicsrc\conf\Conf;
use publicsrc\src\ExecShell;
use publicsrc\src\MagicTool;
use publicsrc\src\Package;
use publicsrc\src\UserFavorite;
use publicsrc\src\Instance;
use Flight;

class CopyPackage
{
    public $database;
    private $pkgHome;
    private $pkgCodeHome;
    private $installCopyHome;
    private $svnBase;
    private $svnBaseTmp;
    private $svnServer;
    private $logger;

    function __construct()
    {
        $this->pkgHome = Conf::PKG_HOME;
        $this->pkgCodeHome = Conf::PKG_CODE_HOME;
        $this->installCopyHome = Conf::INSTALL_COPY_HOME;
        $this->svnBase = Conf::SVN_BASE;
        $this->svnBaseTmp = Conf::SVN_BASE_TMP;
        $this->svnServer = Conf::SVN_SERVER;
        $this->logger = new Log(__CLASS__);
    }

    public function log($msg)
    {
        $this->logger->info($msg);
    }

    /**
     * [copyMethod 拷贝包]
     * @param  [type] $srcPath    [description]
     * @param  [type] $srcVersion [description]
     * @param  [type] $targetPath [description]
     * @return [array]             [resukt:bool-true/false error:string]
     */
    public function copyMethod($srcPath, $srcVersion, $targetPath)
    {
        $packageItem = new Package;
        $srcInfo = $packageItem->getInfoByPath($srcPath, $srcVersion);
        $targetInfo = $packageItem->getLastValidPackageByPath($targetPath);
        if (empty($srcInfo) || empty($targetInfo)) {
            $resultArray['error'] = '包不存在';
            $resultArray['result'] = false;
            return $resultArray;
        }
        $svnVersion = $srcInfo['svnVersion'];
        $svnBase = $this->svnBase;
        $tmpPath = $this->svnBaseTmp . $srcPath;

        $targetPackageArray = explode('/', trim($targetPath, '/'));
        if (count($targetPackageArray) == 3) {
            $targetName = $targetPackageArray[2];
        } else {
            $targetName = $targetPackageArray[1];
        }
        $targetProduct = $targetPackageArray[0];
        $srcPackageArray = explode('/', trim($srcPath, '/'));
        if (count($srcPackageArray) == 3) {
            $srcName = $srcPackageArray[2];
        } else {
            $srcName = $srcPackageArray[1];
        }
        $srcProduct = $srcPackageArray[0];
        //版本号处理
        $targetVersion = $targetInfo['version'];
        $versionArray = explode('.', $targetVersion);
        $versionArray[count($versionArray) - 1] ++;
        $targetVersion = implode('.', $versionArray);
        $baseVersion = $versionArray;
        unset($baseVersion[count($versionArray) - 1]);
        $baseVersion = implode('.', $baseVersion);

        $cmdArray = array();
        $cmdArray[] = "rm $tmpPath -r ;" .
        "/usr/local/bin/svn export --config-dir /etc/subversion -r $svnVersion svn://".
        $this->svnServer . rtrim($srcPath,'/') .
        "/ $tmpPath --force >/dev/null 2>&1;echo $?";

        $cmdArray[] = '/usr/bin/sed -i s:^name=\"' . $srcName .
        '\":name=\"' . $targetName . '\": ' . $tmpPath . '/init.xml; echo $?';

        $cmdArray[] = '/usr/bin/sed -i s:^product=\"' . $srcProduct . '\":product=\"' .
        $targetProduct . '\": ' . $tmpPath . '/init.xml; echo $?';

        $cmdArray[] = '/usr/bin/sed -i s:^version=\"[0-9.]*\":version=\"' .
        $baseVersion . '\": ' . $tmpPath . '/init.xml; echo $?';

        $cmdArray[] = "/usr/bin/rsync -a --delete --exclude=.svn " . rtrim($tmpPath,'/') .
        "/ $svnBase$targetPath  2>&1;echo $?";

        foreach ($cmdArray as $index => $singleCommand) {
            $singleCommand =  escapeshellcmd($singleCommand);
            $this->log($singleCommand);
            $shellRunResult = shell_exec($singleCommand);
            $this->log($shellRunResult);
            if ($shellRunResult != '0') {
                $resultArray['error'] = '执行命令出错:命令' . ($index+1);
                $resultArray['result'] = false;
                return $resultArray;
            }
        }

        //修改包数据信息
        $packageItem = new Package;
        $packageItem->packageInfo['product'] = $targetInfo['product'];
        $packageItem->packageInfo['name'] = $targetInfo['name'];
        $packageItem->packageInfo['version'] = $targetVersion;
        $packageItem->packageInfo['path'] = $targetInfo['path'];
        $packageItem->packageInfo['user'] = $srcInfo['version'];
        $packageItem->packageInfo['remark'] = "从$srcPath $srcVersion ".
        "{$srcInfo['remark']} 拷贝";
        $packageItem->packageInfo['author'] = $srcInfo['author'];
        $packageItem->packageInfo['status'] = 1;
        $packageItem->packageInfo['frameworkType'] = $srcInfo['frameworkType'];
        $packageItem->packageInfo['stateless'] = $srcInfo['stateless'];
        $packageItem->packageInfo['os'] = $srcInfo['os'];
        //运行升级脚本
        $shellRun = new ExecShell('update_package.sh');
        $shellRun->run(
            $this->pkgHome . $targetPath,
            $targetProduct,
            $targetName,
            $targetVersion
            );
        $resultArray = array();
        $shellRunCode = $shellRun->rtCode();
        if ($shellRun->result(0) != 'result' ||
            ($shellRun->result(1) != 'success' &&
            $shellRun->result(1) != 'failed') ) {
            $resultArray['error'] = '后台异常返回';
            $resultArray['result'] = false;
            return $resultArray;
        }
        if ($shellRun->result(1) === 'success') {
            $packageItem->packageInfo['svnVersion'] = $shellRun->result(3);
            $packageItem->savePackage();
        } else {
            $resultArray['error'] = '升级失败' . $shellRun->result(2);
            $resultArray['result'] = false;
            return $resultArray;
        }
        $resultArray['error'] = '';
        $resultArray['result'] = true;
        return $resultArray;
    }
}
