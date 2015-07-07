<?php
/**
 * CURL异常
 * @author steveswwang
 */

namespace common;
use Exception;

class CurlError extends Exception {
    /**
     * @var int HTTP状态码
     */
    private $httpStatus;

    /**
     * 构造函数
     * @param string $message 异常消息内容
     * @param int $code 异常代码
     * @param int $httpStatus HTTP状态码
     * @param int $options CURL请求选项
     */
    public function __construct($message = '', $code = 0, $httpStatus = 0, array $options = null) {
        parent::__construct($message, $code);
        $this->httpStatus = $httpStatus;
    }

    /**
     * 获取HTTP状态码
     * @return int
     */
    public function getHttpStatus() {
        return $this->httpStatus;
    }

    /**
     * 将异常对象转换为字符串
     * @return string
     **/
    public function __toString() {
        return "{$this->code} [{$this->httpStatus}] {$this->message}";
    }

    /**
     * 将异常对象转换为关联数组，并将message进行json解析为data
     * @return array
     **/
    public function toArray() {
        return array(
            'code' => $this->code,
            'httpStatus' => $this->httpStatus,
            'data' => json_decode($this->message, true),
        );
    }
}
