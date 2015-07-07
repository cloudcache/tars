<?php
/**
 * API 控制器
 * @author steveswwang
 */

namespace controller;
use Flight;
use common\BaseController;
use common\Curl;
use remote\PkgUser;
use remote\PkgAuth;
use remote\Pkg;

class Api extends BaseController {

    // 路由表
    protected $routes = array(
        'GET /api/search' => 'search',
        'POST /api/session' => 'signin',

        'GET /api/p' => 'products',
        'POST /api/p' => 'storeProducts',
        'DELETE /api/p' => 'removeProducts',

        'GET /api/p/@product:\w+/@name:\w+/exist' => 'exist',
        'POST /api/p/@product:\w+/@name:\w+/create' => 'create',
        'POST /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+' => 'submitCreate',
        'PUT /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+' => 'submitUpdate',
        'DELETE /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+' => 'removeVersion',
        'PUT /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/config' => 'updateConfig',
        'GET /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/detail' => 'versionDetail',
        'PUT /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/detail' => 'editVersionDetail',
        'GET /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/tar' => 'tar',

        'GET /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/tree/*' => 'treeReadOnly',
        'GET /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/blob/*' => 'blobReadOnly',
        'GET /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/raw/*' => 'rawReadOnly',

        'GET /api/p/@product:\w+/@name:\w+/v@version:[\d\.]+/greater-versions' => 'versionsGreater',

        'GET /api/p/@product:\w+/@name:\w+/tree/*' => 'tree',
        'GET /api/p/@product:\w+/@name:\w+/blob/*' => 'blob',
        'DELETE /api/p/@product:\w+/@name:\w+/blob/*' => 'rm', // */',
        'POST /api/p/@product:\w+/@name:\w+/blob/*' => 'mkdir',
        'PUT /api/p/@product:\w+/@name:\w+/blob/*' => 'updateBlob',
        'POST /api/p/@product:\w+/@name:\w+/pull/*' => 'pull',
        'GET /api/p/@product:\w+/@name:\w+/raw/*' => 'raw',
        'GET /api/p/@product:\w+/@name:\w+/svn/status' => 'svnStatus',
        'POST /api/p/@product:\w+/@name:\w+/svn/revert' => 'svnRevert',

        'POST /api/p/@product:\w+/@name:\w+/files/upload' => 'upload',

        'GET /api/p/@product:\w+/@name:\w+/versions' => 'versions',
        'GET /api/p/@product:\w+/@name:\w+/instances' => 'instances',
        'GET /api/p/@product:\w+/@name:\w+/settings' => 'settings',
        'PUT /api/p/@product:\w+/@name:\w+/settings' => 'updateSettings',
        'GET /api/p/@product:\w+/@name:\w+/tasks' => 'tasks',
        'GET /api/p/@product:\w+/@name:\w+/detail' => 'pkgDetail',

        'POST /api/p/@product:\w+/@name:\w+/install' => 'install',
        'POST /api/p/@product:\w+/@name:\w+/update' => 'update',
        'POST /api/p/@product:\w+/@name:\w+/maintenance' => 'maintenance',
        'POST /api/p/@product:\w+/@name:\w+/rollback' => 'rollback',

        'GET /api/task' => 'tasks',
        'GET /api/task/unread' => 'tasksUnread',
        'PUT /api/task/read' => 'markTaskRead',
        'GET /api/task/@taskId:\w+' => 'taskDetail',

        'GET /api/users' => 'listUsers',
        'POST /api/users' => 'storeUsers',
        'PUT /api/user' => 'updateUser',

        'POST /api/devices/passwords' => 'importPasswords',
        'GET /api/devices' => 'devices',
        'GET /api/devices/attrs' => 'devicesAttrs',
        'POST /api/devices' => 'importDevices',
        'POST /api/devices/batch' => 'devicesBatch',
    );

    // 获取 url 中 splat 部分，剔除 query 参数和 hash fragment
    private function getSplat($route) {
        return preg_replace('/[\?#].*/', '', $route->splat);
    }

    // 控制 Curl 请求错误
    private function handleCurlError(Curl &$curl, $index = false) {
        if ($index !== false) {
            $errors = $curl->curlMultiLastError();
            $error = $errors[$index];
        } else {
            $error = $curl->curlLastError();
        }
        if ($error) {
            Flight::json($error->toArray(), 500);
        }
    }

    // 列出业务列表
    public function products() {
        $pkg = new Pkg();
        $productList = $pkg->getProductList();

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        // 按中文排序
        $nameList = array_map(function($row){
            return $row['chinese'];
        }, $productList);
        array_multisort($nameList, $productList);

        Flight::json($productList);
    }

    // 添加业务
    public function storeProducts() {
        // 管理员校验
        $this->restrict();

        $data = Flight::request()->data->getData();

        $pkg = new Pkg();
        $ret = $pkg->storeProductList($data);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 删除业务
    public function removeProducts() {
        // 管理员校验
        $this->restrict();

        $ids = Flight::request()->query->ids;
        $ids = explode(';', $ids);

        $pkg = new Pkg();
        $ret = $pkg->removeProductList($ids);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 模糊搜索包
    public function search() {
        $query = Flight::request()->query;

        $pkg = new Pkg();
        // p: 业务
        // q: 包名
        $resultList = $pkg->searchPackage($query->q, $query->p);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($resultList);
    }

    // 登录
    public function signin() {
        $data = Flight::request()->data;

        $pkgUser = new PkgUser();
        $ret = $pkgUser->signin($data->username, $data->password);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkgUser);

        $token = $ret['token'];
        Flight::set('token', $token);

        $ret = $pkgUser->getUser();

        // 控制 Curl 请求错误
        $this->handleCurlError($pkgUser);

        session_start();

        $_SESSION['token'] = $token;
        $_SESSION['id'] = $ret['id'];
        $_SESSION['username'] = $ret['username'];
        $_SESSION['role'] = $ret['role'];

        session_write_close();

        $info = array(
            'username' => $ret['username'],
            'role' => $ret['role'],
        );

        Flight::json($info);
    }

    // 检测一个包是否存在
    public function exist($product, $name) {
        $pkg = new Pkg();
        $ret = $pkg->checkPackageExist($product, $name);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 准备创建一个包
    public function create($product, $name) {
        $data = Flight::request()->data;

        $frameworkType = $data->frameworkType;
        if ($frameworkType === 'undefined') {
            $isShell = 'true';
        } else {
            $isShell = 'false';
        }

        $pkg = new Pkg();
        $ret = $pkg->createPackage($product, $name, $frameworkType, $isShell);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 更新一个包版本配置
    public function updateConfig($product, $name, $version) {
        $data = Flight::request()->data->getData();
        $defaults = array(
            'confStateless' => 'off',
            'confOS' => 'linux',
        );
        $data += $defaults;
        $pkg = new Pkg();
        $ret = $pkg->savePackageConfig($product, $name, $version, $data);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 提交创建一个新包
    public function submitCreate($product, $name, $version) {
        $data = Flight::request()->data->getData();
        $pkg = new Pkg();
        $ret = $pkg->submitCreatePackage($product, $name, $version, $data);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        // 保存用户为包的管理员
        /*$pkgAuth = new PkgAuth();
        $pkgAuth->storeRoleUsers($product, $name, 'admin', array(Flight::get('username')));*/

        Flight::json($ret);
    }

    // 提交创建一个包的新版本
    public function submitUpdate($product, $name, $version) {
        $data = Flight::request()->data->getData();
        $pkg = new Pkg();
        $ret = $pkg->submitUpdatePackage($product, $name, $version, $data);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 删除一个包版本
    public function removeVersion($product, $name, $version) {
        $pkg = new Pkg();
        $ret = $pkg->removeVersion($product, $name, $version);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 查看一个包版本的详情
    public function versionDetail($product, $name, $version) {
        $pkg = new Pkg();
        $pkg->multi();
        $pkg->getPackageInfo($product, $name, $version);
        $pkg->exportPackageToCache($product, $name, $version);
        $rets = $pkg->exec();

        $info = array_shift($rets);
        if (!$info) {
            Flight::json(null, 404);
        }

        $export = array_shift($rets);
        if (!$export) {
            Flight::json(null, 500);
        }

        $detail = $info + $export;
        Flight::json($detail);
    }

    // 修改一个包版本的详情（备注）
    public function editVersionDetail($product, $name, $version) {
        $data = Flight::request()->data;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }
        $path = $info['path'];

        $ret = $pkg->setRemark($path, $version, $data->remark);
        Flight::json($ret);
    }

    // 列出一个包的工作目录的文件列表
    public function tree($product, $name, $route) {
        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $base = $info['path'];
        } else {
            $base = '/' . $product . '/' . $name;
        }

        // $path = urldecode($route->splat);
        $path = $this->getSplat($route);

        $files = $pkg->listDirectory($path, $base, 'create');

        if (is_array($files)) {
            $tree = array();
            foreach ($files as $row) {
                // 统一文件大小输出
                if ($row['type'] === 'dir') {
                    $row['size'] = '';
                } else {
                    $pos = strpos($row['size'], ' ');
                    if ($pos !== false) {
                        $row['size'] = substr($row['size'], 0, $pos);
                    }
                }

                $row['mode'] = preg_replace('/^0([0-7]{3}$)/', '$1', $row['mode']);

                $tree[] = $row;
            }

            usort($tree, function($a, $b){
                $c = $a['type'] === 'dir';
                $d = $b['type'] === 'dir';
                $r = $d - $c;
                if ($r) {
                    return $r;
                } else {
                    return strcmp($a['name'], $b['name']);
                }
            });

            Flight::json($tree);
        } else {
            Flight::json(null, 500);
        }
    }

    // 列出一个包版本快照的文件列表
    public function treeReadOnly($product, $name, $version, $route) {
        $query = Flight::request()->query;

        $pkg = new Pkg();

        $base = $query->home;
        // $base = '/' . $product . '/' . $name;
        $path = $this->getSplat($route);
        $files = $pkg->listDirectory($path, $base, 'check');

        if (is_array($files)) {
            $tree = array();
            foreach ($files as $row) {
                // 过滤目录 `/admin`
                if (!$path && $row['name'] === 'admin') {
                    continue;
                }

                // 统一文件大小输出
                if ($row['type'] === 'dir') {
                    $row['size'] = '';
                } else {
                    $pos = strpos($row['size'], ' ');
                    if ($pos !== false) {
                        $row['size'] = substr($row['size'], 0, $pos);
                    }
                }

                $row['mode'] = preg_replace('/^0([0-7]{3}$)/', '$1', $row['mode']);

                $tree[] = $row;
            }

            usort($tree, function($a, $b){
                $c = $a['type'] === 'dir';
                $d = $b['type'] === 'dir';
                $r = $d - $c;
                if ($r) {
                    return $r;
                } else {
                    return strcmp($a['name'], $b['name']);
                }
            });

            Flight::json($tree);
        } else {
            Flight::json(null, 500);
        }
    }

    // 获取一个包的工作目录的某个文件内容
    public function blob($product, $name, $route) {
        $query = Flight::request()->query;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $path = $info['path'];
        } else {
            $path = '/' . $product . '/' . $name;
        }

        $path .= '/' . $this->getSplat($route);

        $ret = $pkg->getFileContent($path, $query->charset);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 获取一个包版本快照的某个文件内容
    public function blobReadOnly($product, $name, $version, $route) {
        $query = Flight::request()->query;

        $path = '/' . $this->getSplat($route);

        $pkg = new Pkg();
        $ret = $pkg->getFileContent($path, $query->charset, $query->home);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 删除一个包的工作目录的某个文件
    public function rm($product, $name, $route) {
        $query = Flight::request()->query;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $base = $info['path'];
        } else {
            $base = '/' . $product . '/' . $name;
        }

        $path = $base . '/' . $this->getSplat($route);

        $ret = $pkg->operateFile($path, 'rm');

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 在一个包的工作目录创建目录
    public function mkdir($product, $name, $route) {
        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $base = $info['path'];
        } else {
            $base = '/' . $product . '/' . $name;
        }

        $path = $base . '/' . $this->getSplat($route);

        $ret = $pkg->operateFile($path, 'mkdir');

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 更新一个包的工作目录的文件内容
    public function updateBlob($product, $name, $route) {
        $data = Flight::request()->data;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $base = $info['path'];
        } else {
            $base = '/' . $product . '/' . $name;
        }

        $path = $base . '/' . $this->getSplat($route);

        if ($data->content !== null) {
            $ret = $pkg->saveFileContent($path, $data->content, $data->charset);
        } elseif ($data->name !== null) {
            $ret = $pkg->operateFile($path, 'rename', null, $data->name);
        } elseif ($data->mode !== null) {
            $ret = $pkg->operateFile($path, 'chmod', $data->mode);
        } else {
            Flight::json(null, 400);
        }

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 获取一个包的版本列表
    public function versions($product, $name) {
        $pkg = new Pkg();
        $versionList = $pkg->getVersionList($product, $name);

        if (empty($versionList)) {
            Flight::json(null, 404);
        }

        usort($versionList, function($a, $b){
            return version_compare($b['version'], $a['version']);
        });

        $path = $versionList[0]['path'];
        $counts = $pkg->getInstanceCount($path);
        $countMap = array();
        if ($counts && isset($counts['list'])) {
            foreach ($counts['list'] as $row) {
                $countMap[$row['packageVersion']] = (int) $row['count'];
            }
        }

        foreach ($versionList as &$row) {
            $row['instanceCount'] = isset($countMap[$row['version']]) ? $countMap[$row['version']] : 0;
        }
        unset($row);

        Flight::json($versionList);
    }

    // 获取一个包，大于某一个版本的版本列表
    public function versionsGreater($product, $name, $version) {
        $pkg = new Pkg();
        $versionList = $pkg->getVersionList($product, $name);

        if (empty($versionList)) {
            $versionList = array();
        }

        $list = array();
        foreach ($versionList as $row) {
            if (version_compare($row['version'], $version) > 0) {
                $list[] = array(
                    'version' => $row['version'],
                    'remark' => $row['remark'],
                );
            }
        }

        usort($list, function($a, $b){
            return version_compare($b['version'], $a['version']);
        });

        Flight::json($list);
    }

    // 获取一个包的设置信息
    public function settings($product, $name) {
        $auth = new PkgAuth();
        $auth->multi();

        $auth->getVisibility($product, $name);
        $auth->getRoles($product, $name);

        $rets = $auth->exec();

        // 是否私有包
        $ret = array_shift($rets);

        if ($ret && $ret['status'] === 0) {
            $visibility = $ret['data']['public'] ? 'public' : 'private';
        } else {
            Flight::json($ret, 500);
        }

        // 用户角色列表
        $ret = array_shift($rets);
        if ($ret && $ret['status'] === 0) {
            $data = $ret['data'];
            $data += array(
                'admin' => array(),
                'super_operator' => array(),
                'operator' => array(),
            );
            $admin = $data['admin'];
            $superOperator = $data['super_operator'];
            $operator = $data['operator'];
            $map = array();
            foreach ($admin as $name) {
                $map[$name] = array('admin');
            }
            foreach ($superOperator as $name) {
                if (!isset($map[$name])) {
                    $map[$name] = array();
                }
                $map[$name][] = 'superOperator';
            }
            foreach ($operator as $name) {
                if (!isset($map[$name])) {
                    $map[$name] = array();
                }
                $map[$name][] = 'operator';
            }
            $userList = array_map(function($name, $roles){
                return array(
                    'name' => $name,
                    'isAdmin' => in_array('admin', $roles),
                    'isSuperOperator' => in_array('superOperator', $roles),
                    'isOperator' => in_array('operator', $roles),
                );
            }, array_keys($map), array_values($map));

            $isAdmin = Flight::get('userrole') === 'admin';
            // 包的管理员或系统管理员才能更新包的权限设置
            $authorized = $isAdmin || in_array(Flight::get('username'), $admin);
        } else {
            Flight::json($ret, 500);
        }

        Flight::json(compact('visibility', 'userList', 'authorized'));
    }

    // 更新一个包的权限设置
    public function updateSettings($product, $name) {
        $data = Flight::request()->data;
        $isAdmin = Flight::get('userrole') === 'admin';

        $auth = new PkgAuth();
        $ret = $auth->getRoles($product, $name);

        if ($ret && $ret['status'] === 0) {
            $list = $ret['data'];
            $list += array(
                'admin' => array(),
            );
            // 包的管理员或系统管理员才能更新包的权限设置
            $authorized = $isAdmin || in_array(Flight::get('username'), $list['admin']);

            if (!$authorized) {
                Flight::json(array('error' => '你没有权限进行此操作'), 403);
            }

            $auth->multi();

            // 设置包是否公开属性
            if ($data->visibility) {
                $private = $data->visibility === 'private';
                $auth->setVisibility($product, $name, $private);
            }

            // 添加角色
            foreach ($data->store as $role => $users) {
                if (!empty($users)) {
                    $auth->storeRoleUsers($product, $name, $role, $users);
                }
            }

            // 删除角色
            foreach ($data->remove as $role => $users) {
                if (!empty($users)) {
                    $auth->removeRoleUsers($product, $name, $role, $users);
                }
            }

            $rets = $auth->exec();

            // 控制请求错误
            foreach ($rets as $ret) {
                if (!$ret || $ret['status'] !== 0) {
                    Flight::json($ret, 500);
                }
            }

            Flight::json(array('error' => ''));
        } else {
            Flight::json($ret, 500);
        }
    }

    // 获取一个包的实例列表
    public function instances($product, $name) {
        $query = Flight::request()->query;
        $version = $query->version;
        $page = (int) $query->page;
        if ($page < 1) {
            $page = 1;
        }
        $limit = 50;
        $fromIndex = $limit * ($page - 1);

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $path = $info['path'];
        } else {
            Flight::json(null, 404);
        }

        // 过滤IP
        $ips = $query->ips;
        if ($ips) {
            $targetIps = explode(',', $ips);
        } else {
            $targetIps = null;
        }

        // 过滤IP时，不分页
        if (!empty($targetIps)) {
            $fromIndex = $limit = 0;
        }

        $options = compact('version'/*, 'instanceName'*/, 'targetIps');
        $pageOptions = compact('fromIndex', 'limit');

        // 分页
        if ($limit) {
            $pkg->multi();
            $pkg->getInstanceCount($path, $options);
            $pkg->getInstanceList($path, $options + $pageOptions);
            $rets = $pkg->exec();

            $count = array_shift($rets);
            $list = array_shift($rets);

            // 控制 Curl 请求错误
            $this->handleCurlError($pkg, 0);
            $this->handleCurlError($pkg, 1);

        // 不分页
        } else {
            $list = $pkg->getInstanceList($path, $options + $pageOptions);

            // 控制 Curl 请求错误
            $this->handleCurlError($pkg);

            $count = array('total' => count($list));
        }

        Flight::json(array(
            'total' => $count['total'],
            'instanceList' => $list,
            'info' => array(
                'packageUser' => $info['user'],
                'frameworkType' => $info['frameworkType'],
            ),
        ));
    }

    // 获取一个包的详情
    public function pkgDetail($product, $name) {
        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }

        $ret = $pkg->checkout($product, $name, $info['version']);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($info);
    }

    // 在一个包的工作目录上传多个文件
    public function upload($product, $name) {
        $data = Flight::request()->data;
        $unCompress = $data->unCompress;
        $chmod = $data->chmod;

        if (!$unCompress) {
            $unCompress = 'true';
        }
        if (!$chmod) {
            $chmod = '644';
        }

        $files = isset($_FILES['ScriptFile']) ? $_FILES['ScriptFile'] : null;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $base = $info['path'];
        } else {
            $base = '/' . $product . '/' . $name;
        }

        $path = $base . '/' . $data->path;

        $ret = $pkg->uploadFile($path, $files, $unCompress, $chmod);

        $error = $pkg->curlLastError();
        if ($error) {
            $result = array(
                'result' => false,
                'data' => $error->toArray(),
            );
        } elseif (!$ret || $ret['error']) {
            $result = array(
                'result' => false,
                'data' => $ret,
            );
        } else {
            $result = array(
                'result' => true,
                'data' => $ret,
            );
        }

        echo '<script>parent.pkgUploadFileDone(', json_encode($result), ')</script>';
    }

    // 获取一个包的工作目录的某个原始文件
    public function raw($product, $name, $route) {
        $query = Flight::request()->query;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $path = $info['path'];
        } else {
            $path = '/' . $product . '/' . $name;
        }
        $path .= '/' . $this->getSplat($route);

        $ret = $pkg->downloadFile($path);

        if (is_int($ret)) {
            Flight::json(null, $ret);
        } else {
            if (strpos($ret['header'], 'application/octet-stream') !== false) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.basename($path).'"');
            } else {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo $ret['body'];
        }
    }

    // 获取一个包版本快照的某个原始文件
    public function rawReadOnly($product, $name, $version, $route) {
        $query = Flight::request()->query;

        $path = '/' . $this->getSplat($route);

        $pkg = new Pkg();
        $ret = $pkg->downloadFile($path, $query->home);

        if (is_int($ret)) {
            Flight::json(null, $ret);
        } else {
            if (strpos($ret['header'], 'application/octet-stream') !== false) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.basename($path).'"');
            } else {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo $ret['body'];
        }
    }

    // 获取一个包的工作目录的 svn status
    public function svnStatus($product, $name) {
        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }
        $path = $info['path'];

        $ret = $pkg->svnStatus($path);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 还原一个包的工作目录的某个文件
    public function svnRevert($product, $name) {
        $data = Flight::request()->data;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }
        $path = $info['path'];

        if ($data->path) {
            $path .= '/' . $data->path;
        }

        $ret = $pkg->svnRevert($path);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 从现网拉取多个文件到一个包的工作目录
    public function pull($product, $name, $route) {
        $data = Flight::request()->data;

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if ($info) {
            $base = $info['path'];
        } else {
            $base = '/' . $product . '/' . $name;
        }

        $path = $base . '/' . $this->getSplat($route);

        $ret = $pkg->pull($path, $data->ip, $data->fileList);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 获取一个包版本的完整 tar 包
    public function tar($product, $name, $version) {
        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::notFound();
        }
        $path = $info['path'];

        $ret = $pkg->exportPackage($product, $name, $version);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::redirect("/download{$path}/{$name}-{$version}-install.tar.gz");
    }

    // 安装一个包版本到指定的设备
    public function install($product, $name) {
        $options = Flight::request()->data->getData();

        $booleanFields = array('startAfterComplete');
        foreach ($booleanFields as $field) {
            if (isset($options[$field])) {
                $options[$field] = $options[$field] ? 'true' : 'false';
            }
        }

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }

        $defaults = array(
            'frameworkType' => $info['frameworkType'] === 'plugin' ? 'plugin' : 'server',
            'renameList' => '',
            'paraList' => '',
        );

        $options += $defaults;

        $ret = $pkg->install($product, $name, $options);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 更新指定设备的某个包实例到指定版本
    public function update($product, $name) {
        $options = Flight::request()->data->getData();

        $booleanFields = array('forceUpdate', 'updateAppName', 'stopBeforeUpdate', 'updatePort',
            'restartAfterUpdate', 'updateStartStopScript');
        foreach ($booleanFields as $field) {
            if (isset($options[$field])) {
                $options[$field] = $options[$field] ? 'true' : 'false';
            }
        }

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }

        $defaults = array(
            'batchNum' => 0,
            'batchInterval' => 0,
            'hotRestart' => 'false',
            'ignoreFileList' => array(),
            'copyFileInstallOrCp' => 'install',
            'restartOnlyApp' => '',
        );

        $options += $defaults;

        $ret = $pkg->update($product, $name, $options);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 维护指定设备的某个包实例 (启动/重启/停止/卸载)
    public function maintenance($product, $name) {
        $query = Flight::request()->query;
        $operation = $query->operation;
        $options = Flight::request()->data->getData();

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }

        $defaults = array(
            // 'hotRestart' => 'false',
            'batchNum' => 0,
            'batchInterval' => 0,
        );

        $options += $defaults;

        $ret = $pkg->maintenance($product, $name, $operation, $options);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 回滚指定设备的某个包实例
    public function rollback($product, $name) {
        $options = Flight::request()->data->getData();

        $pkg = new Pkg();

        $info = $pkg->getPackageInfo($product, $name);
        if (!$info) {
            Flight::json(null, 404);
        }

        $ret = $pkg->rollback($product, $name, $options);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 查询任务列表
    public function tasks($product = null, $name = null) {
        $query = Flight::request()->query;
        $page = (int) $query->page;
        if ($page < 1) {
            $page = 1;
        }
        $limit = 20;
        $fromIndex = $limit * ($page - 1);

        $startTime = $endTime = null;
        switch ($query->range) {
            case 'today':
                $startTime = date('Y-m-d H:i:s', strtotime('today'));
                break;

            case 'yesterday':
                $startTime = date('Y-m-d H:i:s', strtotime('yesterday'));
                $endTime   = date('Y-m-d H:i:s', strtotime('today'));
                break;

            case 'month':
                $startTime = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;

            case 'all':
                break;

            default:
                $startTime = date('Y-m-d H:i:s', strtotime('-7 days'));
        }
        $options = compact('startTime', 'endTime', 'product', 'name');
        $pageOptions = compact('fromIndex', 'limit');

        // TODO: 如果已制定 product 和 name, 则移除 operator
        if ($product && $name) {
            $operator = null;
        } else {
            $operator = Flight::get('username');
        }

        $pkg = new Pkg();
        $pkg->multi();
        $pkg->getTaskCountByOperator($operator, $options);
        $pkg->getTaskByOperator($operator, $options + $pageOptions);
        $rets = $pkg->exec();

        $count = array_shift($rets);
        $list = array_shift($rets);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg, 0);
        $this->handleCurlError($pkg, 1);

        Flight::json(array(
            'total' => (int) $count['count'],
            'taskList' => $list,
        ));
    }

    // 查询我的未读任务列表
    public function tasksUnread() {
        $operator = Flight::get('username');

        $pkg = new Pkg();
        $list = $pkg->getTaskByOperator($operator, array('hasRead' => 0));

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        if ($list) {
            $first = $list[0];
            $temp = array();
            foreach ($list as $row) {
                $temp[] = array(
                    'task_id' => $row['task_id'],
                    'product' => $row['product'],
                    'name' => $row['name'],
                    'op_type' => $row['op_type'],
                    'task_status' => $row['task_status'],
                    'task_num' => (int) $row['task_num'],
                    'success_num' => (int) $row['success_num'],
                    'fail_num' => (int) $row['fail_num'],
                    // 'ipList' => $ipList,
                );
            }
            $list = $temp;
        }

        Flight::json($list);
    }

    // 标记任务为 `已读`
    public function markTaskRead() {
        $data = Flight::request()->data->getData();

        $pkg = new Pkg();
        $ret = $pkg->markTaskRead($data);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 获取一个任务的详情
    public function taskDetail($taskId) {
        $pkg = new Pkg();
        $pkg->multi();
        $pkg->getTaskDetail($taskId);
        $pkg->getTaskResult($taskId);
        $rets = $pkg->exec();

        $detail = array_shift($rets);
        $result = array_shift($rets);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg, 0);
        $this->handleCurlError($pkg, 1);

        Flight::json(compact('detail', 'result'));
    }

    // 管理员校验
    private function restrict($msg = '你没有权限进行此操作') {
        $role = Flight::get('userrole');
        if ($role !== 'admin') {
            Flight::json(array(
                'status' => 403,
                'msg' => $msg,
            ), 403);
        }
    }

    // 列出全部用户
    public function listUsers() {
        // 管理员校验
        $this->restrict();

        $pkgUser = new PkgUser();
        $ret = $pkgUser->listUsers();

        // 控制 Curl 请求错误
        $this->handleCurlError($pkgUser);

        Flight::json($ret);
    }

    // 批量添加用户
    public function storeUsers() {
        // 管理员校验
        $this->restrict();

        $users = Flight::request()->data->getData();

        $pkgUser = new PkgUser();
        $ret = $pkgUser->storeUsers($users);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkgUser);

        Flight::json($ret);
    }

    // 修改用户密码
    public function updateUser() {
        $data = Flight::request()->data;

        $pkgUser = new PkgUser();

        if ($data->role) {
            // 管理员校验
            $this->restrict();

            $role = $data->role;
            // 修改用户角色
            $ret = $pkgUser->updateUser($data->username, compact('role'));
        } else {
            $old_password = $data->old_password;
            $password = $data->password;
            $role = Flight::get('userrole');
            $ret = $pkgUser->updateUser(Flight::get('username'), compact('old_password', 'password', 'role'));
        }

        // 控制 Curl 请求错误
        $this->handleCurlError($pkgUser);

        Flight::json($ret);
    }

    // 批量导入设备密码
    public function importPasswords() {
        $devices = Flight::request()->data->getData();

        $pkg = new Pkg();
        $ret = $pkg->importPasswords($devices);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 查询设备列表
    public function devices() {
        $query = Flight::request()->query;

        $params = array();
        if ($query->business) {
            $params['business'] = $query->business;
        }
        if ($query->idc) {
            $params['idc'] = $query->idc;
        }

        $pkg = new Pkg();
        $ret = $pkg->getDeviceList($params);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 查询设备属性列表（业务、机房）
    public function devicesAttrs() {
        $pkg = new Pkg();
        $pkg->multi();
        $pkg->getDeviceBusinessList();
        $pkg->getDeviceIdcList();
        $rets = $pkg->exec();

        $businessRet = array_shift($rets);
        $idcRet = array_shift($rets);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg, 0);
        $this->handleCurlError($pkg, 1);

        $businessList = array_map(function($row){
            return $row['business'];
        }, $businessRet);
        $idcList = array_map(function($row){
            return $row['idc'];
        }, $idcRet);

        sort($businessList);
        sort($idcList);

        Flight::json(compact('businessList', 'idcList'));
    }

    // 批量导入设备列表
    public function importDevices() {
        $devices = Flight::request()->data->getData();

        $pkg = new Pkg();
        $ret = $pkg->importDevices($devices);

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }

    // 设备相关其它批量操作
    public function devicesBatch() {
        $method = Flight::request()->query->_method;

        $pkg = new Pkg();

        if ($method === 'DELETE') {
            $data = Flight::request()->data->getData();

            $devices = array_map(function($deviceId){
                return compact('deviceId');
            }, $data);

            $ret = $pkg->removeDevices($devices);
        } else {
            Flight::json(null, 400);
        }

        // 控制 Curl 请求错误
        $this->handleCurlError($pkg);

        Flight::json($ret);
    }
}
