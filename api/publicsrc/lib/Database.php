<?php
namespace publicsrc\lib;
use PDO;

class Database
{

    private $host;
    private $user;
    private $password;
    private $name;
    private $port;
    private $tableName;
    private $log;
    private $db;

    function __construct($host, $port, $user, $password, $dbname, $tableName=null)
    {
        $this->log = new Log(__CLASS__);
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->name = $dbname;
        $this->port = $port;
        $this->tableName = $tableName;
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=UTF8";
        $this->db = new PDO($dsn, $this->user, $this->password);
        $this->log->info("$host $port $user $password $dbname $tableName");
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * [createTable 创建按天分表的表格 若不存在则新建 $table_yyyymmdd]
     * @param  [type] $tableName [description]
     * @param  [type] $prefix    [description]
     * @return [type]            [description]
     */
    public function createTable($tableName, $prefix)
    {
        //默认已经创建基表直接复制
        $basic = $tableName . "_basic";
        $tableName = $tableName . "_" . $prefix;
        $sql = "CREATE TABLE if not exists `".$tableName."` like $basic";
        // $this->mysqlConnect();
        // $ret = mysql_query($sql);
        $ret = executeSql($sql, false);
        return $tableName;
    }

    public function executeSql($sql, array $params, $collectData=true) {
        $stmt = $this->db->prepare($sql);
        $this->log->info($sql);
        $this->log->info(json_encode($params));
        foreach ($params as $key => $value) {
            $mustWord = preg_match('/^\w+$/', $key, $matches);
            if ($mustWord != 1) {
                $this->log->info("sql key not a word");
                return false;
            }
            $stmt->bindValue($key, $value);
        }
        $ret = $stmt->execute();
        if ($collectData == true) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->log->info("executeSql result");            
            $this->log->info(json_encode($result));
            return $result;
        } else {
            return $ret;
        }
        
        /*
        使用：
        $params = array($pro, $nam);
        $ret = $db->executeSql('SELECT xx FROM xxx WHERE a = ? AND b = ?', $params);

        $sql = "select * from test  where `name`=:name";
        $arr = array('name'=>'pekingg');
        $ret = $db->executeSql($sql, $arr);
        */
    }


    /**
     * [executeSql 执行sql语句]
     * @param  [string] $sql [sql]
     * @return [type]      [description]
     */
    public function executeSqlOld($sql, $collectData=true)
    {
        $mysqli = $this->connectDatabase();
        $result = $mysqli->query($sql);
        if ($collectData == false) {
            return $result;
        }
        $resultArray = array();
        if($result != false)
        {
            while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $resultArray[] = $row;
            }
        }
        return $resultArray;
    }

    /**
     * [insertValue 插入数据库]
     * @param  [array] $paraArray  [键值对]
     * @param  string $tableName [表名]
     * @return [type]            [description]
     */
    public function insertValue($paraArray, $tableName='')
    {
        if (empty($tableName) && empty($this->tableName)) {
            return false;
        } elseif (empty($tableName)) {
            $tableName = $this->tableName;
        }
        // INSERT INTO table(field1,field2,field3,field4,field5) VALUES(:field1,:field2,:field3,:field4,:field5)
        $sql = "INSERT INTO $tableName (";
        $subsql = " VALUES (";
        $countIndex = 0;
        foreach ($paraArray as $key => $value) {
            $mustWord = preg_match('/^\w+$/', $key, $matches);
            if ($mustWord != 1) {
                $this->log->info("sql key not a word");
                return false;
            }
            $sql .= "`$key`";
            $subsql .= ":$key";
            $countIndex ++;
            if (count($paraArray) == $countIndex) {
                $sql .= ") ";
                $subsql .= ")";
            } else {
                $sql .= ", ";
                $subsql .= ",";
            }
        }     
        $sqlAll = $sql . $subsql; 
        $this->log->info("pdo sql : $sqlAll");
        $stmt = $this->db->prepare($sqlAll);
        foreach ($paraArray as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $ret = $stmt->execute();
        return $ret;
    }

    /**
     * [selectValue 选择数据]
     * @param  [type] $paraArray [选择符合的键值对]
     * @param  string $tableName [可留空]
     * @return [type]            [description]
     */
    public function selectValue($paraArray, $tableName='')
    {
        if (empty($tableName) && empty($this->tableName)) {
            return false;
        } elseif (empty($tableName)) {
            $tableName = $this->tableName;
        }
        //SELECT * FROM table WHERE id=:id AND name=:name
        $sql = "SELECT * FROM $tableName WHERE ";
        $countIndex = 0;
        foreach ($paraArray as $key => $value) {
            $mustWord = preg_match('/^\w+$/', $key, $matches);
            if ($mustWord != 1) {
                $this->log->info("sql key not a word");
                return false;
            }
            $sql .= "$key=:$key ";
            $countIndex ++;
            if (count($paraArray) != $countIndex) {
                $sql .= " AND ";
            }
        }     
        $this->log->info("pdo sql : $sql");
        $stmt = $this->db->prepare($sql);
        foreach ($paraArray as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $ret = $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * [updateValue 更新数据库数据]
     * @param  [type] $keyArray  [原来符合的键值对]
     * @param  [type] $paraArray [需要更新的键值对]
     * @param  string $tableName [可空]
     * @return [type]            [description]
     */
    public function updateValue($keyArray, $paraArray, $tableName='')
    {
        if (empty($tableName) && empty($this->tableName)) {
            return false;
        } elseif (empty($tableName)) {
            $tableName = $this->tableName;
        }

        //UPDATE `tbl_dashboard_item` SET `dlp_id` = :dlp_id, `item_name` = :item_name WHERE `item_id` = :item_id'
        $sql = "UPDATE $tableName SET ";
        $countIndex = 0;
        //set content
        foreach ($paraArray as $key => $value) {
            $mustWord = preg_match('/^\w+$/', $key, $matches);
            if ($mustWord != 1) {
                $this->log->info("sql key not a word");
                return false;
            }
            $sql .= "`$key`=:$key";
            $countIndex ++;
            if (count($paraArray) != $countIndex) {
                $sql .= ", ";
            }
        }   

        $sql .= " WHERE ";
        $countIndex = 0;
        //key content
        foreach ($keyArray as $key => $value) {
            $mustWord = preg_match('/^\w+$/', $key, $matches);
            if ($mustWord != 1) {
                $this->log->info("sql key not a word");
                return false;
            }
            $sql .= "`$key`=:w$key";
            $countIndex ++;
            if (count($keyArray) != $countIndex) {
                $sql .= " AND ";
            }
        }

        $this->log->info("pdo sql : $sql");
        $stmt = $this->db->prepare($sql);
        //set binding
        foreach ($paraArray as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        //key binding
        foreach ($keyArray as $key => $value) {
            $stmt->bindValue("w$key", $value);
        }
        $ret = $stmt->execute();
        return $ret;
    }

    /**
     * [deleteValue 删除数据库数据]
     * @param  [array] $keyArray  [符合的键值对]
     * @param  string $tableName [表名 可空]
     * @return [type]            [description]
     */
    public function deleteValue($keyArray, $tableName='')
    {
        if (empty($tableName) && empty($this->tableName)) {
            return false;
        } elseif (empty($tableName)) {
            $tableName = $this->tableName;
        }

        //DELETE FROM table WHERE id=:id
        $sql = "DELETE FROM $tableName WHERE ";
        $countIndex = 0;
        foreach ($keyArray as $key => $value) {
            $mustWord = preg_match('/^\w+$/', $key, $matches);
            if ($mustWord != 1) {
                $this->log->info("sql key not a word");
                return false;
            }
            $sql .= "`$key`=:$key ";
            $countIndex ++;
            if (count($keyArray) != $countIndex) {
                $sql .= " AND ";
            }
        }     
        $this->log->info("pdo sql : $sql");
        $stmt = $this->db->prepare($sql);
        foreach ($keyArray as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $ret = $stmt->execute();
        return $ret;
    }
}
