<?php
namespace publicsrc\src;

use publicsrc\conf\Conf;
use publicsrc\lib\Database;

class Instance
{
    private $dataBase;
    // public $packageInfo;

    function __construct()
    {
        //$host, $user, $password, $name, $port, $table_name=null)
        $this->dataBase = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_instance_table'));
    }

    public function addInstance($instanceInfo)
    {
        $result = $this->dataBase->insertValue($instanceInfo);
        return $result;
    }

    public function updateInstance($instanceInfo)
    {
        $keyArray = array('instanceId'=>$instanceInfo['instanceId']);
        $result = $this->dataBase->updateValue($keyArray, $instanceInfo);
        return $result;
    }

    /**
     * [deleteInstanceList 删除实例列表]
     * @param  [type] $instanceList [description]
     * @return [type]               [description]
     */
    public function deleteInstanceList($instanceList)
    {
        $instanceIdList = array();
        if (!is_array($instanceList)) {
            return false;
        } else {
            foreach ($instanceList as $key => $value) {
                $instanceIdList[] = $value['instanceId'];
            }
            $sql = "UPDATE " . Conf::get('pkg_db_instance_table') . " SET `status`=100 where instanceId in (";
            // $instanceIdStr = implode(",", $instanceIdList);
            $keys = array();
            $count = 1;
            foreach ($instanceIdList as $index => $instanceId) {
                $name = "instance$count";
                $count ++;
                $keys[$name] = $instanceId;
                if ($index != (count($instanceList) - 1)) {
                    $sql .= ":$name, ";
                } else {
                    $sql .= ":$name)";
                }
            }
            $result = $this->dataBase->executeSql($sql, $keys, false);
            return $result;
        }
    }

    /**
     * [deleteInstanceByIp 删除跟IP相关的所有实例]
     * @param  [type] $ip [description]
     * @return [type]     [description]
     */
    public function deleteInstanceByIp($ip)
    {
        $keyArray = array('ip'=>$ip);
        $result = $this->dataBase->deleteValue($keyArray);
        return $result;
    }

    public function getInstance($svnPath , $version, $status=1)
    {
        $toFind = array(
            'packagePath'=>$svnPath,
            'status'=>$status,
            'packageVersion'=>$version);
        $resultArray = $this->dataBase->selectValue($toFind);
        return $resultArray;
    }

    /**
     * [getInstanceByIpAndPath 根据实例ip和安装路径搜索实例]
     * @param  [type] $ip   [description]
     * @param  [type] $path [description]
     * @return [type]       [description]
     */
    public function getInstanceByIpAndPath($ip, $path)
    {
        $toFind = array(
            'ip'=>$ip,
            'packagePath'=>$path,
            'status'=>1);
        $resultArray = $this->dataBase->selectValue($toFind);
        return $resultArray;
    }

    /**
     * [getInstanceCountList 获取版本实例数分布]
     * @param  [type] $path         [description]
     * @param  [type] $instanceName [description]
     * @return [type]               [description]
     */
    public function getInstanceCountList($path, $instanceName, $version=null)
    {
        $keys = array('path'=>$path);
        $sql = "select packageVersion, count(*) as count from " .
        Conf::get('pkg_db_instance_table') . " where packagePath=:path ";
        if (!empty($instanceName)) {
            $sql .= " and name=:instanceName ";
            $keys['instanceName'] = $instanceName;
        }
        if (!empty($version)) {
            $sql .= " and packageVersion=:version";
            $keys['version'] = $version;
        }
        $sql .= " and status=1 group by packageVersion";
        $resultArray = $this->dataBase->executeSql($sql, $keys);
        return $resultArray;
    }

    /**
     * [getInstanceList 获取实例列表]
     * @param  [type] $path         [description]
     * @param  [type] $version      [description]
     * @param  [type] $fromIndex    [可空]
     * @param  [type] $toIndex      [可空]
     * @param  [type] $instanceName [可空]
     * @return [type]               [description]
     */
    public function getInstanceList($path, $version, $fromIndex, $limit, $instanceName)
    {
        $keys = array('path'=>$path);
        $sql = "select * from " . Conf::get('pkg_db_instance_table') . " where packagePath=:path ";
        if (!empty($instanceName)) {
            $sql .= " and name=:instanceName ";
            $keys['instanceName'] = $instanceName;
        }
        if (!empty($version)) {
            $sql .= " and packageVersion=:version ";
            $keys['version'] = $version;

        }
        $sql .= "and status=1 order by inet_aton(substring_index(concat(`packageversion`,'.0.0.0'),'.',4)) desc, inet_aton(ip) asc";
        if (!empty($limit)) {
            $sql .= " limit $fromIndex, $limit";
        }
        $resultArray = $this->dataBase->executeSql($sql, $keys);
        return $resultArray;
    }

    /**
     * [getInstanceListByIp 根据ip获取安装记录]
     * @param  [type] $ip [description]
     * @return [type]     [description]
     */
    public function getInstanceListByIp($ip)
    {
        $toSelect = array('ip'=>$ip, 'status'=>1);
        $resultArray = $this->dataBase->selectValue($toSelect);
        return $resultArray;
    }

    /**
     * [getInstanceListByIp 根据ip获取安装记录 不限状态]
     * @param  [type] $ip [description]
     * @return [type]     [description]
     */
    public function getInstanceByIp($ip)
    {
        $toSelect = array('ip'=>$ip);
        $resultArray = $this->dataBase->selectValue($toSelect);
        return $resultArray;
    }

    /**
     * [updateStatus 更新实例信息]
     * @param  [type] $ipList      [description]
     * @param  [type] $packagePath [description]
     * @param  [type] $installPath [description]
     * @param  [type] $status      [description]
     * @return [type]              [description]
     */
    public function updateStatus($ipList, $packagePath, $installPath, $status)
    {
        $ipStr = implode('\',\'', $ipList);
        $keys = array('status'=>$status, 'packagePath'=>$packagePath, 'installPath'=>$installPath);
        $sql = "update " . Conf::get('pkg_db_instance_table') .
        " set status=:status where ip in (";
        foreach ($ipList as $index => $ip) {
            $name = "ip$index";
            if ($index != (count($ipList) - 1)) {
                $sql .= ":$name, ";
            } else {
                $sql .= ":$name)";
            }
            $keys[$name] = $ip;
        }
        $sql .= "and packagePath=:packagePath and installPath=:installPath";
        // var_dump($sql);exit;
        $result = $this->dataBase->executeSql($sql, $keys, false);
        return $result;
    }
}
