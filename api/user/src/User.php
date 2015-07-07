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

class User
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
        if (isset($_SERVER['HTTP_TARS_TOKEN'])) {
            $this->token = $_SERVER['HTTP_TARS_TOKEN'];
        } else {
            $this->token = null;
        }
    }
    public function register()
    {
        $regInfo = Flight::request()->data->getData();
        $needPara = array('username', 'role','password');
        $this->checkParameter($needPara, $regInfo);
        extract($regInfo);
        $select  = array('username'=>$username);
        $ret = $this->dataBase->selectValue($select ,'t_User');
        if(!empty($ret))
        {
            $result = array('code'=>1002,
                    'error'=>'user has exists',
                    );
            Flight::json($result,400); 
        }
        $regInfo['regTime'] = date('Y-m-d H:i:s');
        $salt=base64_encode(md5(microtime(true)));
        $regInfo['password']=sha1($regInfo['password'].$salt);
        $regInfo['salt'] = $salt;
        $ret = $this->dataBase->insertValue($regInfo,'t_User');
        if(!$ret)
        {
            $result = array('code'=>1003,
                    'error'=>'insert to db error',
                    );
            Flight::json($result,500);
        }
        $result = array('code'=>0,
                'error'=>'ok',
                );
        Flight::json($result,200);
    }
    public function batRegister()
    {
        $userInfo = $this->verifyToken($this->token);
        $userInfo = $this->verifyToken($this->token);
        if($userInfo['role'] != 'admin')
        {
            $result = array('code'=> 1004,
                    'error'=>'role not admin ,permission denied',
                    );
            Flight::json($result,400);
        }
        $regInfos = Flight::request()->data->getData();
        foreach($regInfos as $regInfo)
        {
            $needPara = array('username','role', 'password');
            $this->checkParameter($needPara, $regInfo);
            extract($regInfo);
            $select  = array('username'=>$username);
            $ret = $this->dataBase->selectValue($select ,'t_User');
            if(!empty($ret))
            {
                $result = array('code'=>1005,
                        'error'=>'user has exists',
                        );
                Flight::json($result,400);
            }
            $regInfo['regTime'] = date('Y-m-d H:i:s');
            $salt=base64_encode(md5(microtime(true)));
            $regInfo['password']=sha1($regInfo['password'].$salt);
            $regInfo['salt'] = $salt;
            $ret = $this->dataBase->insertValue($regInfo,'t_User');
            if(!$ret)
            {
                $result = array('code'=>1006,
                        'error'=>'insert to db error',
                        );
                Flight::json($result,500);
            }
        }
        $result = array('code'=>0,
                'error'=>'ok',
                );
        Flight::json($result,200);
    }
    public function login()
    {
        $loginInfo = Flight::request()->data->getData();
        $needPara = array('username', 'password');
        $this->checkParameter($needPara, $loginInfo);
        extract($loginInfo);
        $select  = array('username'=>$username);
        $ret = $this->dataBase->selectValue($select ,'t_User');
        if(empty($ret))
        {
            $result = array('code'=>1007,
                    'error'=>'user not exists',
                    );
            Flight::json($result,400);
        }
        $userInfo = $ret[0];
        if(sha1($loginInfo['password'].$userInfo['salt']) === $userInfo['password'])
        {
            $select  = array('username'=>$username);
            //$sql = 'select * from t_Token where username="'.$username.'" and expireTime>"'.date('Y-m-d H:i:s').'"';
            $sql = 'select * from t_Token where username=:username and expireTime>:time';
            $para= array('username'=>$username,
                'time'=>date('Y-m-d H:i:s'),
                );
            $ret = $this->dataBase->executeSql($sql,$para,true);
            if(!empty($ret))
            {
                $token = $ret[0]['token'];
                $result = array('token'=>$token);
                Flight::json($result,200);

            }
            $token = md5(uniqid(rand()).microtime(true));
            $expireTime = date('Y-m-d H:i:s',time()+36000);
            //$sql = 'replace into t_Token (`username`,`token`,`expireTime`)
            //    values("'.$username.'","'.$token.'","'.$expireTime.'")';
            $sql = 'replace into t_Token (`username`,`token`,`expireTime`)
                values(:username,:token,:expireTime)';
            $para = array(
                'username'=>$username,
                'token'=>$token,
                'expireTime'=>$expireTime,
                );
            $ret = $this->dataBase->executeSql($sql,$para,false);
            if(!$ret)
            {
                $result = array('code'=>-1006,
                        'error'=>'save token error',
                        );
                Flight::json($result,500);
            }
            $result = array('token'=>$token);
            Flight::json($result,200);
        }
        $result = array('code'=>-1001,
                'error'=>'password error',
                );
        Flight::json($result,400);
    }
    public function getUserInfo()
    {
        $loginInfo = Flight::request()->query->getData();
        $userInfo = $this->verifyToken($this->token);
        unset($userInfo['password']);
        unset($userInfo['salt']);
        unset($userInfo['updateTime']);
        Flight::json($userInfo,200);
    }
    public function updateUserInfo()
    {
        $updateInfo = Flight::request()->data->getData();
        $needPara = array();
        //$optionPara = array('username','role','password','old_password');
        //$this->checkParameter($needPara, $updateInfo,$optionPara);
        extract($updateInfo);
        $userInfo = $this->verifyToken($this->token);
        if(!empty($username)
            && ($userInfo['username'] != $username)
            && (($userInfo['role'] != 'admin') || isset($optionPara['password'])))
        {
            $result = array('code'=>-1009,
                    'error'=>'permission denied',
                    );
            Flight::json($result,400);
        }
        if(empty($username))
        {
            $username = $userInfo['username'];
        }
        else
        {
            unset($updateInfo['username']);
        }

        //change password
        if(!empty($password))
        {
            if(empty($old_password))
            {
                $result = array('code'=>-1011,
                        'error'=>'old password error',
                        );
                Flight::json($result,400);
            }

            if((($userInfo['role'] !='admin') || ($userInfo['username'] == $username)) && sha1($old_password.$userInfo['salt']) !== $userInfo['password'])
            {
                $result = array('code'=>-1011,
                        'error'=>'old password error',
                        );
                Flight::json($result,400);
            }
            $salt=base64_encode(md5(microtime(true)));
            $pwdInfo['password']=sha1($password.$salt);
            $pwdInfo['salt'] = $salt;
            $key = array('username'=>$username);
            $ret = $this->dataBase->updateValue($key,$pwdInfo,'t_User');
            if(!$ret)
            {
                $result = array('code'=> -1010,
                        'error'=>'update password info error',
                        );
                Flight::json($result,500);
            }
        }

        unset($updateInfo['password']);
        unset($updateInfo['old_password']);
        $key = array('username'=>$username);
        if(!empty($updateInfo))
        {
            $ret = $this->dataBase->updateValue($key,$updateInfo,'t_User');
            if(!$ret)
            {
                $result = array('code'=> -1010,
                        'error'=>'update user info error',
                        );
                Flight::json($result,500);
            }
        }
        Flight::json($updateInfo,200);
    }
    public function verifyToken($token)
    {
        $select  = array('token'=>$token);
        $ret = $this->dataBase->selectValue($select ,'t_Token');
        if(empty($ret))
        {
            $result = array('code'=> -1007,
                    'error'=>'user not login',
                    );
            Flight::json($result,400);
        }
        $tokenInfo = $ret[0];
        if(strtotime($tokenInfo['expireTime']) < time())
        {
            $result = array('code'=> -1008,
                    'error'=>'token time out',
                    );
            Flight::json($result,404);
        }
        $select  = array('username'=>$tokenInfo['username']);
        $ret = $this->dataBase->selectValue($select ,'t_User');
        if(empty($ret))
        {
            $result = array('code'=> -1002,
                    'error'=>'user not exists',
                    );
            Flight::json($result,400);
        }
        $userInfo = $ret[0];
        return $userInfo;
    }
    public function getAllUser()
    {
        $userInfo = $this->verifyToken($this->token);
        $sql = 'select username,role from t_User';
        $users = $this->dataBase->executeSql($sql,array(),true);
        Flight::json($users,200);
    }
    public function deleteUsers()
    {
        $info= Flight::request()->query->getData();
        $needPara = array('users');
        $this->checkParameter($needPara, $info);
        $userInfo = $this->verifyToken($this->token);
        if($userInfo['role'] != 'admin')
        {
            $result = array('code'=> 1004,
                    'error'=>'role not admin ,permission denied',
                    );
            Flight::json($result,400);
        }
        $i = 0;
        $para = array();
        $var_list = array();
        foreach($info['users'] as $user)
        {
            $var = ':user'.$i;
            $para['user'.$i] = $user;
            $var_list[]=$var;
            $i++;
        }
        $sql = 'delete from t_User where username in ('.implode(',',$var_list).')';
        echo $sql;
        var_dump($para);
        $ret = $this->dataBase->executeSql($sql,$para,false);
        exit;
        Flight::json($ret,200);
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
