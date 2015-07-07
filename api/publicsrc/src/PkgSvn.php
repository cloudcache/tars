<?php
// ini_set("display_errors", "On");
// error_reporting(E_ALL);
namespace publicsrc\src;

use publicsrc\conf\Conf;

class PkgSvn
{
    public function __construct()
    {
        $svnUser = Conf::get('svn_user');
        $svnPassword = Conf::get('svn_password');
        $this->repo = Conf::get('svn_repo');
        $this->svnCmd = "/usr/bin/svn --username=$svnUser --password=$svnPassword --non-interactive ";
        $this->svnCopy= Conf::get('package_home');
        $this->pkgTmpPath= Conf::get('package_path_export');
        $this->updTmpPath= Conf::get('package_path_update');
        $this->tmpPath= Conf::get('svn_tmp');
        $this->pkgFramework = Conf::get('package_framework');
    }
    public function runCmd($cmd,&$retVal)
    {
        $out = array();
        $retVal = 0;
        $ret = exec($cmd,$out,$retVal);
        $out = implode("\n",$out);
        return $out;
    }
    public function checkout($pkg,$dst=null)
    {
        if ($dst == null) {
            $dst = $this->svnCopy.$pkg;
        }
        //$ret = svn_checkout($this->repo . $pkgPath,$this->svnCopy.$pkgPath);
        $cmd =  $this->svnCmd." checkout ".$this->repo.$pkg." ". $dst . ' 2>&1';
        if(!is_dir(dirname($dst)))
        {
            mkdir(dirname($dst),0755,true);
        }
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
            'code'=>$retVal,
            'msg'=>$ret,
            );
        return $result;
    }
    public function getLastVersion($pkg)
    {
        $cmd =  $this->svnCmd." info ".$this->repo.$pkg;
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $match_count = preg_match_all('/Revision:\s+(\d+)/i',$ret,$matches);
        if($match_count === 0)
        {
            $result = array(
                    'code'=>-1004,
                    'msg'=>'get version error',
                    );
        }
        else
        {
            $result = array(
                    'code'=>0,
                    'msg'=>'ok',
                    'data'=>array('revision'=>$matches[1][0])
                    );
        }
        return $result;

    }
    public function getSvnUrl($path)
    {
        $cmd =  $this->svnCmd." info ".$path;
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $match_count = preg_match_all('/URL:\s+(\S+)/i',$ret,$matches);
        if($match_count === 0)
        {
            $result = array(
                    'code'=>-1004,
                    'msg'=>'get url error',
                    );
        }
        else
        {
            $result = array(
                    'code'=>0,
                    'msg'=>'ok',
                    'data'=>array('svnUrl'=>$matches[1][0])
                    );
        }
        return $result;

    }
    public function commit($path,$log)
    {
        $ret = $this->status($path);
        if($ret['code'] !== 0)
        {
            return $ret;
        }
        $status = $ret['data'];
        $ret = $this->getSvnUrl($path);
        if($ret['code'] !== 0)
        {
            return $ret;
        }
        $svnUrl= $ret['data']['svnUrl'];
        foreach($status as $st)
        {
            switch($st['action'])
            {
                case 'unversion':
                    $ret = $this->add($st['file']);
                    break;
                case 'miss':
                    $this->delete($st['file']);
                    break;
                case 'changed':
                    $relativePath = ltrim(substr($st['file'],strlen($path)+1),'/');
                    $tmpFile = $st['file'].uniqid().".bak";
                    $ret = rename($st['file'],$tmpFile);
                    $cmd =  $this->svnCmd." delete ".$svnUrl.'/'.$relativePath."  -m 'delete ' 2>&1";
                    $retVal = 0;
                    $ret = $this->runCmd($cmd,$retVal);
                    //$this->update($path);
                    $this->update($st['file']);
                    rename($tmpFile,$st['file']);
                    $ret = $this->add($st['file']);
                    break;
                default:continue;
            }
        }

        $cmd =  $this->svnCmd." ci ".$path."  -m '". $log."' 2>&1";
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function status($path)
    {
        if(!is_dir($path."/.svn"))
        {
            $result = array(
                    'code'=>-10005,
                    'msg'=>'path is not svn dir',
                    );
            return $result;
        }
        $cmd =  $this->svnCmd." st  ".$path." 2>&1";
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $status = array();
        if(!empty($ret))
        {
            $lines = explode("\n",$ret);
            $actionMap = array(
                    '?'=>'unversion',
                    'A'=> 'add',
                    'D'=> 'delete',
                    '!'=> 'miss',
                    'M'=> 'modify',
                    '~'=> 'changed',
                    );
            foreach($lines as $line)
            {
                $action = substr($line,0,1);
                if (array_key_exists($action,$actionMap))
                {
                    $st['action'] = $actionMap[$action];
                }
                else
                {
                    $st['action'] = 'other';
                }
                $st['file'] =  trim(substr($line,8));
                $status[] = $st;
            }
        }
        $result = array(
                'code'=>$retVal,
                'msg'=>'OK',
                'data'=>$status,
                );
        return $result;
    }
    public function add($path)
    {
        $cmd =  $this->svnCmd." add   --force ".$path." 2>&1";
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function delete($path)
    {
        //$ret = svn_delete($path);
        $cmd =  $this->svnCmd." delete ".$path." 2>&1";
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function allDelete($path)
    {
        //$ret = svn_delete($path);
        $ret = $this->getSvnUrl($this->svnCopy."/".$path);
        if($ret['code'] !== 0)
        {
            return $ret;
        }
        $svnUrl= $ret['data']['svnUrl'];
        $cmd =  $this->svnCmd." delete ".$svnUrl." -m 'delete' 2>&1";
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
	$shell ="rm -rf ".$this->svnCopy.$path;
        $ret = $this->runCmd($shell,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function revert($path)
    {
        //$ret = svn_revert($path);
        $cmd =  $this->svnCmd." revert ".$path." 2>&1";
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function update($path)
    {
        //$ret = svn_update($pkgPath);
        $cmd =  $this->svnCmd." update ".$path." 2>&1";
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function export($pkg,$dst,$version = -1)
    {
        $cmd =  $this->svnCmd." export  --force -r $version ".$this->repo.$pkg." $dst 2>&1";
        if(!is_dir(dirname($dst)))
        {
            mkdir(dirname($dst),0755,true);
        }
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function exportFile($path,$file,$version,$dst)
    {
        $cmd =  $this->svnCmd." export -N ".$this->repo.$path.$file."@$version $dst 2>&1";
        if(!is_dir(dirname($dst)))
        {
            mkdir(dirname($dst),0755,true);
        }
        $retVal = 0;
        $ret = $this->runCmd($cmd,$retVal);
        $result = array(
                'code'=>$retVal,
                'msg'=>$ret,
                );
        return $result;
    }
    public function diffVersion($pkg,$startVersion,$endVersion)
    {
        $cmd = $this->svnCmd ." diff -r ".$startVersion.":".$endVersion." --summarize ".$this->repo.$pkg;
        $ret = shell_exec($cmd);
        $line_arr = explode("\n",trim($ret));
        $diff_arr = array();
        foreach($line_arr as $line)
        {
            $pre_str = rtrim($this->repo,'/').'/'.ltrim($pkg,'/');
            $line = str_replace($pre_str,'',$line);
            $action = substr($line,0,1);
            $file = trim(substr($line,8));
            $diff['action'] = $action;
            $diff['file'] = $file;
            $diff_arr[] = $diff;
        }
        return $diff_arr;
    }
    public function checkPkg($path)
    {
        if(!is_dir($path) || !is_file($path."/init.xml") )
        {
            $result = array(
                    'code'=>-100000,
                    'msg'=>'frame work error',
                    );
            return $result;
        }
        $content = file_get_contents($path."/init.xml");
        $match_count = preg_match_all('/\nname=.*/',$content,$matches);
        if($match_count === 0)
        {
            $result = array(
                    'code'=>-100001,
                    'msg'=>'init.xml error',
                    );
            return $result;
        }
        $result = array(
                'code'=>0,
                'msg'=>'ok',
                );
        return $result;
    }
    public function checkInstallPkg($path)
    {
        if(!is_dir($path) ||
                !is_dir($path."/admin") ||
                !is_file($path."/init.xml") ||
                !is_file($path."/install.sh") ||
                !is_file($path."/admin/data/version.ini") ||
                !is_file($path."/admin/data/source.ini")
          )
        {
            $result = array(
                    'code'=>-100000,
                    'msg'=>'frame work error',
                    );
            return $result;
        }
        $content = file_get_contents($path."/init.xml");
        $match_count = preg_match_all('/\nname=.*/',$content,$matches);
        if($match_count === 0)
        {
            $result = array(
                    'code'=>-100001,
                    'msg'=>'init.xml error',
                    );
            return $result;
        }
        $result = array(
                'code'=>0,
                'msg'=>'ok',
                );
        return $result;
    }
    public function checkUpdatePkg($path)
    {
        if(!is_dir($path) ||
                !is_file($path."/md5.conf") ||
                !is_file($path."/update.conf") ||
                !is_file($path."/update.sh") ||
                !is_file($path.".tar.gz")
          )
        {
            $result = array(
                    'code'=>-10007,
                    'msg'=>'update frame work error',
                    );
            return $result;
        }

        $content = file($path."/update.conf");
        foreach($content as $line)
        {
            list($action,$file) = preg_split('/(\s)+/',$line);

            if(!in_array($action ,array('A','M')))
            {
                continue;
            }
            if(!is_file($path.$file) && !is_dir($path.$file))
            {
                $result = array(
                        'code'=>1005,
                        'msg'=>'file '.$file." errror",
                        );
                return $result;
            }
        }
        $result = array(
                'code'=>0,
                'msg'=>'ok',
                );
        return $result;
    }
    public function exportPkg($pkg,$svn_ver,$version,$type = 'server')
    {
        //get pkg info [/product/name]
        $pkgInfo = explode('/',$pkg);
        $product = $pkgInfo[1];
        $name = $pkgInfo[2];

        $template = $this->pkgFramework."/template/";
        $pathName = $name."-".$version.'-install';
        $pkgCopyPath = $this->pkgTmpPath.$pkg.'/'.$pathName;
        $ret = $this->checkInstallPkg($pkgCopyPath);
        if($ret['code'] === 0)
        {
            $result = array(
                    'code'=>0,
                    'msg'=>'OK',
                    'data'=>array('exportPath'=>$pkgCopyPath),
                    );
            return $result;
        }
        if(empty($pkgCopyPath))
        {
            $result = array(
                    'code'=>-100002,
                    'msg'=>'path error',
                    );
            return $result;
        }

        $cmd = "rm -rf ".$pkgCopyPath."/*";
        $ret = shell_exec($cmd);

        $ret = $this->export($pkg,$pkgCopyPath,$svn_ver);
        if($type == 'plugin')
        {
            $cmd = "cp -ar ".$template."/plugin_install.sh ".$pkgCopyPath."/install.sh";
        }
        else
        {
            $cmd = "cp -ar ".$template."/install.sh ".$pkgCopyPath."/install.sh";
        }
        $ret = shell_exec($cmd);
        $cmd = "cp -ar ".$template."/admin ".$pkgCopyPath."/";
        $ret = shell_exec($cmd);
        file_put_contents($pkgCopyPath."/admin/data/source.ini",$pkg."\n");
        $cur_time = date('Y-m-d H:i:s');
        $ver_str = "[$cur_time] ".$version."\n";
        file_put_contents($pkgCopyPath."/admin/data/version.ini",$ver_str);

        $cmd = "cd ". $this->pkgTmpPath.$pkg." ;tar -zcf ".$pathName.".tar.gz ".$pathName."/";
        $ret = shell_exec($cmd);


        $ret = $this->checkInstallPkg($pkgCopyPath);
        if($ret['code'] == 0)
        {
            $result = array(
                    'code'=>0,
                    'msg'=>'OK',
                    'data'=>array('exportPath'=>$pkgCopyPath),
                    );
            return $result;
        }
        else
        {
            return $ret;
        }
    }

    public function exportUpdatePkg($pkg,$fromSvnVer,$toSvnVer,$fromVer,$toVer,$type = 'server')
    {
        //get pkg info [/product/name]
        $pkgInfo = explode('/',$pkg);
        $product = $pkgInfo[1];
        $name = $pkgInfo[2];

        $updPkgName = "$name-update-$fromVer-$toVer";
        $updPkgPath = $this->updTmpPath.$pkg."/".$updPkgName ;
        $tmpPath = $this->tmpPath.$pkg;
        $updateConf = $updPkgPath."/update.conf";
        $md5Conf = $updPkgPath."/md5.conf";
        $template = $this->pkgFramework."/template/";
        $ret = $this->checkUpdatePkg($updPkgPath);
        if($ret['code'] === 0)
        {
            $result = array(
                    'code'=>0,
                    'msg'=>$ret,
                    'data'=>array('updateConf'=>$updateConf)
                    );
            return $result;
        }


        $changes = $this->diffVersion($pkg,$fromSvnVer,$toSvnVer);
        $this->exportFile($pkg,"/init.xml",$toSvnVer,$updPkgPath."/init.xml");
        $content = file_get_contents($updPkgPath."/init.xml");
        $match_count = preg_match('/\nuser="?([^#\s"]*)"?/',$content,$matches);
        if($match_count >=1)
        {
            $user = $matches[1];
        }
        else
        {
            $user = 'user_00';
        }

        file_put_contents($updateConf,"name=\"".$name."\"\n");
        file_put_contents($updateConf,"user=\"".$user."\"\n",FILE_APPEND);
        file_put_contents($updateConf,"##Begin----------------\n",FILE_APPEND);
        file_put_contents($updateConf,"from=".$fromVer."\n",FILE_APPEND);
        file_put_contents($updateConf,"to=".$toVer."\n",FILE_APPEND);
        file_put_contents($updateConf,"change_count=".count($changes)."\n",FILE_APPEND);
        file_put_contents($updateConf,"##End----------------\n",FILE_APPEND);
        file_put_contents($md5Conf,"",FILE_APPEND);
        foreach($changes as $line)
        {
            if(count($line)<2)
            {
                continue;
            }
            $op = $line['action'];
            $file = $line['file'];
            switch($op)
            {
                case 'A':
                    $ret = $this->exportFile($pkg,$file,$toSvnVer,$updPkgPath.$file);
                    file_put_contents($updateConf,"A ".$file."\n",FILE_APPEND);
                    break;
                case 'M':
                    $ret = $this->exportFile($pkg,$file,$toSvnVer,$updPkgPath.$file);
                    $ret = $this->exportFile($pkg,$file,$fromSvnVer,$tmpPath.$file);
                    file_put_contents($updateConf,"M ".$file."\n",FILE_APPEND);
                    $toMd5= md5_file($updPkgPath.$file);
                    $fromMd5 = md5_file($tmpPath.$file);
                    file_put_contents($md5Conf,$file." ".$fromMd5." ".$toMd5."\n",FILE_APPEND);
                    break;
                case 'D':
                    file_put_contents($updateConf,"D ".$file."\n",FILE_APPEND);
                    break;
                default: break;
            }
        }
        if($type == 'plugin')
        {
            $cmd = "cp -ar ".$template."/plugin_update.sh ".$updPkgPath."/update.sh";
        }
        else
        {
            $cmd = "cp -ar ".$template."/update.sh ".$updPkgPath."/update.sh";
        }
        $ret = shell_exec($cmd);
        $cmd = "cd ". dirname($updPkgPath)." ;tar -zcf ".$updPkgName.".tar.gz ".$updPkgName;
        $ret = shell_exec($cmd);

        $ret = $this->checkUpdatePkg($updPkgPath);
        if($ret['code'] !== 0)
        {
            return $ret;
        }
        $result = array(
                'code'=>0,
                'msg'=>$ret,
                'data'=>array('updateConf'=>$updateConf)
                );
        return $result;
    }
    public function buildUpdatePackage($pkg,$path)
    {
        if(!is_dir($path))
        {
            $result = array(
                    'code'=>-10004,
                    'msg'=>'path is not exists',
                    );
            return $result;
        }
        $pkgInfo = explode('/',$pkg);
        $product = $pkgInfo[1];
        $name = $pkgInfo[2];
        if(!is_dir($path."/.svn"))
        {
            $result = array(
                    'code'=>-10005,
                    'msg'=>'path is not svn dir',
                    );
            return $result;
        }
        $ret = $this->status($path);
        if($ret['code'] !== 0)
        {
            return $ret;
        }
        $changes = $ret['data'];
        if(empty($changes))
        {
            $result = array(
                    'code'=>-10006,
                    'msg'=>'package is not chanage',
                    );
            return $result;
        }

        $ret = $this->commit($path,"system update");
        if($ret['code']!= 0)
        {
            return $ret;
        }
        $ret = $this->getLastVersion($pkg);
        if($ret['code']!= 0)
        {
            return $ret;
        }
        $revision = $ret['data']['revision'];
        $result = array(
                'code'=>0,
                'msg'=>'ok',
                'data'=>array(
                    'svnPath'=>$this->repo.$pkg,
                    'svnVersion'=>$revision,
                    ),
                );
        return $result;

    }
    public function buildPackage($pkg,$path)
    {
        if(!is_dir($path))
        {
            $result = array(
                    'code'=>-10004,
                    'msg'=>'path is not exists',
                    );
            return $result;
        }
        $ret = $this->checkPkg($path);
        if($ret['code'] !== 0)
        {
            return $ret;
        }
        $cmd =  $this->svnCmd." ls ".$this->repo.$pkg.' 2>&1';
        $retVal = 0 ;
        $ret = $this->runCmd($cmd,$retVal);
        if($retVal == 0)
        {
            $result = array(
                    'code'=>-100005,
                    'msg'=>'the pkg exists',
                    );
        }
        $cmd =  $this->svnCmd." import ".$path." ".$this->repo.$pkg.'  -m "build package" 2>&1';
        $retVal = 0 ;
        $ret = $this->runCmd($cmd,$retVal);
        if($retVal != 0)
        {
            $result = array(
                    'code'=>-100006,
                    'msg'=>'import error',
                    );
        }
        $cmd = "rm -rf ".$path;
        $ret = $this->runCmd($cmd,$retVal);
        $ret = $this->checkout($pkg,$path);
        if($ret['code']!= 0)
        {
            return $ret;
        }
        $ret = $this->getLastVersion($pkg);
        if($ret['code']!= 0)
        {
            return $ret;
        }
        $revision = $ret['data']['revision'];
        $result = array(
                'code'=>0,
                'msg'=>'ok',
                'data'=>array(
                    'svnPath'=>$this->repo.$pkg,
                    'svnVersion'=>$revision,
                    ),
                );
        return $result;
    }
}
