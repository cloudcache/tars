<?php

class Log
{
    private $logger;

    function __construct($fileName)
    {
        require_once __DIR__ .'/Logger.php';
        $dir = __DIR__;
        $monthDir = date("Ym");
        $filePath = $dir."/../../log/$monthDir/pkgworker"."_%s.log";
        $fileDir = dirname(basename($filePath));
        if (!is_dir($fileDir)) {
            shell_exec("mkdir -p $fileDir");
        }
        //var_dump($filePath);
        $default_config = array(
            'rootLogger' => array(
                'level' => 'DEBUG',
                'appenders' => array('default')),
            'appenders' => array(
                'default' => array(
                    'datePattern' => "Ymd",
                    'class' => 'LoggerAppenderDailyFile',
                'params' => array(
                    'file' => $filePath,
                    'append' => true),
                'layout' => array(
                    'class' => 'LoggerLayoutPattern',
                    'params' => array(
                        'conversionPattern' => "%d{Y-m-d H:i:s.u} %-5p [%t] %L %c: %m%n"
                        )))));
         Logger::configure($default_config);
         // $this->logger = Logger::getRootLogger();
         $this->logger = Logger::getLogger($fileName);;
    }

    public function getLogger()
    {
        return $this->logger;
    }

}
