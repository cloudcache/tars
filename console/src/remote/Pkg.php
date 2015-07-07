<?php
/**
 * pkg打包相关
 **/

namespace remote;
use Flight;
use common\Curl;
use common\Config;

class Pkg extends Curl {
    public function request($path, array $query = null, array $data = null, $method = 'GET', $timeout = 120, $type = 'json') {
        $url = Config::get('pkg.api_url') . $path;
        $host = Config::get('pkg.api_host');

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $json = $type === 'json';

        if ($data !== null && $json) {
            $header = array(
                'Content-Type: application/json',
            );
        }

        if (! $timeout) {
            $timeout = null;
        }

        $options = compact('url', 'method', 'host', 'header', 'timeout');

        if ($data !== null) {
            if ($json) {
                $options['data'] = json_encode($data);
            } else {
                $options['data'] = $data;
            }
        }

        return $this->curl($options);
    }

    public function get($path, array $query = null) {
        return $this->request($path, $query);
    }

    public function post($path, array $data = null, array $query = null) {
        return $this->request($path, $query, $data, 'POST');
    }

    public function put($path, array $data = null, array $query = null) {
        return $this->request($path, $query, $data, 'PUT');
    }

    public function delete($path, array $query = null) {
        return $this->request($path, $query, null, 'DELETE');
    }

    public function getProductList() {
        return $this->get('query/get_product_map');
    }

    public function storeProductList(array $productList) {
        return $this->post('query/add_product_map', compact('productList'));
    }

    public function removeProductList(array $productIdList) {
        $productList = implode(';', $productIdList);
        return $this->delete('query/delete_product_map', compact('productList'));
    }

    public function searchPackage($name, $product = null) {
        return $this->get('query/search_package', compact('product', 'name'));
    }

    public function getVersionList($product, $name) {
        return $this->get('query/get_package_versionlist', compact('product', 'name'));
    }

    public function getPackageInfo($product, $name, $version = null) {
        return $this->get('query/get_package_information', compact('product', 'name', 'version'));
    }

    public function getInstanceList($path, array $options = null) {
        $query = compact('path');
        if ($options) {
            $query += $options;
        }
        return $this->post('query/get_instance_list', $query);
    }

    public function getInstanceCount($path, array $options = null) {
        $query = compact('path');
        if ($options) {
            $query += $options;
        }
        return $this->get('query/get_instance_countlist', $query);
    }

    public function setRemark($path, $version, $remark) {
        return $this->put('query/set_remark', compact('path', 'version', 'remark'));
    }

    public function checkPackageExist($product, $name) {
        return $this->get('pack/check_package_exist', compact('product', 'name'));
    }

    public function createPackage($product, $name, $frameworkType, $isShell) {
        return $this->post('pack/create', compact('product', 'name', 'frameworkType', 'isShell'));
    }

    public function checkout($product, $name, $version) {
        return $this->put('pack/checkout', compact('product', 'name', 'version'));
    }

    // $options: path, confProduct, confName, confVersion, confRemark, confUser, confAuthor,
    //     confContent, confFrameworkType, isShell, confContent, confStateless, confOS
    public function savePackageConfig($confProduct, $confName, $confVersion, array $options) {
        $data = compact('confProduct', 'confName', 'confVersion');
        $data += $options;
        return $this->post('pack/save_package_config', $data);
    }

    // $options: path, confProduct, confName, confVersion, confRemark, confUser, confAuthor,
    //     confContent, confFrameworkType, isShell, confContent
    public function submitCreatePackage($confProduct, $confName, $confVersion, array $options) {
        $data = compact('confProduct', 'confName', 'confVersion');
        $data += $options;
        return $this->post('pack/submit_create', $data);
    }

    // $options: path, confProduct, confName, confVersion, confRemark, confUser, confAuthor,
    //     confContent, confFrameworkType, isShell, confContent
    public function submitUpdatePackage($confProduct, $confName, $confVersion, array $options) {
        $data = compact('confProduct', 'confName', 'confVersion');
        $data += $options;
        return $this->put('pack/submit_update', $data);
    }

    public function listDirectory($path, $base, $type = 'check') {
        return $this->get('pack/list_directory', compact('path', 'base', 'type'));
    }

    public function operateFile($path, $cmd, $mode = null, $newName = null) {
        return $this->post('pack/operate_file', compact('path', 'cmd', 'mode', 'newName'));
    }

    // 查看版本详情时，先导出包目录
    public function exportPackageToCache($product, $name, $version) {
        return $this->get('pack/export_package_to_cache', compact('product', 'name', 'version'));
    }

    // 下载前，先导出包 tar 文件
    public function exportPackage($product, $name, $version) {
        return $this->get('filemanage/export', compact('product', 'name', 'version'));
    }

    public function pull($dest, $ip, $fileList) {
        return $this->post('pack/pull', compact('dest', 'ip', 'fileList'));
    }

    public function getFileContent($path, $charset, $home = null) {
        return $this->get('pack/getFileContent', compact('path', 'home', 'charset'));
    }

    public function saveFileContent($path, $content, $charset) {
        return $this->post('pack/save', compact('path', 'content', 'charset'));
    }

    public function removeVersion($product, $name, $version) {
        return $this->delete('pack/delete', compact('product', 'name', 'version'));
    }

    public function svnStatus($path) {
        return $this->get('pack/get_svn_status', compact('path'));
    }

    public function svnRevert($path) {
        return $this->post('pack/revert', compact('path'));
    }

    public function uploadFile($path, array $files, $unCompress = 'true', $chmod = '644') {
        $temp = array();
        $ScriptFile = array();
        $error = false;
        $oneSucceed = false;
        if ($files) {
            foreach ($files['error'] as $key => $errorCode) {
                if ($errorCode === UPLOAD_ERR_OK) {
                    $oneSucceed = true;
                    $name = basename(trim($files['name'][$key]));
                    if (!preg_match('/^[\w\.\-]+$/', $name)) {
                        $error = 'illegal file name';
                        break;
                    }
                    $tempDir = ROOT_DIR . '/tmp';
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755);
                    }
                    $dest = $tempDir . '/' . $name;
                    if (move_uploaded_file($files['tmp_name'][$key], $dest)) {
                        $ScriptFile['ScriptFile['.$key.']'] = '@' . $dest;
                        $temp[] = $dest;
                    } else {
                        $error = 'move_uploaded_file failed';
                        break;
                    }
                } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                } else {
                    $error = 'upload failed ' . $errorCode;
                    break;
                }
            }
        } else {
            $error = 'no file to be uploaded';
        }

        if (!$oneSucceed) {
            $error = 'no file to be uploaded';
        }

        if ($error) {
            foreach ($temp as $dest) {
                unlink($dest);
            }
            return compact('error');
        }

        $data = array_merge(compact('path', 'unCompress', 'chmod'), $ScriptFile);

        $ret = $this->request('pack/upload', null, $data, 'POST', 600, 'form-data');

        foreach ($temp as $dest) {
            unlink($dest);
        }

        return $ret;
    }

    public function downloadFile($path, $home = null) {
        $url = Config::get('pkg.api_url') . 'pack/download_file'
                . '?' . http_build_query(compact('path', 'home'));
        $host = Config::get('pkg.api_host');
        $timeout = 0;
        $method = 'GET';

        $options = compact('url', 'host', 'method', 'timeout');
        $options[CURLOPT_HEADER] = 1;

        $curl = new Curl();
        $ret = $curl->curl($options, false, function($ch, $result) use (&$size) {
            if ($result !== false) {
                $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            }
        });

        if ($ret !== false && $ret !== null) {
            $ret = array(
                'header' => substr($ret, 0, $size),
                'body' => substr($ret, $size)
            );
        } else {
            $error = $curl->curlLastError();
            $ret = $error->getHttpStatus();
        }

        return $ret;
    }

    public function getTaskByOperator($operator, array $options = null) {
        $query = compact('operator');
        if ($options) {
            $query += $options;
        }
        return $this->get('operation/get_task_by_operator', $query);
    }

    public function getTaskCountByOperator($operator, array $options = null) {
        $query = compact('operator');
        if ($options) {
            $query += $options;
        }
        return $this->get('operation/get_task_count_by_operator', $query);
    }

    public function markTaskRead(array $taskIdList, $hasRead = 1) {
        return $this->post('operation/mark_task_read', compact('taskIdList', 'hasRead'));
    }

    // $options: ipList, version, frameworkType, renameList, paraList, startAfterComplete
    public function install($product, $name, array $options) {
        $operator = Flight::get('username');
        $data = compact('product', 'name', 'operator');
        $data += $options;
        return $this->post('operation/install', $data);
    }

    // $options: ipList, fromVersion, toVersion, installPath,
    //     stopBeforeUpdate, forceUpdate, restartAfterUpdate, updateAppName, updatePort
    //     hotRestart, updateStartStopScript, copyFileInstallOrCp,
    //     batchNum, batchInterval, ignoreFileList, restartOnlyApp
    public function update($product, $name, array $options) {
        $operator = Flight::get('username');
        $data = compact('product', 'name', 'operator');
        $data += $options;
        return $this->post('operation/update', $data);
    }

    // $options: ipList, installPath, currentVersion?
    public function maintenance($product, $name, $operation, array $options) {
        $operator = Flight::get('username');
        $data = compact('product', 'name', 'operation', 'operator');
        $data += $options;
        return $this->post('operation/maintenance', $data);
    }

    // $options: ipList, installPath, operation, packageUser, frameworkType
    //     hotRestart, batchNum, batchInterval
    public function rollback($product, $name, array $options) {
        $operator = Flight::get('username');
        $data = compact('product', 'name', 'operator');
        $data += $options;
        return $this->post('operation/rollback', $data);
    }

    public function getTaskResult($taskId) {
        return $this->get('operation/get_task_result', compact('taskId'));
    }

    public function getTaskDetail($taskId) {
        return $this->get('operation/get_task_result_all', compact('taskId'));
    }

    // 批量导入设备密码
    public function importPasswords(array $devicePasswordList) {
        return $this->post('query/import_password', compact('devicePasswordList'));
    }

    // 获取设备列表
    public function getDeviceList(array $params = null) {
        return $this->get('query/device', $params);
    }

    // 获取设备业务列表
    public function getDeviceBusinessList() {
        return $this->get('query/device_business');
    }

    // 获取设备机房列表
    public function getDeviceIdcList() {
        return $this->get('query/device_idc');
    }

    // 批量删除设备
    public function removeDevices(array $deviceList) {
        return $this->post('query/delete_device', $deviceList);
    }

    // 批量添加/修改设备
    public function importDevices(array $deviceList) {
        return $this->post('query/update_device', $deviceList);
    }
}
