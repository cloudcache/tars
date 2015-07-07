<?php
namespace publicsrc\src;

use publicsrc\lib\Database;
use publicsrc\conf\Conf;

class UserFavorite
{
    private $dataBase;

    function __construct()
    {
        //$host, $user, $password, $name, $port, $table_name=null)
        $this->dataBase = new Database(
            Conf::get('pkg_db_host'),
            Conf::get('pkg_db_port'),
            Conf::get('pkg_db_user'),
            Conf::get('pkg_db_password'),
            Conf::get('pkg_db_name'),
            Conf::get('pkg_db_usefav_table'));
    }

    public function insert($userId, $product, $name)
    {
        $toInsert = array('userId'=>$userId, 'product'=>$product, 'name'=>$name);
        $hasResult = $this->dataBase->selectValue($toInsert);
        if (!empty($hasResult)) {
            return true;
        } else {
            $resultBool = $this->dataBase->insertValue($toInsert);
            return $resultBool;
        }
    }

    public function remove($userId, $product, $name)
    {
        $toDelete = array('userId'=>$userId, 'product'=>$product, 'name'=>$name);
        $resultBool = $this->dataBase->deleteValue($toInsert);
        return $resultBool;
    }

    public function getUserFavorite($userId)
    {
        $toFind = array('userId'=>$userId);
        $resultList = $this->dataBase->selectValue($toFind);
        return $resultList;
    }
}

