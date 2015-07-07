<?php
namespace publicsrc\lib;


class Log2
{
    public $log;
    function __construct($config)
    {
        require_once __DIR__ .'/log4php/Logger.php';
        $this->log = new Logger($config);
    }

    public function getLogger()
    {
        return $this->log;
    }

}
