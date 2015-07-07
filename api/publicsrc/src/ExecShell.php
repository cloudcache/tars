<?php
namespace publicsrc\src;

use publicsrc\conf\Conf;
use publicsrc\lib\Log;
class ExecShell
{
    private $execName;
    private $header;
    private $delimiter;
    private $path;
    private $cmdStr;
    public $result;
    public $done;
    public $rtCode;
    public $output;

    public function __construct($execName, $path = null, $header = "result", $delimiter = "%%")
    {
        if ($path == null) {
            $path = Conf::get('tool_shell');
        }
        $this->path = $path;
        $this->execName = $execName;
        $this->header = $header;
        $this->delimiter = $delimiter;
        // initialize
        $this->result = array();
        $this->done = false;
        $this->output = array();
        $this->rtCode = NULL;
        $this->log = new Log(__CLASS__);
    }

    public function setExecName($execName)
    {
        $this->execName = $execName;
    }

    public function run()
    {
        $parameterList = func_get_args();
        foreach ($parameterList as & $value) {
            $value = '"'.$value.'"';
        }
        $parameterStr = join(" ", $parameterList);
        $cmdStr = "cd ".$this->path.";";
        $cmdStr = $cmdStr.'./'.$this->execName." ".$parameterStr;
        $this->log->info("Shell cmd: $cmdStr");
        $this->output = array();
        $this->cmdStr = $cmdStr ;
        exec($cmdStr, $this->output, $this->rtCode);
        for ($i=count($this->output)-1; $i>=0; --$i) {
            if (strpos($this->output[$i], $this->header) === 0) {
                $this->result = explode($this->delimiter, $this->output[$i]);
                break;
            }
        }
        $this->log->info("Shell run code: " . $this->rtCode);
        $this->log->info("Shell run output: " . json_encode($this->output));
    }

    public function runBackground($parameterList = null)
    {
        if ($parameterList == null) {
            $parameterList = func_get_args();
        }
        foreach ($parameterList as & $value) {
            $value = '"'.$value.'"';
        }
        $parameterStr = join(" ", $parameterList);
        $cmdStr = "cd ".$this->path.";";
        $cmdStr = $cmdStr.'./'.$this->execName." ".$parameterStr." > /dev/null 2>&1 &";
        $this->output = array();
        $this->cmdStr = $cmdStr;
        exec($cmdStr, $this->output, $this->rtCode);
    }

    public function debug($file, $append = true)
    {
        if ($append === true) {
            $flags = FILE_APPEND;
        }
        file_put_contents($file, "\n".$this->cmdStr, $flags);
        file_put_contents($file, "\n".join("\n",$this->output)."\n", $flags);
    }

    public function getOutput()
    {
        foreach ($this->output as $key => &$value) {
            // $value = iconv("GBK", "UTF-8//IGNORE", utf8_encode($value));
            // $value = iconv("GBK", "UTF-8//IGNORE", $value);
            $value = mb_convert_encoding($value,'UTF-8','utf-8,GBK,GB2312');
            $value = $value;
        }
        return $this->output;
    }

    public function result($index = null, $conv = true)
    {
        if (null === $index) {
            return $this->result;
        }
        if (true === $conv) {
            return iconv("GBK", "UTF-8//IGNORE", $this->result[$index]);
        } else {
            return $this->result[$index];
        }
    }

    public function rtCode()
    {
        return $this->rtCode;
    }
};

