<?php
namespace publicsrc\src;

use publicsrc\lib\Database;
use publicsrc\conf\Conf;

 class Package
 {
    private $dataBase;
    public $packageInfo;

    function __construct()
    {
        //$host, $user, $password, $name, $port, $table_name=null)
        $this->dataBase = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_info_table'));

        $this->packageInfo = array(
            "id" => "",
            'billId' => '',
            "product" => "",
            "name" => "",
            "version" => "",
            "path" => "",
            "svnVersion" => "",
            "user" => "",
            "stateless" => "",
            "frameworkType" => "",
            "os" => "",
            "remark" => "",
            "status" => "");
    }

    /**
     * [savePackage 保存包的新信息]
     * @return [Boolean] [true false]
     */
 	public function savePackage()
 	{
        $toDelete = array(
            'product'=>$this->packageInfo['product'],
            'name'=>$this->packageInfo['name'],
            'version'=>$this->packageInfo['version'],
            'status'=>200);
        $resultBool = $this->dataBase->deleteValue($toDelete);
        if (!$resultBool) {
            return $resultBool;
        }

        $toInsert = array(
            'billId'=>$this->packageInfo['billId'],
            'product'=>$this->packageInfo['product'],
            'name'=>$this->packageInfo['name'],
            'version'=>$this->packageInfo['version'],
            'path'=>$this->packageInfo['path'],
            'svnVersion'=>$this->packageInfo['svnVersion'],
            'user'=>$this->packageInfo['user'],
            'stateless'=>$this->packageInfo['stateless'],
            'frameworkType'=>$this->packageInfo['frameworkType'],
            'author'=>$this->packageInfo['author'],
            'os'=>$this->packageInfo['os'],
            'remark'=>$this->packageInfo['remark'], //remark要注意会不会截断  转义等
            'status'=>$this->packageInfo['status'],
            'submitTime'=>date('Y-m-d H:i:s')
            );
        // file_put_contents('sql.sql', $sql); //why add??
        $resultBool = $this->dataBase->insertValue($toInsert);
        return $resultBool;
 	}

    /**
     * [isLastPackage 判断包是否只有一个 是最后一个]
     * @param  [string]  $svnPath [description]
     * @param  integer $status  [description]
     * @return boolean          [description]
     */
    public function isLastPackage($svnPath, $status=1)
    {
        $toSelect = array('path'=>$svnPath, 'status'=>$status);
        $resultArray = $this->dataBase->selectValue($toSelect);
        if (!empty($resultArray) && count($resultArray) == 1) {
            return true;
        }
        return false;
    }

    public function getLastValidPackage($svnPath, $packageId, $version)
    {
        $keys = array('svnPath'=>$svnPath, 'packageId'=>$packageId, 'version'=>$version);
        $sql = "SELECT * FROM  `sPackage` WHERE  `path`=:svnPath and `packageId` <= :packageId and `version` != :version and `status`!=4 order by `packageId` desc limit 1";
        $resultArray = $this->dataBase->executeSql($sql, $keys);
        if ((!empty($resultArray)) && (count($resultArray) == 1)) {
            return $resultArray[0];
        }
        return null;
    }

    public function getLastValidPackageByPath($path)
    {
        $keys = array('path'=>$path);
        $sql = "SELECT * FROM `sPackage` WHERE `path`=:path  AND STATUS =1 ORDER BY packageId DESC LIMIT 1";
        $resultArray = $this->dataBase->executeSql($sql, $keys);
        if ((!empty($resultArray)) && (count($resultArray) == 1)) {
            return $resultArray[0];
        }
        return null;
    }

    /**
     * [getSvnPath 获取包的svn路径--- 也就是/product/name  = =]
     * @param  [type] $product [description]
     * @param  [type] $name    [description]
     * @return [type]          [description]
     */
 	public function getSvnPath($product, $name)
 	{
        $toSelect = array('product'=>$product, 'name'=>$name, 'status'=>1);
        $resultArray = $this->dataBase->selectValue($toSelect);
        if (!empty($resultArray)) {
            $tmpArray = $resultArray[count($resultArray) - 1];
            return $tmpArray['path'];
        } else {
            return null;
        }
 	}

   	/**
       * [getInfo 获取包信息]
       * @param  [type]  $product [description]
       * @param  [type]  $name    [description]
       * @param  [type]  $version [description]
       * @param  integer $status  [description]
       * @return [type]           [description]
       */
    public function getInfo($product, $name, $version, $status=1)
   	{
        if (empty($version)) {
            $keys = array('product'=>$product, 'name'=>$name);
            $sql = "SELECT * FROM `sPackage` WHERE product=:product and name=:name and status=1 order by `packageId` desc limit 0,1";
            $resultArray = $this->dataBase->executeSql($sql, $keys);
        } else {
            $keyArray = array(
              'product'=>$product,
              'name'=>$name,
              'version'=>$version,
              'status'=>$status);
            $resultArray = $this->dataBase->selectValue($keyArray);
        }

        if (!empty($resultArray)) {
            return $resultArray[0];
        }
        return $resultArray;
   	}

    /**
     * [getInfoByPath 根据路径和版本获取包信息]
     * @param  [type] $path    [description]
     * @param  [type] $version [description]
     * @return [type]          [description]
     */
    public function getInfoByPath($path, $version)
    {
        $keyArray = array(
            'path'=>$path,
            'version'=>$version,
            'status'=>1);
        $resultArray = $this->dataBase->selectValue($keyArray);
        if (!empty($resultArray)) {
          return $resultArray[0];
        }
        return $resultArray;
    }

    /**
     * [SearchPackage 搜索包]
     * @param [type] $name          [description]
     * @param [type] $product       [description]
     */
    public function searchPackage($name, $product)
    {
        // $sql = "SELECT DISTINCT path, product, name, user FROM sPackage " .
        if (!empty($product) && !empty($name)) {
            $keys = array('product' => '%' .$product . '%', 'name'=>'%' . $name . '%');
            $sql = "SELECT  product, name, version, author, submitTime FROM sPackage " . "WHERE product LIKE :product AND name LIKE :name " . " AND status = 1 group by product, name order by product, name";
        } elseif (!empty($product)) {
            $keys = array('product' => '%' .$product . '%');
            $sql = "SELECT  product, name, version, author, submitTime FROM sPackage " . "WHERE product LIKE :product " . " AND status = 1 group by product, name order by product, name";
        } else {
            $keys = array('name'=>'%' . $name . '%');
            $sql = "SELECT  product, name, version, author, submitTime FROM sPackage " . "WHERE name LIKE :name " . " AND status = 1 group by product, name order by product, name";
        }
        // var_dump($sql);exit;

        $resultArray = $this->dataBase->executeSql($sql, $keys);
        return $resultArray;
    }

    /**
     * [getVersionList 获取可用的包版本列表]
     * @param  [type] $name    [description]
     * @param  [type] $product [description]
     * @return [type]          [description]
     */
    public function getVersionList($name, $product)
    {
        $toSelect = array(
            'product'=>$product,
            'name'=>$name,
            'status'=>1
            );
        $resultArray = $this->dataBase->selectValue($toSelect);
        return $resultArray;
    }


    /**
     * [setStatus 根据包的id修改包的状态]
     * @param [type] $packageId [description]
     * @param [type] $status    [description]
     */
    public function setStatus($packageId, $status)
    {
        $keyArray = array('packageId'=>$packageId);
        $paraArray = array('status'=>$status);
        $result = $this->dataBase->updateValue($keyArray, $paraArray);
        return $result;
    }

    /**
     * [setRemark 设置包版本备注]
     * @param [type] $path    [description]
     * @param [type] $version [description]
     * @param [type] $remark  [description]
     */
    public function setRemark($path, $version, $remark)
    {
      $keyArray = array('path'=>$path, 'version'=>$version);
      $paraArray = array('remark'=>$remark);
      $result = $this->dataBase->updateValue($keyArray, $paraArray);
      return $result;
    }

    /**
     * [getProductMap 获取业务名称映射表]
     * @return [type] [description]
     */
    public function getProductMap()
    {
        $sql = "select * from ProductNameMap";
        $keys = array();
        $resultArray = $this->dataBase->executeSql($sql, $keys);
        return $resultArray;
    }

    /**
     * [addProductMap 添加业务名称映射]
     * @param [type] $productList [description]
     */
    public function addProductMap($productList)
    {
        if (!is_array($productList)) {
            return false;
        }
        $sql = "insert ignore ProductNameMap(product, chinese) values ";
        $keys = array();
        $count = 0;
        foreach ($productList as $value) {
            // $sql .= "('{$value['product']}', '{$value['chinese']}'),";
            $sql .= "(:product$count, :chinese$count),";
            $keys["product$count"] = $value['product'];
            $keys["chinese$count"] = $value['chinese'];
            $count ++;
        }
        $sql = rtrim($sql, ",");
        $result = $this->dataBase->executeSql($sql, $keys, false);
        return $result;
    }

    /**
     * [deleteProductMap 删除业务名称映射]
     * @param [type] $productList [description]
     */
    public function deleteProductMap($productList)
    {
        if (!is_array($productList)) {
            return false;
        }
        $sql = "delete from ProductNameMap where product in (";
        $keys = array();
        foreach ($productList as $index => $value) {
            $sql .= ":value$index,";
            $keys["value$index"] = $value;
        }
        $sql = rtrim($sql, ",");
        $sql .= ")";
        $result = $this->dataBase->executeSql($sql, $keys, false);
        return $result;
    }

 }

