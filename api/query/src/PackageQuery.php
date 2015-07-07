<?php
namespace query\src;

use publicsrc\conf\Conf;
use publicsrc\src\Package;
use publicsrc\src\Password;
use publicsrc\src\Instance;
use publicsrc\src\ExecShell;
use publicsrc\lib\Log;
use publicsrc\lib\Amqp;
use publicsrc\lib\Database;
use publicsrc\lib\NewDatabase;
use publicsrc\src\MagicTool;
use Flight;

class PackageQuery
{
    private $pkgHome;
    private $pkgCodeHome;
    private $installCopyHome;
    public $errorMsg;
    private $logger;
    const INFO = 'INFO';
    const ERROR = 'ERROR';

    function __construct()
    {
        $this->errorMsg = null;
        $className = __CLASS__;
        $this->logger = new Log($className);
    }

    public function test()
    {
        // $p = new Password;
        // echo $p->getDevicePassword('1.1.1.1');
        // $db = new NewDatabase('192.168.1.1', '3306', 'pkg', 'pkg', 'PackageCenterOpensrc', 'test');
        // $arr = array('name'=>'pekingg', 'password'=>'pekingt');
        // $key = array('name'=>'pekinggg', 'password'=>'pekingt');
        // $db->insertValue($arr);
        // $ret = $db->selectValue($arr);
        // $ret = $db->updateValue($key, $arr);
        // $ret = $db->deleteValue($key);
        // $sql = "select * from test where `name`=:name";
        // $arr = array('name'=>'pekingg');
        // $ret = $db->executeSql($sql, $arr);

        // var_dump($ret);
        // $ins = new Instance;
        // $ret = $ins->deleteInstanceList(array(array('instanceId'=>'58'), array('instanceId'=>'60')));
        // var_dump($ret);
        $pkg = new Package;
        // $previousPackage = $pkg->getLastValidPackage('/pekinglin/steve', '9', '1.0.2');
        // $previousPackage = $pkg->getLastValidPackageByPath('/pekinglin/steve');
        // $previousPackage = $pkg->getInfo('pekinglin', 'steve', '1.0.0');
        // $previousPackage = $pkg->searchPackage('', 'peking');
        // $previousPackage = $pkg->addProductMap(
        //     array(
        //         array('product'=>'test1', 'chinese'=>'测试1'),
        //         array('product'=>'test2', 'chinese'=>'测试2'),
        //     ));
        $previousPackage = $pkg->getProductMap();
        // $previousPackage = $pkg->deleteProductMap(array('test1', 'test2'));

        var_dump($previousPackage);
    }

    public function test1(&$name)
    {
        $name['lala'] = 123;
    }

    public function log($msg)
    {
        $this->logger->info($msg);
    }

    /**
     * [importDevicePassword 导入设备密码]
     * @return [type] [description]
     */
    public function importDevicePassword()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $needPara = array('devicePasswordList');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $list = $data['devicePasswordList'];
        $allResult = true;
        foreach ($list as $key => $value) {
            $deviceId = $value['deviceId'];
            $password = $value['password'];
            $p = new Password;
            $result = $p->insertDevice($deviceId, $password);
            if ($result == false) {
                $allResult = false;
            }
        }
        if ($allResult == false) {
            $errorArray['error'] = "导入失败, 请检查";
            Flight::json($errorArray, 400);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * [getDevicePassword 获取设备密码]
     * @return [type] [description]
     */
    public function getDevicePassword()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        // $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('deviceId');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $deviceId = $data['deviceId'];
        $p = new Password;
        $password = $p->getDevicePassword($deviceId);
        $errorArray['password'] = $password;
        Flight::json($errorArray, 200);
    }

    /**
     * 根据idc或者业务获取设备
     */
    public function getDevice()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        // $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array();
        $optionalPara = array('idc', 'business');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $idc = $data['idc'];
        $business = $data['business'];
        $p = new Password;
        $device = $p->getDeviceByIdcOrBusiness($idc, $business);
        if ($device == null) {
            $device = array();
        }
        // $errorArray['password'] = $password;
        Flight::json($device, 200);
    }

    /**
     * 更新设备信息
     */
    public function updateDevice()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $needPara = array();
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $deviceArray = $data;
        $p = new Password;
        $device = $p->updateDevice($deviceArray);
        if ($device == false) {
            $errorArray['error'] = '修改失败';
            Flight::json($errorArray, 400);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * 更新设备信息
     */
    public function deleteDevice()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $needPara = array();
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $deviceArray = $data;
        $p = new Password;
        $device = $p->deleteDevice($deviceArray);
        if ($device == false) {
            $errorArray['error'] = '删除失败';
            Flight::json($errorArray, 400);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * 获取idc列表
     */
    public function getDeviceIdc()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        // $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array();
        $optionalPara = array('business');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $business = $data['business'];
        $p = new Password;
        $device = $p->getDeviceIdc($business);
        if ($device == null) {
            $device = array();
        }
        // $errorArray['password'] = $password;
        Flight::json($device, 200);
    }

    /**
     * 获取业务列表
     */
    public function getDeviceBusiness()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        // $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array();
        $optionalPara = array('idc');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $idc = $data['idc'];
        $p = new Password;
        $device = $p->getDeviceBusiness($idc);
        if ($device == null) {
            $device = array();
        }
        // $errorArray['password'] = $password;
        Flight::json($device, 200);
    }

    /**
     * [dealInstanceReport 接收实例上报数据]
     * @return [type] [description]
     */
    public function dealInstanceReport()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $this->log(json_encode($data));
        $needPara = array('action', 'ip', 'data');
        $optionalPara = array();
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $action = $data['action'];
        $ip = $data['ip'];
        $actionMap = array('delete', 'replace');
        if ((!in_array($action, $actionMap))) {
            $errorArray['error'] = 'action error';
            Flight::json($errorArray, 200);
        }
        $instance = new Instance;
        //删除包实例
        if ($action == 'delete') {
            $result = $instance->deleteInstanceByIp($ip);
            if (!$result) {
                $errorArray['error'] = 'delete instance error';
                Flight::json($errorArray, 400);
            }
        } elseif ($action == 'replace') {
            $data = urldecode($data['data']);
            $data = explode('||', $data);
            $databaseInfo = $instance->getInstanceByIp($ip);
            $newDatabaseInfo = array();
            $dataAll = array();
            foreach ($databaseInfo as $key => $value) {
                $newKey = $value['ip'].'|'.$value['packagePath'].'|'.$value['installPath'];
                $value['hash'] = $value['ip'] . $value['packagePath'] . $value['packageVersion'] . $value['installPath'] . $value['name'] . $value['port'] . $value['status'];
                $newDatabaseInfo[$newKey] = $value;
            }
            foreach ($data as $key => $item) {
                if (empty($item)) {
                    continue;
                }
                $item = explode('###', $item);
                $this->log(json_encode($item));
                $instanceData = array(
                    'ip'=>$ip,
                    'installPath'=>$item[0],
                    'packageVersion'=>$item[1],
                    'packagePath'=>$item[2],
                    'name'=>$item[4],
                    'port'=>$item[6],
                    'submitTime'=>date("Y-m-d H:i:s",time()),
                    'installTime'=>$item[7],
                    'status'=>1,
                    'hash'=>$ip.$item[2].$item[1].$item[0].$item[4].$item[6]."1"
                    );
                $newKey = $ip.'|'.$item[2].'|'.$item[0];
                $dataAll[$newKey] = $instanceData;
            }
            $toDelete = array();
            $toModify = array();
            $this->log('database data ' . json_encode($newDatabaseInfo));
            $this->log('report data ' . json_encode($dataAll));

            foreach ($newDatabaseInfo as $key => $value) {
                if (empty($dataAll[$key])) {
                    //删除已不存在实例
                    $toDelete[] = $value;
                } elseif ($value['hash'] == $dataAll[$key]['hash']) {
                    //一样不需要修改
                    unset($dataAll[$key]);
                    continue;
                } else {
                    //做修改
                    $dataAll[$key]['instanceId'] = $value['instanceId'];
                    $toModify[] = $dataAll[$key];
                    unset($dataAll[$key]);
                }
            }

            //还剩下的要添加
            foreach ($dataAll as $key => $value) {
                unset($value['hash']);
                $result = $instance->addInstance($value) == true ? 'true':'false';
                $this->log('insert result' . $result);
            }
            //删除的实例
            if (!empty($toDelete)) {
                $result = $instance->deleteInstanceList($toDelete) == true ? 'true':'false';
                $this->log('delete result' . $result);
            }
            if (!empty($toModify)) {
                foreach ($toModify as $key => $value) {
                    unset($value['hash']);
                    $result = $instance->updateInstance($value) == true ? 'true':'false';
                    $this->log('update result' . $result);
                }
            }
        }
        Flight::json($errorArray, 200);
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
     * [searchPackage 搜索包]
     * @return [type] [description]
     */
    public function searchPackage()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array();
        $optionalPara = array('product', 'name');
        // $product = isset($data['product']) ? $data['product']:'';
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $package = new Package;
        $result = $package->searchPackage($data['name'], $data['product']);
        $errorArray = $result;
        Flight::json($errorArray, 200);
    }

    /**
     * [getPackageVersionList 获取包版本列表]
     * @return [type] [description]
     */
    public function getPackageVersionList()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('product', 'name');
        $error = $this->checkParameter($needPara, $data);
        $package = new Package;
        $result = $package->getVersionList($data['name'], $data['product']);

        $errorArray = $result;
        Flight::json($errorArray, 200);
    }

    /**
     * [getPackageInformation 获取包详情]
     * @return [type] [description]
     */
    public function getPackageInformation()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('product', 'name');
        $optionalPara = array('version');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $package = new Package;
        $result = $package->getInfo($data['product'], $data['name'], $data['version']);
        $errorArray = $result;
        if (empty($result)) {
            $errorArray['error'] = '查找无结果';
            Flight::json($errorArray, 404);
        }
        Flight::json($errorArray, 200);
    }

    /**
     * [getInstanceByIpAndPath 根据ip和路径获取单个安装实例]
     * @return [type] [description]
     */
    public function getInstanceByIpAndPath()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('ip', 'path');
        $error = $this->checkParameter($needPara, $data);
        $instance = new Instance;
        $result = $instance->getInstanceByIpAndPath($data['ip'], $data['path']);
        $errorArray = $result;
        Flight::json($errorArray, 200);
    }

    /**
     * [getPathByProductAndName 根据产品和名称获取路径path]
     * @return [type] [description]
     */
    public function getPathByProductAndName()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('product', 'name');
        $error = $this->checkParameter($needPara, $data);
        $package = new Package;
        $result = $package->getSvnPath($data['product'], $data['name']);
        $errorArray = $result;
        Flight::json($errorArray, 200);
    }

    /**
     * [getInstanceCountList 获取实例版本数分布]
     * @return [type] [description]
     */
    public function getInstanceCountList()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('path');
        $optionalPara = array('version', 'instanceName');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $instance = new Instance;
        $result = $instance->getInstanceCountList($data['path'], $data['instanceName'], $data['version']);
        $total = 0;
        foreach ($result as $value) {
            $total += intval($value['count']);
        }
        $errorArray = array('total'=>$total, 'list'=>$result);
        Flight::json($errorArray, 200);
    }

    /**
     * [getInstanceList 获取实例列表]
     * @return [type] [description]
     */
    public function getInstanceList()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $needPara = array('path');
        $optionalPara = array('version', 'fromIndex', 'limit', 'instanceName', 'targetIps');
        $error = $this->checkParameter($needPara, $data, $optionalPara);
        $instance = new Instance;
        $result = $instance->getInstanceList(
            $data['path'],
            $data['version'],
            $data['fromIndex'],
            $data['limit'],
            $data['instanceName']);
        $targetIps = $data['targetIps'];
        if (!empty($data['targetIps'])) {
            if (!is_array($data['targetIps'])) {
                $targetIps = array($data['targetIps']);
            }
            foreach ($result as $index => $value) {
                if (!in_array($value['ip'], $targetIps)) {
                    unset($result[$index]);
                }
            }
            $result = array_values($result);
        }
        $errorArray = $result;
        Flight::json($errorArray, 200);
    }

    /**
     * [getInstanceListbyIp 根据ip获取所有实例列表]
     * @return [type] [description]
     */
    public function getInstanceListbyIp()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('ip');
        $error = $this->checkParameter($needPara, $data);
        $instance = new Instance;
        $result = $instance->getInstanceListByIp($data['ip']);

        $errorArray = $result;
        Flight::json($errorArray, 200);
    }

    /**
     * [setPackageRemark 修改包备注]
     */
    public function setPackageRemark()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $this->log(json_encode($data));
        $needPara = array('path', 'version', 'remark');
        $error = $this->checkParameter($needPara, $data);
        $package = new Package;
        $result = $package->setRemark($data['path'], $data['version'], $data['remark']);
        if ($result != true) {
            $errorArray['error'] = '修改备注出错';
            Flight::json($errorArray, 400);
        }

        Flight::json($errorArray, 200);
    }

    /**
     * [deleteInstallRecord 删除包安装记录]
     * @return [type] [description]
     */
    public function deleteInstallRecord()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $needPara = array('ipList', 'packagePath', 'installPath');
        $error = $this->checkParameter($needPara, $data);
        $instance = new Instance;
        $result = $instance->updateStatus(
            $data['ipList'],
            $data['packagePath'],
            $data['installPath'],
            100
            );
        if ($result != true) {
            $errorArray['error'] = '删除记录出错';
            Flight::json($errorArray, 400);
        }

        Flight::json($errorArray, 200);
    }

    /**
     * [getProductMap 获取业务名称映射表]
     * @return [type] [description]
     */
    public function getProductMap()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        // $data = Flight::request()->query->getData();
        $package = new Package();
        $result = $package->getProductMap();
        Flight::json($result, 200);
    }

    /**
     * [addProductMap 批量插入业务映射]
     */
    public function addProductMap()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->data->getData();
        $needPara = array('productList');
        $error = $this->checkParameter($needPara, $data);
        $package = new Package();
        $result = $package->addProductMap($data['productList']);
        if ($result) {
            Flight::json($errorArray, 200);
        }
        $errorArray['error'] = '修改数据库出错';
        Flight::json($errorArray, 400);
    }

    /**
     * [deleteProductMap 删除业务映射]
     * @return [type] [description]
     */
    public function deleteProductMap()
    {
        $this->log('START FUNCTION '.__FUNCTION__);
        $errorArray = array('error'=>'');
        $data = Flight::request()->query->getData();
        $needPara = array('productList');
        $error = $this->checkParameter($needPara, $data);
        $productList = explode(";", $data['productList']);
        $package = new Package();
        foreach ($productList as $value) {
            $hasPackage = $package->searchPackage(null, $value);
            if (!empty($hasPackage)) {
                $errorArray['error'] = "$value 产品下有已经存在的包, 无法删除";
                Flight::json($errorArray, 400);
            }
        }
        $result = $package->deleteProductMap($productList);
        if ($result) {
            Flight::json($errorArray, 200);
        }
        $errorArray['error'] = '修改数据库出错';
        Flight::json($errorArray, 400);
    }
}
