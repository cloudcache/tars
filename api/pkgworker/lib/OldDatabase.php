<?php

class Database
{

    private $host;
    private $user;
    private $password;
    private $name;
    private $port;
    private $tableName;
    private $logger;

    function __construct($host, $port, $user, $password, $dbname, $table_name=null)
    {
        require_once __DIR__ .'/log4php/Log.php';
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->name = $dbname;
        $this->port = $port;
        $this->tableName = $table_name;
        $this->logger = new Log('Database');
        $this->logger = $this->logger->getLogger();
        $this->logger->info("$host $port $user $password $dbname $table_name");
    }

    /**
     * [connect_db 连接数据库]
     * @return [type] [description]
     */
    public function connectDatabase()
    {
        $mysqli = new mysqli($this->host,$this->user,$this->password,$this->name,$this->port);
        /* check connection */
        if ($mysqli->connect_errno)
        {
            printf("Connect failed: %s\n", $mysqli->connect_error);
            // $this->log("connect_db_info fail");
            $error = "connect_db_info fail";
            return NULL;
            exit();
        }
        $sql = "SET NAMES 'utf8'";
        $result = $mysqli->query($sql);
        return $mysqli;
    }

    /**
     * [executeSql 执行sql语句]
     * @param  [string] $sql [sql]
     * @return [type]      [description]
     */
    public function executeSql($sql, $collectData=true)
    {
        $this->logger->info("sql: $sql");
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
        $mysqli = $this->connectDatabase();
        $values = array_values($paraArray);
        $keys = array_keys($paraArray);
        $sql = 'INSERT INTO `'.$tableName.'` (`'.implode('`,`', $keys).'`) VALUES (\''.implode('\',\'', $values).'\')';
        $ret = $mysqli->query($sql);
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
        $sql = "select * from $tableName where ";
        foreach ($paraArray as $key => $value) {
            $sql .= " `$key` = '$value' and ";
        }
        $sql = trim(trim($sql, ' '), 'and');

        $mysqli = $this->connectDatabase();
        $result = $mysqli->query($sql);
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

        $sql = "update `$tableName` set ";
        foreach ($paraArray as $key => $value) {
            $sql.="`$key` = '$value',";
        }
        $sql = trim($sql, ',');

        $sql .= " where ";
        foreach ($keyArray as $key => $value) {
            $sql .= " `$key` = '$value' and ";
        }
        $sql = trim(trim($sql, ' '), 'and');
        // var_dump($sql);
        $mysqli = $this->connectDatabase();
        $ret = $mysqli->query($sql);
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
        $sql = "delete from $tableName where ";
        foreach ($keyArray as $key => $value) {
            $sql .= " `$key` = '$value' and ";
        }
        $sql = rtrim(trim($sql, ' '), 'and');
        // var_dump($sql);
        $mysqli = $this->connectDatabase();
        $result = $mysqli->query($sql);
        return $result;
    }
}
