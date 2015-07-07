/*!
 * Tars services.js
 * 服务及过滤器等
 * @author steveswwang
 */

angular.module('tars.services', [])

// 当前登录人
.factory('Me', function(){
  return {
    getUsername: function(){
      return window._username;
    },
    getRole: function(){
      return window._userrole;
    }
  };
})

// $http 包装，识别数组、对象
.factory('HttpWrapper', [
        '$q',
function($q){
  function wrap(http, type) {
    var deferred = $q.defer();
    http.then(function(response){
      var data = response.data,
          fn;
      switch (type) {
        case 'array':
          fn = 'isArray';
          break;
        case 'object':
          fn = 'isObject';
          break;
      }
      if (angular[fn](data)) {
        deferred.resolve(data);
      } else {
        deferred.reject({
          status: response.status,
          data: {
            code: 1001,
            httpStatus: 200,
            data: {
              error: 'unexpected result'
            }
          }
        });
      }
    }, deferred.reject);
    return deferred.promise;
  }
  return {
    array: function(http){
      return wrap(http, 'array');
    },
    object: function(http){
      return wrap(http, 'object');
    }
  };
}])

// 处理 Ajax 错误
.factory('HandleAjaxError', [
        'Dialog', 'HandleNoSession',
function(Dialog ,  HandleNoSession){
  return function(message, reason){
    // 忽略主动中断
    if (reason.status === 0) {
      return;
    }

    // 处理没有登录或登录信息超时的情况
    if (HandleNoSession(reason)) {
      return;
    }

    // 其它情况提示错误原因
    Dialog.alert({
      message: message,
      level: 'error',
      log: reason.data ?
          (reason.data.data ?
            (reason.data.data.error || reason.data.data) : reason.data)
              : _.pick(reason, 'status', 'data')
    });
  };
}])

// 处理没有登录或登录信息超时的情况
.factory('HandleNoSession', [
        'Dialog',
function(Dialog){
  return function(reason){
    if (reason.status === 403 && reason.data && reason.data.error === 'no session') {
      Dialog.confirm({
        message: '你还没有登录或登录信息超时，请重新登录！',
        level: 'warning',
        confirm: function(){
          location.href = '/signin';
        }
      });
      return true;
    }
    return false;
  };
}])

// 封装一层 console
.factory('console', [
        'DEBUG',
function(DEBUG){
  var methods = ['log', 'error'],
      console = {},
      _console = window.console;
  angular.forEach(methods, function(method){
    console[method] = function(){
      if (DEBUG && _console) {
        _console[method].apply(_console, arguments);
      }
    };
  });
  return console;
}])

// 过滤器：任务对应版本号
.filter('taskVersion', function(){
  return function(task){
    if (task.param) {
      switch (task.op_type) {
        case 'update':
          return 'v' + task.param.fromVersion + ' > v' + task.param.toVersion;
        case 'install':
          return 'v' + task.param.version;
      }
    }
    return '';
  };
})

// 过滤器：任务操作类型
.filter('operationType', function(){
  var typeMap = {
    install: '安装',
    update: '升级',
    rollback: '回滚',
    start: '启动',
    restart: '重启',
    stop: '停止',
    uninstall: '卸载'
  };
  return function(operation){
    return typeMap[operation] || '未知';
  };
})

// 过滤器：任务状态
.filter('taskStatus', function(){
  var statusMap = {
        ok: '成功',
        failed: '失败',
        wait: '等待'
      },
      statusMapAction = _.defaults({'wait': '中...'}, statusMap);
  return function(task, action){
    return (action ? statusMapAction : statusMap)[task.task_status] || '未知';
  };
})

// 过滤器：任务详情状态
.filter('taskDetailStatus', function(){
  return function(task){
    switch (task.status) {
      case 'wait':
        return '等待中';
      case 'started':
        return '执行中';
      case 'ok':
        return '成功';
      case 'failed':
        return '失败';
      default:
        return '未知';
    }
  };
})

// 过滤器：任务进度
.filter('taskProgress', function(){
  return function(task){
    return task.success_num + '/' + task.task_num;
  };
})

// 过滤器：SVN 状态类型
.filter('svnAction', function(){
  return function(action){
    switch (action) {
      case 'unversion':
      case 'add':
        return '新增';
      case 'modify':
        return '修改';
      case 'delete':
      case 'miss':
        return '删除';
      default:
        return '其它';
    }
  };
})

// 过滤器：任务耗时
.filter('costTime', function(){
  return function(time){
    time = +time;
    var seconds = time % 60,
        minutes = Math.floor(time / 60),
        hours = Math.floor(time / 3600);
    return (hours > 0 ? hours + 'h' : '') +
        (hours > 0 || minutes > 0 ? minutes + '′' : '') +
        (hours > 0 || minutes > 0 || seconds > 0 ? seconds + '″' : '');
  };
})

// 一些工具函数
.factory('Utils', [
        '$timeout',
function($timeout){
  return {
    // 从文本中匹配多个 IP
    matchIps: function(str){
      return str.match(/\b\d{1,3}(\.\d{1,3}){3}\b/g);
    },
    // 在一个元素上弹出 `已复制` 提示
    copied: function(selector){
      var elem = $(selector).tooltip({
        show: true,
        trigger: 'manual',
        title: '已复制'
      }).tooltip('show');
      $timeout(function(){
        elem.tooltip('hide');
      }, 1e3);
    }
  };
}])

// 构建包的 URL
.factory('PkgUrl', function(){
  return {
    build: function(pkg, withVersion) {
      return '/api/p/' + pkg.product + '/' + pkg.name + (withVersion ? '/v' + pkg.version : '');
    }
  };
})

// 包管理服务
.factory('PkgManager', [
        '$http', 'HttpWrapper', 'PkgUrl',
function($http ,  HttpWrapper ,  PkgUrl){
  var products = HttpWrapper.array(
    $http.get('/api/p')
  );

  return {
    // 获取业务列表
    listProducts: function(){
      return products;
    },
    // 批量导入业务
    storeProducts: function(products){
      return HttpWrapper.object(
        $http.post('/api/p', products)
      );
    },
    // 删除多个业务
    removeProducts: function(ids){
      return HttpWrapper.object(
        $http['delete']('/api/p', {
          params: {
            ids: ids.join(';')
          }
        })
      );
    },
    // 重新获取业务列表
    relistProducts: function(){
      products = HttpWrapper.array(
        $http.get('/api/p')
      );
      return products;
    },
    // 搜索包
    search: function(q, p){
      return HttpWrapper.array(
        $http.get('/api/search', {
          params: {
            q: q,
            p: p
          }
        })
      );
    },
    // 获取包详情
    getPkgDetail: function(product, name){
      return HttpWrapper.object(
        $http.get(PkgUrl.build({
          product: product,
          name: name
        }) + '/detail')
      );
    },
    // 获取版本详情
    getVersionDetail: function(product, name, version){
      return HttpWrapper.object(
        $http.get(PkgUrl.build({
          product: product,
          name: name,
          version: version
        }, true) + '/detail')
      );
    },
    // 获取版本列表
    getVersionList: function(product, name){
      return HttpWrapper.array(
        $http.get(PkgUrl.build({
          product: product,
          name: name
        }) + '/versions')
      );
    },
    // 获取可升级版本列表
    getGreaterVersionList: function(product, name, version){
      return HttpWrapper.array(
        $http.get(PkgUrl.build({
          product: product,
          name: name,
          version: version
        }, true) + '/greater-versions')
      );
    },
    // 获取包设置信息
    getSettings: function(product, name){
      return HttpWrapper.object(
        $http.get(PkgUrl.build({
          product: product,
          name: name
        }) + '/settings')
      );
    },
    updateSettings: function(pkg, params){
      return HttpWrapper.object(
        $http.put(PkgUrl.build(pkg) + '/settings', params)
      );
    },
    // 获取实例列表数据（分页）
    getInstanceData: function(product, name, params){
      return HttpWrapper.object(
        $http.get(PkgUrl.build({
          product: product,
          name: name,
        }) + '/instances', {
          params: params
        })
      );
    },
    // 修改版本备注
    updateRemark: function(pkg){
      return HttpWrapper.object(
        $http.put(PkgUrl.build(pkg, true) + '/detail', {
          remark: pkg.remark
        })
      );
    },
    // 安装
    install: function(pkg, options){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/install', options)
      );
    },
    // 升级
    update: function(pkg, options){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/update', options)
      );
    },
    // 启动/停止/重启/卸载
    maintenance: function(pkg, operation, options){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/maintenance', options, {
          params: {
            operation: operation
          }
        })
      );
    },
    // 回滚
    rollback: function(pkg, options){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/rollback', options)
      );
    },
    // 检测包是否存在
    exist: function(pkg){
      return HttpWrapper.object(
        $http.get(PkgUrl.build(pkg) + '/exist')
      );
    },
    // 开始创建包
    create: function(pkg){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/create', {
          frameworkType: pkg.frameworkType
        })
      );
    },
    // 保存包配置
    save: function(pkg, options){
      return HttpWrapper.object(
        $http.put(PkgUrl.build(pkg, true) + '/config', options)
      );
    },
    // 提交创建新包或新版本
    submit: function(pkg, options, isCreate) {
      return HttpWrapper.object(
        $http[isCreate ? 'post' : 'put'](PkgUrl.build(pkg, true), options)
      );
    },
    // 删除版本
    removeVersion: function(pkg){
      return HttpWrapper.object(
        $http['delete'](PkgUrl.build(pkg, true))
      );
    }
  };
}])

// 任务管理服务
.factory('TaskManager', [
        '$http', 'HttpWrapper', 'PkgUrl',
function($http ,  HttpWrapper ,  PkgUrl){
  return {
    // 获取任务列表
    list: function(range, page, pkg){
      if (pkg) {
        // 获取包的任务列表
        return HttpWrapper.object(
          $http.get(PkgUrl.build(pkg) + '/tasks', {
            params: {
              range: range,
              page: page
            }
          })
        );
      } else {
        // 获取我的任务列表
        return HttpWrapper.object(
          $http.get('/api/task', {
            params: {
              range: range,
              page: page
            }
          })
        );
      }
    },
    // 获取未读任务列表
    listUnread: function(){
      return HttpWrapper.array(
        $http.get('/api/task/unread')
      );
    },
    // 标记任务为已读
    read: function(taskIdList){
      return HttpWrapper.object(
        $http.put('/api/task/read', taskIdList)
      );
    },
    // 获取任务详情
    detail: function(timeout, taskId){
      return HttpWrapper.object(
        $http.get('/api/task/' + taskId, {
          timeout: timeout
        })
      );
    }
  };
}])

// 文件管理服务
.factory('FileManager', [
        '$http', 'HttpWrapper', 'PkgUrl',
function($http ,  HttpWrapper ,  PkgUrl){
  return {
    // 获取目录文件列表
    ls: function(pkg, path, home){
      return HttpWrapper.array(
        $http.get(PkgUrl.build(pkg, !!home) + '/tree/' + path, {
          params: {
            home: home
          }
        })
      );
    },
    // 删除文件
    rm: function(pkg, path){
      return HttpWrapper.object(
        $http['delete'](PkgUrl.build(pkg) + '/blob/' + path)
      );
    },
    // 创建新目录
    mkdir: function(pkg, path){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/blob/' + path)
      );
    },
    // 重命名文件
    rename: function(pkg, path, name){
      return HttpWrapper.object(
        $http.put(PkgUrl.build(pkg) + '/blob/' + path, {
          name: name
        })
      );
    },
    // 修改文件权限
    chmod: function(pkg, path, mode){
      return HttpWrapper.object(
        $http.put(PkgUrl.build(pkg) + '/blob/' + path, {
          mode: mode
        })
      );
    },
    // 现网拉取文件
    pull: function(pkg, path, ip, fileList){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/pull/' + path, {
          ip: ip,
          fileList: fileList
        })
      );
    },
    // 获取文件内容
    content: function(pkg, path, charset, home){
      return HttpWrapper.object(
        $http.get(PkgUrl.build(pkg, !!home) + '/blob/' + path, {
          params: {
            charset: charset,
            home: home
          }
        })
      );
    },
    // 修改文件内容
    updateContent: function(pkg, path, content, charset){
      return HttpWrapper.object(
        $http.put(PkgUrl.build(pkg) + '/blob/' + path, {
          content: content,
          charset: charset
        })
      );
    }
  };
}])

// SVN 管理服务
.factory('SvnManager', [
        '$http', 'HttpWrapper', 'PkgUrl',
function($http ,  HttpWrapper ,  PkgUrl){
  return {
    // 获取 SVN status （文件变更列表）
    status: function(pkg){
      return HttpWrapper.array(
        $http.get(PkgUrl.build(pkg) + '/svn/status')
      );
    },
    // 还原工作目录的文件
    revert: function(pkg, path){
      return HttpWrapper.object(
        $http.post(PkgUrl.build(pkg) + '/svn/revert', {
          path: path
        })
      );
    }
  };
}])

// 用户管理服务
.factory('UserManager', [
        '$http', 'HttpWrapper',
function($http ,  HttpWrapper){
  return {
    // 获取用户列表
    list: function(){
      return HttpWrapper.array(
        $http.get('/api/users')
      );
    },
    // 批量导入用户名和密码
    store: function(users){
      return HttpWrapper.object(
        $http.post('/api/users', users)
      );
    },
    // 修改用户信息（密码）
    update: function(data){
      return HttpWrapper.object(
        $http.put('/api/user', data)
      );
    }
  };
}])

// 设备管理服务
.factory('DeviceManager', [
        '$http', 'HttpWrapper',
function($http ,  HttpWrapper){
  return {
    // 列出设备列表
    list: function(params){
      return HttpWrapper.array(
        $http.get('/api/devices', {
          params: params
        })
      );
    },
    // 列出设备属性列表（业务、机房）
    listAttrs: function(){
      return HttpWrapper.object(
        $http.get('/api/devices/attrs')
      );
    },
    // 批量导入设备密码
    importPasswords: function(devices){
      return HttpWrapper.object(
        $http.post('/api/devices/passwords', devices)
      );
    },
    // 批量导入设备列表
    importDevices: function(devices){
      return HttpWrapper.object(
        $http.post('/api/devices', devices)
      );
    },
    // 批量删除设备
    removeDevices: function(deviceIds){
      // Method overriding
      return HttpWrapper.object(
        $http.post('/api/devices/batch?_method=DELETE', deviceIds)
      );
    }
  };
}])

// 对话框服务
.factory('Dialog', [
        '$rootScope',
function($rootScope){
  return {
    alert: function(options){
      $rootScope.$broadcast('dialog show', 'alert', options);
    },
    confirm: function(options){
      $rootScope.$broadcast('dialog show', 'confirm', options);
    },
    prompt: function(options){
      $rootScope.$broadcast('dialog show', 'prompt', options);
    },
    'delete': function(options){
      $rootScope.$broadcast('dialog show', 'confirm', _.defaults(options, {confirmText: '删除', confirmClass: 'btn-danger', level: 'warning'}));
    },
    processing: function(){
      // 标记对话框为处理中（这将禁用按钮）
      $rootScope.$broadcast('dialog processing');
    },
    processed: function(){
      // 标记对话框为处理完成（这将恢复按钮）
      $rootScope.$broadcast('dialog processed');
    },
    close: function(){
      // 关闭对话框
      $rootScope.$broadcast('dialog close');
    }
  };
}])

// 对话框控制器
.controller('DialogCtrl', [
        '$scope', '$timeout',
function($scope ,  $timeout){
  var dialog = $('#modal-dailog'),
      defaults = {
        title: '提示',
        cancelText: '取消',
        confirmText: '确定',
        confirmClass: 'btn-primary',
        autofocus: true,
        cancel: function(){
          dialog.modal('hide');
        }
      },
      typeDefaults = {
        alert: {
          confirm: function(){
            dialog.modal('hide');
          }
        }
      };

  $scope.$on('dialog show', function(e, type, options){
    $scope.options = _.defaults(options, defaults, typeDefaults[type]);
    $scope.type = type;
    $scope.processing = false;

    dialog.modal({
      show: true,
      backdrop: 'static'
    });

    if (options.autofocus) {
      // 按钮自动聚焦
      $timeout(function(){
        if (type === 'prompt') {
          dialog.find('.form-control').focus().select();
        } else {
          dialog.find('.js-confirm').focus();
        }
      });
    }
  });

  // 标记对话框为处理中（这将禁用按钮）
  $scope.$on('dialog processing', function(){
    $scope.processing = true;
  });

  // 标记对话框为处理完成（这将恢复按钮）
  $scope.$on('dialog processed', function(){
    $scope.processing = false;
  });

  // 关闭对话框
  $scope.$on('dialog close', function(){
    dialog.modal('hide');
  });
}]);
