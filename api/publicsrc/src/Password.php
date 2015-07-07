<?php
namespace publicsrc\src;

use publicsrc\lib\Database;
use publicsrc\conf\Conf;

 class Password
 {
    private $dataBase;
    private $privateKey;
    private $iv;
    // private $ivSize;

    private $aes256Key;

    function __construct()
    {
        //$host, $user, $password, $name, $port, $table_name=null)
        $this->dataBase = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_device_table'));
        $this->table =  Conf::get('pkg_db_device_table');
        $this->privateKey = Conf::get('private_key');
        $this->aes256Key = hash("SHA256", $this->privateKey, true);
    }

    public function getVector()
    {
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC), MCRYPT_RAND);
        $this->iv = $iv;
        return $iv;
    }

    /**
     * [encryption 密码加密 AES]
     * @return [type] [description]
     */
    public function encryption($key, $iv)
    {
        $sSecretKey = $this->aes256Key;
        return rtrim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $sSecretKey, $key, MCRYPT_MODE_CBC, $iv)), "\0\3");
    }

    /**
     * [decryption 密码解密]
     * @return [type] [description]
     */
    public function decryption($encryptedKey, $iv)
    {
        $sSecretKey = $this->aes256Key;
        return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $sSecretKey, base64_decode($encryptedKey), MCRYPT_MODE_CBC, $iv), "\0\3");
    }

    /**
     * [insertDevice 插入新机器]
     * @param  [type] $deviceId [机器id / ip]
     * @param  [type] $password [密码]
     * @return [type]           [description]
     */
    public function insertDevice($deviceId, $password)
    {
        $iv = $this->getVector();
        $password = $this->encryption($password, $iv);
        $toInsert = array(
            'deviceId'=>$deviceId,
            'password'=>$password,
            'vector'=>base64_encode($iv)
            );
        $resultBool = $this->dataBase->insertValue($toInsert);
        if ($resultBool == false) {
            $keyArray = array('deviceId'=>$deviceId);
            $resultBool = $this->dataBase->updateValue($keyArray, $toInsert);
        }
        return $resultBool;
    }

    /**
     * [getDevice 获取设备信息]
     * @param  [type] $deviceId [机器id / ip]
     * @return [type]           [description]
     */
    public function getDevice($deviceId)
    {
        $toSelect = array('deviceId'=>$deviceId);
        $resultArray = $this->dataBase->selectValue($toSelect);
        if (!empty($resultArray)) {
            $tmpArray = $resultArray[0];
            return $tmpArray;
        } else {
            return null;
        }
    }

    /**
     * 根据idc 或 业务 获取设备信息
     */
    public function getDeviceByIdcOrBusiness($idc=null, $business=null)
    {
        $toSelect = array();
        $sql = "select deviceId, idc, business from " . $this->table ;
        $sql .= ' where ';
        $para = array();
        if (!empty($idc)) {
            $para['idc'] = $idc;
            $sql .= ' idc=:idc and ';
        } 
        if (!empty($business)) {
            $para['business'] = $business;
            $sql .= ' business=:business and ';

        }
        $sql .= '1';
        $resultArray = $this->dataBase->executeSql($sql, $para);
        if (!empty($resultArray)) {
            return $resultArray;
        } else {
            return null;
        }
    }

    /**
     * 根据idc 或 业务 获取设备信息
     */
    public function updateDevice($deviceArray)
    {
        foreach ($deviceArray as $device) {
            $para = array(
                'deviceId'=>$device['deviceId'],
                'idc'=>$device['idc'],
                'business'=>$device['business']
                );
            $key = array('deviceId'=>$device['deviceId']);
            $result = $this->dataBase->insertValue($para);
            if ($result == false) {
                $result = $this->dataBase->updateValue($key, $para);
            }
            if ($result == false) {
                return false;
            }
        }
        return true;
    }

    /**
     * 根据idc 或 业务 删除设备信息
     */
    public function deleteDevice($deviceArray)
    {
        foreach ($deviceArray as $device) {
            $para = array(
                'deviceId'=>$device['deviceId']
                );
            $key = array('deviceId'=>$device['deviceId']);
            $result = $this->dataBase->deleteValue($key);
            if ($result == false) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取idc列表
     */
    public function getDeviceIdc($business=null)
    {
        $sql = "select distinct (idc) from " . $this->table ;
        if (!empty($business)) {
            $sql .= " where business=:business";
        }
        $para = array('business'=>$business);
        $resultArray = $this->dataBase->executeSql($sql, $para);
        if (!empty($resultArray)) {
            return $resultArray;
        } else {
            return null;
        }
    }

    /**
     * 获取idc列表
     */
    public function getDeviceBusiness($idc=null)
    {
        $sql = "select distinct (business) from " . $this->table ;
        if (!empty($idc)) {
            $sql .= " where idc=:idc";
        }
        $para = array('idc'=>$idc);
        $resultArray = $this->dataBase->executeSql($sql, $para);
        if (!empty($resultArray)) {
            return $resultArray;
        } else {
            return null;
        }
    }


    /**
     * [getDevicePassword 获取设备明文密码]
     * @param  [type] $deviceId [description]
     * @return [type]           [description]
     */
    public function getDevicePassword($deviceId)
    {
        $infoArray = $this->getDevice($deviceId);
        if ($infoArray == null) {
            return null;
        }
        $iv = base64_decode($infoArray['vector']);
        $password = $infoArray['password'];
        $decryptedPassword = $this->decryption($password, $iv);
        return $decryptedPassword;
    }
 }

