<?php
namespace user\src;

use publicsrc\conf\Conf;
use publicsrc\conf\PrivateConf;
use publicsrc\src\Package;
use publicsrc\src\MagicTool;
use publicsrc\src\ExecShell;
use publicsrc\lib\Log;
use publicsrc\lib\Database; 
use Flight;

class PkgUser 
{

    public function __construct()
    {
        //$host, $user, $password, $name, $port, $table_name=null)
        $this->dataBase = new Database(
                Conf::get('pkg_db_host'),
                Conf::get('pkg_db_port'),
                Conf::get('pkg_db_user'),
                Conf::get('pkg_db_password'),
                Conf::get('pkg_db_name'),
                Conf::get('pkg_db_info_table'));
    }
    public function addPkgAdmin()
    {
        $roleInfo = Flight::request()->data->getData();
        $needPara = array('name', 'product','role','username');
        $this->checkParameter($needPara, $roleInfo);
        extract($roleInfo);
        $select  = array('role'=>$role);
        $ret = $this->dataBase->selectValue($select ,'t_RolePower');
        if(!$ret)
        {
            $result = array('status'=>-1,
                'msg'=>'role not exists',
                );
            Flight::json($result,400); 
        }
        foreach($username as $u)
        {
            $admin = array(
                    'product'=>$product,
                    'name'=>$name,
                    'role'=>$role,
                    'user_name'=>$u,
                    );
            $admin['status'] = 100;
            $ret = $this->dataBase->insertValue($admin,'t_PkgRole');
            if(!$ret)
            {
                $result = array('status'=>-1,
                        'msg'=>'insert to db error',
                        );
                Flight::json($result,500); 
            }
        }
        $result = array('status'=>0,
                'msg'=>'ok',
                );
        Flight::json($result,200); 
    }
    public function delPkgAdmin()
    {
        $roleInfo = Flight::request()->query->getData();
        $needPara = array('name', 'product','role','username');
        $this->checkParameter($needPara, $roleInfo);
        extract($roleInfo);
        foreach($username as $u)
        {
            $admin = array(
                    'product'=>$product,
                    'name'=>$name,
                    'role'=>$role,
                    'user_name'=>$u,
                    );
            $ret = $this->dataBase->deleteValue($admin,'t_PkgRole');
            if(!$ret)
            {
                $result = array('status'=>-1,
                        'msg'=>'delete from db error',
                        );
                Flight::json($result,500); 
            }
        }
        $result = array('status'=>0,
                'msg'=>'ok',
                );
        Flight::json($result,200); 
    }
    public function getUserRole()
    {
        $roleInfo = Flight::request()->query->getData();
        $needPara = array('name', 'product','username');
        $this->checkParameter($needPara, $roleInfo);
        extract($roleInfo);
        //$sql = "SELECT distinct  `role` from `t_PkgRole` WHERE `product` = '".$product."' and `name` = '".$name."' and `user_name` = '".$username."' and `status` = 100";
        $sql = "SELECT distinct  `role` from `t_PkgRole` WHERE `product` = :product and `name` = :name and `user_name` = :username and `status` = 100";
        $para =array(
            'product'=>$product,
            'name'=>$name,
            'username'=>$username
            );
        echo $sql;
        var_dump($para);
        exit;
        $roles= $this->dataBase->executeSql($sql,$para,true);
        if(!$roles)
        {
            $result = array('status'=>-1,
                    'msg'=>'get from db error',
                    );
            Flight::json($result,500); 
        }
        foreach($roles as $role)
        {
            $data["role_list"][] = $role['role'];
        }
        $result = array('status'=>0,
                'msg'=>'ok',
                'data'=>$data,
                );
        Flight::json($result,200); 
    }
    public function getPkgUser()
    {
        $roleInfo = Flight::request()->query->getData();
        $needPara = array('name', 'product');
        $this->checkParameter($needPara, $roleInfo);
        extract($roleInfo);
        //$sql = "SELECT `role`,`user_name` from `t_PkgRole` WHERE `product` = '".$product."' and `name` = '".$name."'  and `status` = 100";
        $sql = "SELECT `role`,`user_name` from `t_PkgRole` WHERE `product` = :product and `name` = :name and `status` = 100";
        $para =array(
            'product'=>$product,
            'name'=>$name,
            );
        $roles= $this->dataBase->executeSql($sql,$para,true);
        if(empty($roles))
        {
            $result = array('status'=>0,
                    'msg'=>'ok',
                    'data'=>array(),
                    );
            Flight::json($result,200); 
        }
        foreach($roles as $role)
        {
            $data[$role['role']][] = $role['user_name'];
        }
        $result = array('status'=>0,
                'msg'=>'ok',
                'data'=>$data,
                );
        Flight::json($result,200); 
    }
    public function authenticate()
    {
        $roleInfo = Flight::request()->query->getData();
        $needPara = array('name', 'product','act','username');
        $this->checkParameter($needPara, $roleInfo);
        extract($roleInfo);
        //$sql = "SELECT distinct `privilege` from t_RolePower  where `role` in (SELECT distinct  `role` from `t_PkgRole` WHERE `product` = '".$product."' and `name` = '".$name ."' and `user_name` = '".$username."' and `status` = 100)";  
        $sql = "SELECT distinct `privilege` from t_RolePower  where `role` in (SELECT distinct  `role` from `t_PkgRole` WHERE `product` = :product and `name` = :name and `user_name` = :username and `status` = 100)";  
        $para =array(
            'product'=>$product,
            'name'=>$name,
            'username'=>$username
            );
        $results = $this->dataBase->executeSql($sql,$para,true);
        $privilege_list = array();
        foreach($results as $p)
        {
            $privilege_list[] = $p['privilege'];     
        }
        if($act == 'create' && in_array('develop',$privilege_list))
        {
            $result = array('status'=>0,
                'msg'=>'ok',
                'data'=>array('privilege'=>true),
                );
        }
        else
        {
            $result = array('status'=>1,
                    'msg'=>'no privilege',
                    'data'=>array('privilege'=>false),
                    );
        }
        Flight::json($result,200); 
    }
    public function changePkgAtt()
    {
        $roleInfo = Flight::request()->data->getData();
        $needPara = array('name', 'product','public');
        $this->checkParameter($needPara, $roleInfo);
        extract($roleInfo);
        //$sql = "replace INTO t_PkgAttribute (`product`,`name`,`public`) values ('".$product."','".$name."','".$public."')";
        $sql = "replace INTO t_PkgAttribute (`product`,`name`,`public`) values (:product,:name,:public)";
        $para =array(
            'product'=>$product,
            'name'=>$name,
            'public'=>$public,
            );
        $ret = $this->dataBase->executeSql($sql,$para,false);
        if(!$ret)
        {
            $result = array('status'=>-1,
                'msg'=>'change error',
                );
            Flight::json($result,500); 
        }
        else
        {
            $result = array('status'=>0,
                'msg'=>'ok',
                );
            Flight::json($result,200); 
        }
    }
    public function getPkgAtt()
    {
        $roleInfo = Flight::request()->query->getData();
        $needPara = array('name', 'product');
        $this->checkParameter($needPara, $roleInfo);
        extract($roleInfo);
        //$sql = "select public from t_PkgAttribute where `product`='".$product."' and `name`='".$name."'";
        $sql = "select public from t_PkgAttribute where `product`=:product and `name`=:name";
        $para =array(
            'product'=>$product,
            'name'=>$name,
            );
        $ret = $this->dataBase->executeSql($sql,$para,true);
        if(!$ret)
        {
            $result = array('status'=>0,
                'msg'=>'ok',
                'data'=>array('public'=>1),
                );
        }
        else
        {
            $result = array('status'=>0,
                'msg'=>'ok',
                'data'=>$ret[0],
                );
        }
            Flight::json($result,200); 
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

}
