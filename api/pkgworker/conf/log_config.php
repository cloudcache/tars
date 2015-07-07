<?php
return array(
        'rootLogger' => array(
            'level' => 'DEBUG',
            'appenders' => array('default'),
            ),
        'appenders' => array(
            'default' => array(
                'datePattern' => "Ymd",
                'class' => 'LoggerAppenderDailyFile',
                'params' => array(
                    'file' => '../log/pkgWorker_%s.log',
                    'append' => true
                    ),
                'layout' => array(
                    'class' => 'LoggerLayoutPattern',
                    'params' => array(
                    'conversionPattern' => "%d{Y-m-d H:i:s.u} %-5p [%t] %L %c: %m%n",
                    ),
                    ),
                ),
            ),
        );
