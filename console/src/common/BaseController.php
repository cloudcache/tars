<?php
/**
 * 控制器基类
 * @author steveswwang
 */

namespace common;
use Flight;

abstract class BaseController {
    protected $routes = array();

    public function __construct() {

        foreach ($this->routes as $path => $method) {
            Flight::route($path, array($this, $method), strpos($path, '*') !== false);
        }
    }
}
