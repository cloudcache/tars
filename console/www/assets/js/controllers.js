/*!
 * Tars controllers.js
 * 控制器
 * @author steveswwang
 */

angular.module('tars.controllers', [])

// 顶部导航
.controller('NavbarCtrl', [
        '$scope', '$rootScope', '$state', '$timeout', 'TaskManager', 'PkgManager', 'Me', 'HandleAjaxError', 'HandleNoSession', 'console', 'NAVBAR_TASK_INTERVAL',
function($scope ,  $rootScope ,  $state ,  $timeout ,  TaskManager ,  PkgManager ,  Me ,  HandleAjaxError ,  HandleNoSession ,  console ,  NAVBAR_TASK_INTERVAL){

  // 顶部任务拉取间隔
  if (NAVBAR_TASK_INTERVAL < 3e3) {
    throw 'NAVBAR_TASK_INTERVAL should be greater than 3000';
  }

  $scope.username = Me.getUsername();

  // 包搜索
  $scope.params = {};
  $scope.search = function(){
    if ($scope.params.q) {
      $state.go('search', $scope.params);
    }
    return false;
  };

  // 导航状态
  $scope.pkgActive = function(){
    return $state.includes('home') || $state.includes('search') ||
        $state.includes('product') || $state.includes('pkg');
  };
  $scope.devicesActive = function(){
    return $state.includes('devices') || $state.includes('devices-import') ||
        $state.includes('devices-passwords');
  };

  // 顶部任务条
  var myTaskLeaving;
  $scope.myTaskEnter = function(){
    if (myTaskLeaving) {
      $timeout.cancel(myTaskLeaving);
      myTaskLeaving = null;
    }
    $scope.myTaskOpen = true;
  };
  $scope.myTaskLeave = function(){
    // 延迟隐藏
    myTaskLeaving = $timeout(function(){
      $scope.myTaskOpen = false;
    }, 200);
  };

  // 关闭任务下拉层
  $scope.closeMyTask = function(){
    $scope.myTaskOpen = false;
  };

  // 顶部包管理导航
  $scope.pkgEnter = function(){
    /*if ($scope.pkgActive()) {
      $scope.pkgOpen = true;
    }*/
    $scope.pkgOpen = true;
  };
  $scope.pkgLeave = $scope.closePkg = function(){
    $scope.pkgOpen = false;
  };

  // 任务总状态
  $scope.globalTaskStatus = function(){
    var success = 0,
        error = 0,
        running = 0;
    angular.forEach($scope.taskList, function(row){
      switch (row.task_status) {
        case 'ok':
          success += 1;
          break;
        case 'failed':
          error += 1;
          break;
        case 'wait':
          running += 1;
      }
    });
    return running > 0 ? null : error > 0 ? 'error' : 'success';
  };

  // 页面加载 spinner
  $rootScope.$on('$stateChangeStart', function(){
    $scope.progressing = true;
  });
  $rootScope.$on('$stateChangeSuccess', function(){
    $scope.progressing = false;
  });
  $rootScope.$on('$stateChangeError', function(){
    $scope.progressing = false;
  });

  // 拉取未读任务列表
  $scope.taskLoading = true;
  function getUnreadTask() {
    TaskManager.listUnread()
    .then(function(data){
      $scope.taskList = data;
    })['catch'](function(reason){
      HandleAjaxError('查询未读任务失败！', reason);
    })['finally'](function(){
      $scope.taskLoading = false;
    });
  }
  getUnreadTask();

  // 标记任务已读
  $scope.clearTask = function(row, index){
    TaskManager.read([row.task_id])
    .then(function(){
      $scope.taskList.splice(index, 1);
    })['catch'](function(reason){
      HandleAjaxError('标记任务已读失败！', reason);
    });
  };

  // 标记全部任务已读
  $scope.clearAllTask = function(){
    var ids = [];
    angular.forEach($scope.taskList, function(row){
      ids.push(row.task_id);
    });
    if (ids.length) {
      TaskManager.read(ids)
      .then(function(data){
        console.log('TaskManager.read', data);
        $scope.taskList = [];
      })['catch'](function(reason){
        HandleAjaxError('标记任务已读失败！', reason);
      });
    }
  };

  // 每隔指定时间拉取一次未读任务列表
  function fetchTaskListLater() {
    $timeout(fetchTaskList, NAVBAR_TASK_INTERVAL);
  }
  function fetchTaskList() {
    TaskManager.listUnread()
    .then(function(data){
      console.log('TaskManager.listUnread', data);
      $scope.taskList = data;
    })['catch'](function(reason){
      console.error('TaskManager.listUnread', reason);
      HandleNoSession(reason);
    })['finally'](fetchTaskListLater);
  }
  fetchTaskListLater();

  // 展示业务列表
  function fetchProductList() {
    $scope.loadingProducts = true;
    PkgManager.listProducts()
    .then(function(data){
      console.log('PkgManager.listProducts', data);
      $scope.productList = data;
    })['catch'](function(reason){
      console.error('PkgManager.listProducts', reason);
    })['finally'](function(){
      $scope.loadingProducts = false;
    });
  }

  fetchProductList();

  // 业务列表有更新
  $scope.$on('relist products', fetchProductList);

  // 退出搜索结果页时，将搜索框内容置空
  $scope.$on('search destroy', function(){
    $scope.params.q = null;
  });

  // 在搜索结果页刷新时，恢复搜索框内容
  $scope.$on('search key', function(e, q){
    $scope.params.q = q;
  });
}])

// 首页
.controller('HomeCtrl', [
        '$scope', '$state', 'productList',
function($scope ,  $state ,  productList){
  $scope.productList = productList;

  // 包搜索
  $scope.params = {};
  $scope.search = function(){
    if (!$scope.params.q && $scope.params.p) {
      // 只搜索业务，则跳转到指定业务的包列表页
      $state.go('product', {product: $scope.params.p});
    } else if ($scope.params.q || $scope.params.p) {
      // 否则，跳转到包搜索结果页
      $state.go('search', $scope.params);
    }
  };
}])

// 包搜索结果页
.controller('SearchCtrl', [
        '$scope', '$stateParams', 'resultList',
function($scope ,  $stateParams ,  resultList){
  var inited = false,
      filters = {
        page: 1
      };

  $scope.itemsPerPage = 20;
  $scope.totalItems = resultList.length;
  $scope.filters = filters;

  // 分页切换
  $scope.$watch('filters', function(){
    var begin = ((filters.page - 1) * $scope.itemsPerPage),
        end = begin + $scope.itemsPerPage;
    $scope.resultList = resultList.slice(begin, end);
    if (inited) {
      // 切换页码时，将滚动条置顶
      window.scrollTo(0, 0);
    } else {
      inited = true;
    }
  }, true);

  // 在搜索结果页刷新时，通知 NavbarCtrl 恢复搜索框内容
  $scope.$parent.$broadcast('search key', $stateParams.q);

  $scope.$on('$destroy', function(){
    // 离开包搜索结果页时，通知 NavbarCtrl 将搜索框内容置空
    $scope.$parent.$broadcast('search destroy');
  });
}])

// 任务列表
.controller('TasksCtrl', [
        '$scope', '$stateParams', '$state', 'taskData', 'pkg', 'TASK_DEFAULT_DATE_RANGE',
function($scope ,  $stateParams ,  $state ,  taskData ,  pkg ,  TASK_DEFAULT_DATE_RANGE){
  // 时间范围列表
  $scope.rangeList = [{
    value: 'today',
    text: '今天'
  },{
    value: 'yesterday',
    text: '昨天'
  },{
    value: 'week',
    text: '近7天'
  },{
    value: 'month',
    text: '近30天'
  },{
    value: 'all',
    text: '全部'
  }];

  var defaults = {
        range: TASK_DEFAULT_DATE_RANGE,
        page: 1
      },
      filters = _.defaults($stateParams, defaults);

  $scope.taskList = taskData.taskList;
  $scope.totalItems = taskData.total;
  $scope.itemsPerPage = 20;
  $scope.filters = filters;
  $scope.pkg = pkg;

  $scope.setActiveRange = function(row){
    filters.range = row.value;
  };
  $scope.isActiveRange = function(row){
    return filters.range === row.value;
  };

  // 跳转到任务操作的设备对应的实例列表页
  $scope.goToPkgInstances = function(row){
    $state.go('pkg.instances', _.defaults({ips: row.ip_list.replace(/;/g, ',')}, row));
  };

  // 处理默认数据，以对比过滤参数是否更改
  function trim(filters) {
    var options = {};
    angular.forEach(filters, function(value, key){
      options[key] = defaults[key] === value ? null : value;
    });
    return options;
  }

  // 分页或时间范围更改
  $scope.$watch('filters', function(newValue, oldValue){
    var options = trim(newValue);
    if (!angular.equals(options, trim(oldValue))) {
      window.scrollTo(0, 0);
      if (pkg) {
        // 包任务列表
        $state.go('pkg.tasks', _.defaults(options, pkg));
      } else {
        // 我的任务列表
        $state.go('tasks', options);
      }
    }
  }, true);
}])

// 任务详情
.controller('TaskDetailCtrl', [
        '$scope', '$q', '$stateParams', '$state', '$timeout', 'TaskManager', 'HandleAjaxError', 'Utils', 'taskDetail', 'TASK_DETAIL_INTERVAL',
function($scope ,  $q ,  $stateParams ,  $state ,  $timeout ,  TaskManager ,  HandleAjaxError ,  Utils ,  taskDetail ,  TASK_DETAIL_INTERVAL){

  // 任务详情拉取间隔
  if (TASK_DETAIL_INTERVAL < 3e3) {
    throw 'TASK_DETAIL_INTERVAL should be greater than 3000';
  }

  var ips, timer,
      abort = $q.defer(),
      timeout = abort.promise;

  function init(data) {
    ips = {
      all: [],
      ok: [],
      failed: []
    };

    $scope.taskId = $stateParams.taskId;
    $scope.taskResult = data.result;
    $scope.taskDetail = data.detail;
    $scope.ips = ips;

    angular.forEach(data.result, function(row){
      switch (row.status) {
        case 'ok':
        case 'failed':
          ips[row.status].push(row.ip);
      }
      ips.all.push(row.ip);
    });

    // 任务还在进行中，则继续拉取任务结果
    if (!(data.detail.task_status === 'ok' || data.detail.task_status === 'failed')) {
      fetchResultLater();
    }
  }

  // 每隔指定时间，拉取一次任务结果
  function fetchResultLater() {
    timer = $timeout(fetchResult, TASK_DETAIL_INTERVAL);
  }

  function fetchResult() {
    TaskManager.detail(timeout, $scope.taskId)
    .then(init)
    ['catch'](function(reason){
      HandleAjaxError('查询任务结果失败！', reason);
      if (reason.status !== 0) {
        // 非请求中断，才进行下一次拉取
        fetchResultLater();
      }
    });
  }

  init(taskDetail);

  // 要复制的IP列表
  $scope.getIpsToCopy = function(status){
    return ips[status ? status : 'all'].join('\n');
  };

  // 复制成功
  $scope.copied = Utils.copied;

  // 展开/隐藏任务参数
  $scope.toggleParam = function(){
    $scope.paramShown = !$scope.paramShown;
  };

  // 跳转到任务操作的设备对应的实例列表页
  $scope.goToPkgInstances = function(){
    var detail = $scope.taskDetail;
    $state.go('pkg.instances', _.defaults({ips: detail.ip_list.replace(/;/g, ',')}, detail));
  };

  $scope.$on('$destroy', function(){
    // 离开任务详情页时，结束下一次拉取
    $timeout.cancel(timer);

    // 中断当前请求
    abort.resolve();
  });
}])

// 创建新包
.controller('NewCtrl', [
        '$scope', '$stateParams', '$state', '$timeout', 'PkgManager', 'FileManager', 'SvnManager', 'PkgUrl', 'Dialog', 'HandleAjaxError', 'Me', 'console', 'productList', 'pkgDetail', 'versionDetail', 'PKG_DEFAULT_USER', 'PKG_DEFAULT_FRAMEWORK_TYPE', 'PKG_DEFAULT_VERSION',
function($scope ,  $stateParams ,  $state ,  $timeout ,  PkgManager ,  FileManager ,  SvnManager ,  PkgUrl ,  Dialog ,  HandleAjaxError ,  Me ,  console ,  productList ,  pkgDetail ,  versionDetail ,  PKG_DEFAULT_USER ,  PKG_DEFAULT_FRAMEWORK_TYPE ,  PKG_DEFAULT_VERSION){

  // 包业务列表
  $scope.productList = productList;
  // 包用户列表
  // $scope.userList = PKG_USER_LIST;
  // 包框架类型
  $scope.frameworkTypeList = [{
    value: 'server',
    text: '后台server包'
  },{
    value: 'undefined',
    text: '脚本包'
  }/*,{
    value: 'plugin',
    text: 'SPP插件包'
  }*/];

  // 标签页列表
  var tabList = [{
    value: 'basic',
    text: '基本信息'
  },{
    value: 'files',
    text: '文件管理'
  },{
    value: 'process',
    text: '进程'
  },{
    value: 'dispatch',
    text: '调度&监控'
  },{
    value: 'install',
    text: '安装/启动相关'
  }/*,{
    value: 'init',
    text: 'init.xml'
  }*/];
  $scope.tabList = tabList;
  var lastTab = _.last(tabList).value;

  // TCP绑定网络类型
  $scope.ipTypeList = [{
    value: '0',
    text: '内网'
  },{
    value: '1',
    text: '外网'
  },{
    value: '2',
    text: '全网'
  },{
    value: '3',
    text: 'VIP'
  },{
    value: '4',
    text: '127.0.0.1'
  }];

  // KILL 进程信号
  $scope.killSigList = ['KILL', 'QUIT', 'TERM', 'USR1', 'HUP', 'USR2'];

  // 几个默认配置
  $scope.config = {
    ip_ype: '0',
    kill_sig: 'KILL'
  };

  // 获取业务中文名称
  $scope.getProduct = function(product){
    var item = _.findWhere(productList, {product: product});
    return item ? item.chinese : '未知';
  };

  // 获取框架类型中文名称
  $scope.getFrameworkType = function(frameworkType){
    var item = _.findWhere($scope.frameworkTypeList, {value: frameworkType});
    return item ? item.text : '未知';
  };

  // 当前标签
  var activeTab = tabList[0].value;
  $scope.setActiveTab = function(tab){
    activeTab = tab.value;
    if (tab.value === 'init') {
      refill();
    }
  };
  $scope.isActiveTab = function(value){
    return activeTab === value;
  };

  var pkg, // 包属性
      home = null; // home 路径，查看包版本快照才有

  // 创建新版本
  if (pkgDetail) {
    $scope.newVersion = true;
    $scope.pkg = pkg = _.pick(pkgDetail, ['product', 'name', 'frameworkType', 'user', 'path']);
    pkg.author = Me.getUsername();

    // 将上一个版本号末位加一
    var nums = pkgDetail.version.split('.');
    nums.push((+nums.pop()) + 1);
    pkg.version = nums.join('.');

  // 查看包版本快照
  } else if (versionDetail) {
    $scope.readonly = true;
    home = versionDetail.exportPath;
    $scope.inited = true;
    $scope.pkg = pkg = _.pick(versionDetail, ['product', 'name', 'version', 'frameworkType', 'author', 'user', 'remark', 'path']);

  // 创建新包
  } else {
    $scope.newPackage = true;
    $scope.pkg = pkg = {};
    pkg.product = $stateParams.product;
    pkg.version = PKG_DEFAULT_VERSION;
    pkg.author = Me.getUsername();
    pkg.user = PKG_DEFAULT_USER;
    pkg.frameworkType = PKG_DEFAULT_FRAMEWORK_TYPE;
  }

  // 是否有下一步 (标签)
  $scope.hasNextStep = function(){
    return lastTab !== activeTab;
  };

  // 下一步 (标签)
  $scope.nextStep = function(){
    var nextTab;
    _.some(tabList, function(tab, index){
      var found = tab.value === activeTab;
      if (found) {
        nextTab = tabList[index + 1];
      }
      return found;
    });
    activeTab = nextTab.value;
  };

  // 开始创建（新包或新版本）
  $scope.startCreate = function(){
    // 创建新版本，直接切换到文件管理标签
    if ($scope.newVersion) {
      $scope.inited = true;
      activeTab = 'files';
      return;
    }

    $scope.startingCreate = true;

    // 先检测包是否存在
    PkgManager.exist(pkg)
    .then(function(data){
      console.log('PkgManager.exist', data);
      if (data.exist) {
        $scope.startingCreate = false;
        Dialog.alert({
          message: '包已存在！',
          level: 'warning'
        });
      } else {
        // 不存在则准备创建
        createPackage(pkg);
      }
    })['catch'](function(reason){
      $scope.startingCreate = false;
      HandleAjaxError('检测包是否存在失败！', reason);
    });

    return false;
  };

  // 准备创建新包
  function createPackage(pkg) {
    PkgManager.create(pkg)
    .then(function(data){
      console.log('PkgManager.create', data);
      // 准备创建成功后，初始化前台
      initPackage();
    })['catch'](function(reason){
      HandleAjaxError('创建包失败！', reason);
    })['finally'](function(){
      $scope.startingCreate = false;
    });
  }

  // 文件管理及配置信息初始化
  function initialize() {
    $scope.confirmed = true;
    ls();
    getInitXml();
  }

  // 当前目录信息
  var wd = [],
      _wd = [];
  function pwd(name) {
    return (name ? wd.concat(name) : wd).join('/');
  }

  $scope.listPwd = function(){
    return wd.slice(0, -1);
  };
  $scope.lastPwd = function(){
    return wd[wd.length - 1];
  };
  $scope.cd = function(index){
    wd = wd.slice(0, index);
    ls();
  };

  // 列出指定目录文件列表
  function ls(file) {
    $scope.lsing = true;
    if (file) {
      file.lsing = true;
    }
    if ($scope.filesFirstLoading === undefined) {
      $scope.filesFirstLoading = true;
      svnStatus();
    }
    FileManager.ls(pkg, pwd(), home)
    .then(function(data){
      console.log('FileManager.ls', data);
      $scope.fileList = data;
    })['catch'](function(reason){
      wd = _wd;
      HandleAjaxError('查询文件目录失败！', reason);
    })['finally'](function(){
      $scope.lsing = false;
      if (file) {
        file.lsing = false;
      }
      $scope.filesFirstLoading = false;
    });
  }

  // 获取工作目录 svn status
  function svnStatus() {
    if (!$scope.newVersion) {
      return;
    }
    $scope.svnProcessing = true;
    SvnManager.status(pkg)
    .then(function(data){
      console.log('SvnManager.status', data);
      $scope.changeList = data;
    })['catch'](function(reason){
      HandleAjaxError('查询svn状态失败！', reason);
    })['finally'](function(){
      $scope.svnProcessing = false;
    });
  }

  $scope.ls = ls;
  $scope.svnStatus = svnStatus;

  // 还原工作目录的文件
  $scope.svnRevert = function(item){
    if (item) {
      item.processing = true;
    }
    SvnManager.revert(pkg, item.file)
    .then(function(data){
      console.log('SvnManager.revert', data);
      svnStatus();
    })['catch'](function(reason){
      HandleAjaxError('svn还原失败！', reason);
    })['finally'](function(){
      if (item) {
        item.processing = false;
      }
    });
  };

  // 还原工作目录的所有文件
  $scope.svnRevertAll = function(){
    if (!$scope.changeList || !$scope.changeList.length) {
      return;
    }
    $scope.svnProcessing = true;
    var finished = 0;
    function count() {
      finished += 1;
      if (finished >= $scope.changeList.length) {
        $scope.svnProcessing = false;
        ls();
        svnStatus();
      }
    }
    angular.forEach($scope.changeList, function(item){
      if (item.action === 'unversion') {
        // 如果是 unversion ，则删除文件
        FileManager.rm(pkg, item.file)
        .then(function(data){
          console.log('FileManager.rm', data);
        })['catch'](function(reason){
          HandleAjaxError('还原新增文件失败！', reason);
        })['finally'](count);
      } else {
        // 文件正处于其它处理中
        if (item.processing) {
          return count();
        }

        // 其它情况下调用接口还原
        item.processing = true;
        SvnManager.revert(pkg, item.file)
        .then(function(data){
          console.log('SvnManager.revert', data);
        })['catch'](function(reason){
          HandleAjaxError('svn还原失败！', reason);
        })['finally'](function(){
          if (item) {
            item.processing = false;
          }
        })['finally'](count);
      }
    });
  };

  // 根据文件类型设置图标 class
  $scope.getFileClass = function(file){
    switch (file.type) {
      case 'dir':
        return 'file-icon-dir';
      default:
        return 'file-icon-file';
    }
  };

  // 文件名是否可点击
  $scope.fileCanClick = function(file){
    return file.name !== 'init.xml' || wd.length;
  };

  // 文件能被删除/重命名/修改权限
  $scope.fileCanRemove = $scope.fileCanRename = $scope.fileCanChmod = function(file){
    return !file.processing && (file.type === 'dir' ? file.name !== '..' : file.name !== 'init.xml' || wd.length) && !$scope.readonly;
  };

  // 文件可以下载
  $scope.fileCanDownload = function(file){
    return !file.processing && file.type === 'file';
  };

  // 点击文件名时
  $scope.clickFile = function(file){
    if (file.type === 'dir') {
      // 如果是目录
      // 如果文件正在其它处理中，则不处理
      if (file.lsing || file.processing) {
        return;
      }

      // 复制一份当前目录
      _wd = wd.slice();

      if (file.name === '..') {
        // 返回上一层
        wd.pop();
      } else {
        // 进入一个目录
        wd.push(file.name);
      }

      // 拉取文件列表
      ls(file);
    } else {
      // 如果是普通文件，编辑它
      $scope.$broadcast('edit file', {
        pkg: pkg,
        path: pwd(file.name),
        home: home,
        readonly: !!$scope.readonly
      });
    }
  };

  // 删除文件
  $scope.rm = function(file){
    Dialog['delete']({
      message: '确认删除文件 ' + file.name + ' 吗？',
      confirm: function(){
        Dialog.processing();
        FileManager.rm(pkg, pwd(file.name))
        .then(function(data){
          console.log('FileManager.rm', data);
          Dialog.close();
          ls();
          svnStatus();
        })['catch'](function(reason){
          HandleAjaxError('删除文件失败！', reason);
        });
      }
    });
  };

  // 创建目录
  $scope.mkdir = function(){
    Dialog.prompt({
      message: '请输入目录名',
      input: '',
      inputTitle: '请输入 a-z,A-Z,0-9,-,.',
      pattern: /^[\w\.\-]+$/,
      confirm: function(name){
        if (!name) {
          Dialog.alert({
            message: '请输入有效的名称！',
            level: 'error'
          });
          return;
        }
        if (name === 'admin' && !pwd()) {
          Dialog.alert({
            message: '/admin 是保留目录，请更换名称！',
            level: 'error'
          });
          return;
        }
        Dialog.processing();
        FileManager.mkdir(pkg, pwd(name))
        .then(function(data){
          console.log('FileManager.mkdir', data);
          Dialog.close();
          ls();
          svnStatus();
        })['catch'](function(reason){
          HandleAjaxError('创建目录失败！', reason);
        });
      }
    });
  };

  // 重命名文件
  $scope.rename = function(file){
    Dialog.prompt({
      message: '请输入新的文件名',
      input: file.name,
      inputTitle: '请输入 a-z,A-Z,0-9,-,.',
      pattern: /^[\w\.\-]+$/,
      confirm: function(name){
        if (!name) {
          Dialog.alert({
            message: '请输入有效的名称！',
            level: 'error'
          });
          return;
        }
        Dialog.processing();
        FileManager.rename(pkg, pwd(file.name), name)
        .then(function(data){
          console.log('FileManager.rename', data);
          Dialog.close();
          file.name = name;
          svnStatus();
        })['catch'](function(reason){
          HandleAjaxError('修改文件名失败！', reason);
        });
      }
    });
  };

  // 修改文件权限
  $scope.chmod = function(file){
    Dialog.prompt({
      message: '请输入新的权限值',
      input: file.mode,
      inputTitle: '请输入三位八进制数字',
      pattern: /^[0-7]{3}$/,
      confirm: function(mode){
        if (!name) {
          Dialog.alert({
            message: '请输入有效的权限值！',
            level: 'error'
          });
          return;
        }
        Dialog.processing();
        FileManager.chmod(pkg, pwd(file.name), mode)
        .then(function(data){
          console.log('FileManager.chmod', data);
          Dialog.close();
          file.mode = mode;
        })['catch'](function(reason){
          HandleAjaxError('修改文件权限失败！', reason);
        });
      }
    });
  };

  // 下载文件
  $scope.download = function(file){
    window.open(
      PkgUrl.build(pkg, home) + '/raw/' + pwd(file.name) +
      (home ? '?' + $.param({home: home}) : '')
    );
  };

  // 下载包版本快照 tar 包
  $scope.downloadTar = function(){
    window.open(PkgUrl.build(pkg, true) + '/tar');
  };

  // 配置项
  var configKeys = ('app_name port ip_type udp_port kill_sig start stop crontab clear_file' +
          ' substitute md5 monitor resolve link make install_on_start install_on_complete' +
          ' check_installation start_init start_on_started stop_on_stoped').split(' '),
      configBaseKeys = 'app_name port ip_type udp_port kill_sig'.split(' '),
      pkgBaseKeys = 'product name user version author'.split(' ');

  // 获取 init.xml 配置内容
  function getInitXml() {
    FileManager.content(pkg, 'init.xml', 'gbk', home)
    .then(function(data){
      console.log('FileManager.content', data);
      $scope.xml = {
        content: data.content
      };

      if (!$scope.readonly) {
        $scope.frameworkTypeChanged();
      }

      var conf = parse(data.content);
      angular.forEach(configKeys, function(key){
        if (conf.hasOwnProperty(key)) {
          $scope.config[key] = conf[key];
        }
      });
      // 脚本包默认填监控监控进程为 'null'
      if (pkg.frameworkType === 'undefined' && !$scope.config.app_name) {
        $scope.config.app_name = 'null';
      }
    })['catch'](function(reason){
      HandleAjaxError('获取配置文件失败！', reason);
    });
  }

  // 解析 init.xml 配置内容
  function parse(str) {
    var regAll = /<(\w+)>([\s\S]*?)<\/\1>/g,
        regBase = /^\s*(\w+)="(.*)"/gm,
        match,
        data = {},
        baseInfo = {};
    while ((match = regAll.exec(str)) !== null) {
      data[match[1]] = $.trim(match[2]);
    }
    if (data.base_info) {
      while ((match = regBase.exec(data.base_info)) !== null) {
        baseInfo[match[1]] = $.trim(match[2]);
      }
      delete data.base_info;
    }
    return _.defaults(data, baseInfo);
  }

  // 根据单项配置回填 init.xml
  function refill() {
    if (!$scope.xml) {
      return;
    }
    var content = $scope.xml.content,
        data = _.defaults($scope.config, _.pick(pkg, pkgBaseKeys)),
        baseKeys = configBaseKeys.concat(pkgBaseKeys);
    angular.forEach(data, function(value, key){
      var replace = value.replace(/\$/g, '$$$$');
      if (_.indexOf(baseKeys, key) !== -1) {
        if (key === 'version') {
          // 版本号去掉最后一位
          replace = replace.replace(/\.\d+$/, '');
        }
        content = content.replace(new RegExp('^\\s*' + key + '=".*"', 'm'), key + '="' + replace + '"');
      } else {
        var reg = new RegExp('<' + key + '>(?:[\\s\\S]*?)<\\/' + key + '>');
        if (reg.test(content)) {
          content = content.replace(reg, '<' + key + '>\n' + replace + '\n</' + key + '>');
        } else if (value) {
          content += '\n\n' + '<' + key + '>\n' + value + '\n</' + key + '>';
        }
      }
    });
    $scope.xml.content = content;
  }

  // 框架变更时，需要对应更改 init.xml 中的 `is_shell=true`
  $scope.frameworkTypeChanged = function(){
    console.log('frameworkTypeChanged');
    if ($scope.xml && $scope.xml.content) {
      console.log('frameworkTypeChanged inited');
      if (pkg.frameworkType !== 'plugin') {
        var content = $scope.xml.content.replace(/is_shell=true\r?\n/, '');
        if (pkg.frameworkType !== 'server') {
          content = 'is_shell=true\n' + content;
        }
        $scope.xml.content = content;
      }
    }
  };

  // 调出上传文件对话框
  $scope.upload = function(){
    $scope.$broadcast('upload', {path: pwd()});
  };

  // 调出现网拉取文件对话框
  $scope.pull = function(){
    $scope.$broadcast('pull', {pkg: pkg, path: pwd()});
  };

  // 获取配置表单数据
  function getOptions(){
    refill();
    var options = {
      path: pkg.path || '/' + pkg.product + '/' + pkg.name,
      confUser: pkg.user,
      confAuthor: pkg.author,
      confFrameworkType: pkg.frameworkType,
      confRemark: pkg.remark,
      isShell: pkg.frameworkType === 'undefined' ? 'true' : 'false',
      confContent: $scope.xml.content
    };
    return options;
  }

  // 保存配置
  $scope.save = function(){
    var options = getOptions();
    $scope.saving = true;
    PkgManager.save(pkg, options)
    .then(function(data){
      console.log('PkgManager.save', data);
      Dialog.alert({
        message: '保存配置成功！',
        level: 'success'
      });
    })['catch'](function(reason){
      HandleAjaxError('保存配置失败！', reason);
    })['finally'](function(){
      $scope.saving = false;
    });
  };

  // 提交创建新包或新版本
  $scope.submit = function(){
    // 监控进程名是必填的
    if (!$scope.config.app_name) {
      activeTab = 'process';
      $timeout(function(){
        var elem = $('input[name="app_name"]').tooltip({
          show: true,
          trigger: 'manual',
          title: '请输入'
        }).tooltip('show');
        elem.focus().select();
        $timeout(function(){
          elem.tooltip('hide');
        }, 2e3);
      });
      return;
    }

    var options = getOptions();
    $scope.submitting = true;
    PkgManager.submit(pkg, options, $scope.newPackage)
    .then(function(data){
      console.log('PkgManager.submit', data);
      Dialog.alert({
        message: '创建新' + ($scope.newPackage ? '包' : '版本') + '成功！',
        level: 'success',
        confirm: function(){
          Dialog.close();
          // 提交创建成功后，跳转到版本列表页
          $state.go('pkg.versions', pkg);
        }
      });
    })['catch'](function(reason){
      HandleAjaxError('创建新' + ($scope.newPackage ? '包' : '版本') + '失败！', reason);
    })['finally'](function(){
      $scope.submitting = false;
    });
  };

  // 初始化前台
  function initPackage() {
    if ($scope.newPackage) {
      // 如果是新包，切换到文件管理标签
      $scope.inited = true;
      activeTab = 'files';
      // 文件管理及配置信息初始化
      initialize();
    }
  }

  // 创建新版本或查看版本快照，直接初始化前台
  if ($scope.newVersion || $scope.readonly) {
    initialize();
    initPackage();
  }

  // 修改版本说明
  var lastRemark;
  $scope.editRemark = function(){
    lastRemark = pkg.remark;
    $scope.toEditRemark = true;
    $timeout(function(){
      $('#textarea-edit-remark').focus().select();
    });
  };
  $scope.saveRemark = function(){
    $scope.editingRemark = true;
    PkgManager.updateRemark(pkg)
    .then(function(data){
      console.log('PkgManager.saveRemark', data);
      $scope.toEditRemark = false;
    })['catch'](function(reason){
      HandleAjaxError('修改版本备注失败！', reason);
    })['finally'](function(){
      $scope.editingRemark = false;
    });
  };
  $scope.cancelEditRemark = function(){
    pkg.remark = lastRemark;
    $scope.toEditRemark = false;
  };

  // 调出安装对话框
  $scope.install = function(){
    $scope.$broadcast('install', pkg);
  };

  // 删除版本
  $scope.removeVersion = function(){
    Dialog['delete']({
      message: '确定要删除这个版本吗？',
      confirm: function(){
        Dialog.processing();
        PkgManager.removeVersion(pkg)
        .then(function(data){
          console.log('PkgManager.removeVersion', data);
          Dialog.alert({
            message: '删除版本成功！',
            level: 'success',
            confirm: function(){
              Dialog.close();
              // 删除版本后，跳转到版本列表页面
              $state.go('pkg.versions', pkg);
            }
          });
        })['catch'](function(reason){
          HandleAjaxError('删除版本失败！', reason);
        });
      }
    });
  };

  // 需要重新拉取文件列表和 svn status
  $scope.$on('need ls and svn status', function(){
    ls();
    svnStatus();
  });
}])

// 本地上传文件，现网拉取文件等对话框
.controller('NewDialogsCtrl', [
        '$scope', 'FileManager', 'HandleAjaxError', 'console',
function($scope ,  FileManager ,  HandleAjaxError ,  console){
  // 上传文件
  var modalUpload = $('#modal-upload'),
      formUpload = $('form[name="formUpload"]')/*,
      firstInputFile = $('input[type="file"]')*/;
  // $scope.uploadFiles = [{}];
  $scope.addAnUploadFile = function(){
    $scope.uploadFiles.push({});
  };
  $scope.supportMultipleFile = true;//firstInputFile.prop('files') && firstInputFile.prop('multiple');

  window.pkgUploadFileDone = function(data){
    console.log('FileManager.upload', data);
    $scope.uploadProcessing = false;
    if (data.result) {
      modalUpload.modal('hide');
      $scope.$emit('need ls and svn status');
    } else {
      HandleAjaxError('上传文件失败！', data);
    }
  };

  $scope.uploadConfirm = function(){
    $scope.uploadProcessing = true;
    formUpload.submit();
  };

  $scope.$on('upload', function(e, options){
    $scope.uploadOptions = options;
    $scope.uploadFiles = [{}];
    modalUpload.modal({
      show: true,
      backdrop: 'static'
    });
  });

  // 现网拉取文件
  var modalPull = $('#modal-pull');

  $scope.$on('pull', function(e, options){
    $scope.pullOptions = options;

    $scope.pullConfirm = function(){
      var fileList = options.fileList.replace(/\r?\n/g, ';');
      $scope.pullProcessing = true;
      FileManager.pull(options.pkg, options.path, options.ip, fileList)
      .then(function(data){
        console.log('FileManager.pull', data);
        modalPull.modal('hide');
        $scope.$emit('need ls and svn status');
      })['catch'](function(reason){
        HandleAjaxError('现网拉取文件失败！', reason);
      })['finally'](function(){
        $scope.pullProcessing = false;
      });
    };

    modalPull.modal({
      show: true,
      backdrop: 'static'
    });
  });
}])

// 文本编辑器等对话框
.controller('EditorDialogsCtrl', [
        '$scope', 'FileManager', 'Dialog', 'HandleAjaxError', 'console',
function($scope ,  FileManager ,  Dialog ,  HandleAjaxError ,  console){
  // 编辑文件
  var modalEditor = $('#modal-editor');

  // 编码列表
  $scope.charsetList = [{
    value: 'gbk',
    text: 'gbk'
  }, {
    value: 'utf-8',
    text: 'utf-8'
  }];

  // 语言列表
  $scope.languageList = [{
    value: null,
    text: 'text'
  },{
    value: 'clike',
    text: 'clike'
  },{
    value: 'css',
    text: 'css'
  },{
    value: 'htmlmixed',
    text: 'html'
  },{
    value: 'javascript',
    text: 'js'
  },{
    value: 'php',
    text: 'php'
  },{
    value: 'properties',
    text: 'ini'
  },{
    value: 'shell',
    text: 'shell'
  },{
    value: 'xml',
    text: 'xml'
  },{
    value: 'yaml',
    text: 'yaml'
  }];

  // 文件扩展与代码语言map
  var extLangMap = {
    'ini': 'properties',
    'sh': 'shell',
    'xml': 'xml',
    'yaml': 'yaml',
    'php': 'php',
    'html': 'htmlmixed',
    'js': 'javascript',
    'css': 'css',
    'json': 'javascript',
    'c': 'clike',
    'h': 'clike',
    'cpp': 'clike',
    'java': 'clike'
  };

  // 编辑器使用 CodeMirror
  var _editor,
      _languageCache = {},
      _charsetCache = {},
      _unmatchCharset = {},
      _defaultCharset = 'gbk',
      _currentCharset = _defaultCharset,
      editorOpts = {
        lineWrapping : true,
        lineNumbers: true,
        mode: null
      };

  $scope.editorOpts = editorOpts;
  $scope.editorLoaded = function(editor){
    // 获得编辑器对象
    _editor = editor;
  };

  // 设置代码语言
  $scope.setLanguage = function(lang){
    _languageCache[$scope.editorOptions.path] = lang;
    // 设置 `editorOpts.mode` 似乎不起作用
    editorOpts.mode = lang;
    _editor.setOption('mode', lang);
    CodeMirror.autoLoadMode(_editor, lang);
  };
  $scope.isCurrentLanguage = function(lang){
    return editorOpts.mode === lang;
  };
  $scope.getCurrentLanguage = function(){
    return _.findWhere($scope.languageList, {value: editorOpts.mode}).text;
  };

  // 设置文件编码
  $scope.setCharset = function(charset){
    _currentCharset = charset;
    fetchContent(charset);
  };
  $scope.isCurrentCharset = function(charset){
    return _currentCharset === charset;
  };
  $scope.getCurrentCharset = function(){
    return _.findWhere($scope.charsetList, {value: _currentCharset}).text;
  };

  // 调出编辑文件对话框
  $scope.$on('edit file', function(e, options){
    var path = options.path,
        ext = path.match(/\.([^\.]+)$/),
        // 依次优先获取缓存的、扩展名对应的代码语言
        lang = _languageCache[path] || (ext && extLangMap[ext[1]]) || null;

    // 设置 `editorOpts.mode` 似乎不起作用
    editorOpts.mode = lang;
    // editorOpts.readOnly = options.readonly;
    _editor.setOption('mode', lang);
    _editor.setOption('readOnly', options.readonly && 'nocursor');
    CodeMirror.autoLoadMode(_editor, lang);

    $scope.editorOptions = options;

    // 获取文件内容
    fetchContent();

    modalEditor.modal({
      show: true,
      backdrop: 'static'
    });
  });

  // 保存文件
  $scope.editorConfirm = function(){
    var options = $scope.editorOptions;
    $scope.editorProcessing = true;
    FileManager.updateContent(options.pkg, options.path, options.content || '', _currentCharset)
    .then(function(data){
      console.log('FileManager.updateContent', data);
      modalEditor.modal('hide');
      $scope.$emit('need ls and svn status');
    })['catch'](function(reason){
      HandleAjaxError('保存文件失败！', reason);
    })['finally'](function(){
      $scope.editorProcessing = false;
    });
  };

  // 获取文件内容
  function fetchContent(charset) {
    var options = $scope.editorOptions,
        path = options.path,
        encoding = charset || _charsetCache[path] || _defaultCharset;
    _currentCharset = encoding;
    options.content = null;
    $scope.editorLoading = true;
    $scope.editorError = false;
    FileManager.content(options.pkg, path, encoding, options.home)
    .then(function(data){
      // 获取文件内容失败
      var failed = data.content === null || data.content === false;

      // 失败且服务端返回的编码和当前编码不一致
      if (failed && data.encoding !== encoding) {
        if (! _unmatchCharset[path]) {
          _unmatchCharset[path] = {};
        }
        _unmatchCharset[path][encoding] = true;
        if (_unmatchCharset[path][data.encoding]) {
          // 如果服务器返回的编码已经被标示为错误的
          // 则提示错误
          charsetError();
        } else {
          // 否则切换为该编码
          $scope.setCharset(data.encoding);
        }
        $scope.editorError = true;

      // 失败，但服务器返回的编码和当前编码一致
      } else if (failed) {
        charsetError();
        $scope.editorError = true;

      // 成功
      } else {
        // 缓存该文件的编码
        _charsetCache[path] = encoding;
        options.content = data.content;
      }
    })['catch'](function(reason){
      $scope.editorError = true;
      HandleAjaxError('获取文件内容失败！', reason);
    })['finally'](function(){
      $scope.editorLoading = false;
    });
  }

  function charsetError() {
    Dialog.alert({
      message: '读取文件失败！请确认文件编码！',
      level: 'error'
    });
  }
}])

// 安装等对话框
.controller('InstallDialogsCtrl', [
        '$scope', '$state', '$timeout', 'PkgManager', 'DeviceManager', 'HandleAjaxError', 'Utils', 'console',
function($scope ,  $state ,  $timeout ,  PkgManager ,  DeviceManager ,  HandleAjaxError ,  Utils ,  console){
  var defaultInstallOptions = {
        ipList: null,
        startAfterComplete: 0 // 安装后启动
      },
      modalInstall = $('#modal-install'),
      textareaIpList = modalInstall.find('textarea[name="ipList"]');

  // 调出安装对话框
  $scope.$on('install', function(e, pkg){
    $scope.installOptions = _.defaults({version: pkg.version}, defaultInstallOptions);

    // 确认安装
    $scope.installConfirm = function(){
      var options = angular.copy($scope.installOptions);
      // 安装列表不能为空
      if (!options.ipList) {
        return;
      }
      var ipList = Utils.matchIps(options.ipList);
      if (!ipList) {
        return;
      }
      options.ipList = ipList;
      $scope.installProcessing = true;
      PkgManager.install(pkg, options)
      .then(function(data){
        console.log('PkgManager.install', data);
        // 安装开始后，跳转到任务详情页
        $state.go('task-detail', {taskId: data.taskId});
      })['catch'](function(reason){
        HandleAjaxError('安装失败！', reason);
      })['finally'](function(){
        $scope.installProcessing = false;
        modalInstall.modal('hide');
      });
    };

    $scope.ipListChanged = function(){
      var ipList = Utils.matchIps($scope.installOptions.ipList || '');
      $scope.ipLength = ipList ? ipList.length : 0;
    };

    $scope.batchEnabled = false;
    modalInstall.modal({
      show: true,
      backdrop: 'static'
    });
  });

  // 筛选设备
  var modalDevicesPicker = $('#modal-devices-picker'),
      attrsLoaded,
      loadingTpl = [{
        label: '加载中',
        value: null
      }],
      errorTpl = [{
        label: '查询失败',
        value: null
      }],
      nullTpl = [{
        label: '-- 不限 --',
        value: null
      }];

  $scope.pickDevices = function(){
    $scope.picker = {
      business: null,
      idc: null
    };

    if (!attrsLoaded) {
      $scope.businessList = angular.copy(loadingTpl);
      $scope.idcList = angular.copy(loadingTpl);

      // 列出设备属性列表（业务、机房）
      DeviceManager.listAttrs()
      .then(function(data){
        $scope.businessList = angular.copy(nullTpl)
        .concat(_.map(data.businessList, function(row){
          return {
            label: row,
            value: row
          };
        }));
        $scope.idcList = angular.copy(nullTpl)
        .concat(_.map(data.idcList, function(row){
          return {
            label: row,
            value: row
          };
        }));
        attrsLoaded = true;
      })['catch'](function(){
        $scope.businessList = angular.copy(errorTpl);
        $scope.idcList = angular.copy(errorTpl);
      });
    }

    modalDevicesPicker.modal({
      show: true,
      backdrop: 'static'
    });
    $scope.pick = function(flag){
      $scope.picking = true;
      DeviceManager.list(angular.copy($scope.picker))
      .then(function(data){
        console.log('DeviceManager.list', data);
        var ipList = _.pluck(data, 'deviceId'),
            start = 0;
        if (flag === 'w') {
          $scope.installOptions.ipList = ipList.join('\n');
        } else {
          var oldList = Utils.matchIps($scope.installOptions.ipList || '') || [];
          start = textareaIpList.val().length;
          $scope.installOptions.ipList = _.uniq(oldList.concat(ipList)).join('\n');
        }
        $scope.ipListChanged();

        // 设置选中
        $timeout(function(){
          var end = textareaIpList.val().length;
          console.log('selectRange', start, end);
          textareaIpList.selectRange(start, end);
        });
      })['catch'](function(reason){
        HandleAjaxError('筛选设备失败！', reason);
      })['finally'](function(){
        $scope.picking = false;
        modalDevicesPicker.modal('hide');
      });
    };
  };
}])

// 包 (abstract)
.controller('PkgCtrl', [
        '$scope', 'pkg',
function($scope ,  pkg){
  $scope.pkg = pkg;
  $scope.product = pkg.product;
  $scope.name = pkg.name;
  $scope.install = function(pkg){
    $scope.$broadcast('install', pkg);
  };
}])

// 指定业务的包列表
.controller('ProductCtrl', [
        '$scope', '$stateParams', 'pkgList',
function($scope ,  $stateParams ,  pkgList){
  var pkgTotalList,
      inited,
      filters = {
        author: null,
        version: null,
        order: 'desc',
        page: 1
      };
  $scope.product = $stateParams.product;
  $scope.name = $stateParams.name;
  $scope.itemsPerPage = 20;
  $scope.totalItems = pkgList.length;
  $scope.filters = filters;

  // 从包列表中收集作者列表
  $scope.authorList = _.uniq(_.pluck(pkgList, 'author')).sort();

  $scope.setAuthor = function(author) {
    filters.author = author;
    filter();
  };

  $scope.setOrder = function(order) {
    filters.order = order;
    filter();
  };

  $scope.nameChange = function(){
    filter();
  };

  // 过滤参数更改
  $scope.$watch('filters', function(){
    var begin = ((filters.page - 1) * $scope.itemsPerPage),
        end = begin + $scope.itemsPerPage;
    $scope.pkgList = pkgTotalList.slice(begin, end);
    if (inited) {
      // 切换页码时，将滚动条置顶
      window.scrollTo(0, 0);
    } else {
      inited = true;
    }
  }, true);

  // 过滤：页码、排序、作者、名称等
  function filter() {
    var list = pkgList.slice();
    if (filters.author) {
      list = _.filter(list, function(row){
        return row.author === filters.author;
      });
    }
    if (filters.name) {
      var v = filters.name;
      list = _.filter(list, function(row){
        return row.name.indexOf(v) !== -1;
      });
    }
    if (filters.order === 'asc') {
      list.reverse();
    }
    pkgTotalList = list;
    $scope.totalItems = list.length;
    filters.page = 1;
  }

  filter();
}])

// 版本列表
.controller('PkgVersionsCtrl', [
        '$scope', '$state', 'PkgUrl', 'PkgManager', 'Dialog', 'HandleAjaxError', 'console', 'pkg', 'versionList',
function($scope ,  $state ,  PkgUrl ,  PkgManager ,  Dialog ,  HandleAjaxError ,  console ,  pkg ,  versionList){
  var versionTotalList,
      inited = false,
      filters = {
        author: null,
        ignoreEmpty: false,
        version: null,
        order: 'desc',
        page: 1
      };
  $scope.filters = filters;
  $scope.itemsPerPage = 10;

  // 从版本列表中收集作者列表
  $scope.authorList = _.uniq(_.pluck(versionList, 'author')).sort();

  // 下载包版本快照 tar 包
  $scope.downloadTar = function(item){
    window.open(PkgUrl.build(item, true) + '/tar');
  };

  // 删除版本
  $scope.removeVersion = function(item){
    Dialog['delete']({
      message: '确定要删除这个版本吗？',
      confirm: function(){
        Dialog.processing();
        PkgManager.removeVersion(item)
        .then(function(data){
          console.log('PkgManager.removeVersion', data);
          Dialog.alert({
            message: '删除版本成功！',
            level: 'success',
            confirm: function(){
              Dialog.close();
              // $state.go('pkg.versions', item, {reload: true});
            }
          });
          // 删除版本后，移除页面中的该版本
          function rejection(row) {
            return item.version === row.version;
          }
          versionList = _.reject(versionList, rejection);
          versionTotalList = _.reject(versionTotalList, rejection);
          $scope.versionList = _.reject($scope.versionList, rejection);
        })['catch'](function(reason){
          HandleAjaxError('删除版本失败！', reason);
        });
      }
    });
  };

  $scope.setAuthor = function(author) {
    filters.author = author;
    filter();
  };

  $scope.setOrder = function(order) {
    filters.order = order;
    filter();
  };

  $scope.ignoreEmptyChange = function(){
    filter();
  };

  $scope.versionChange = function(){
    filter();
  };

  // 过滤参数更改
  $scope.$watch('filters', function(){
    var begin = ((filters.page - 1) * $scope.itemsPerPage),
        end = begin + $scope.itemsPerPage;
    $scope.versionList = versionTotalList.slice(begin, end);
    if (inited) {
      window.scrollTo(0, 0);
    } else {
      inited = true;
    }
  }, true);

  // 过滤：页码、排序、作者、版本等
  function filter() {
    var list = versionList.slice();
    if (filters.author) {
      list = _.filter(list, function(row){
        return row.author === filters.author;
      });
    }
    if (filters.ignoreEmpty) {
      list = _.filter(list, function(row){
        return row.instanceCount;
      });
    }
    if (filters.version) {
      var v = filters.version.replace('v', '');
      list = _.filter(list, function(row){
        return row.version.indexOf(v) === 0;
      });
    }
    if (filters.order === 'asc') {
      list.reverse();
    }
    versionTotalList = list;
    $scope.totalItems = list.length;
    filters.page = 1;
  }

  filter();
}])

// 包权限设置
.controller('PkgSettingsCtrl', [
        '$scope', '$timeout', 'PkgManager', 'Dialog', 'HandleAjaxError', 'console', 'settings', 'pkg',
function($scope ,  $timeout ,  PkgManager ,  Dialog ,  HandleAjaxError ,  console ,  settings ,  pkg){
  var table = $('#table-settings'),
      _options,
      roleMap = {
        isAdmin: 'admin',
        isSuperOperator: 'super_operator',
        isOperator: 'operator'
      };
  pkg.visibility = settings.visibility;
  pkg.authorized = settings.authorized;
  $scope.userList = settings.userList;
  $scope.readonly = true;

  // 获取当前权限参数
  function getOptions() {
    return {
      visibility: pkg.visibility,
      userList: angular.copy($scope.userList)
    };
  }

  // 编辑权限
  $scope.edit = function(){
    _options = getOptions();
    $scope.readonly = false;
  };

  // 取消编辑
  $scope.cancel = function(){
    pkg.visibility = _options.visibility;
    $scope.userList = _options.userList;
    $scope.readonly = true;
  };

  // 添加一个用户
  $scope.addOne = function(){
    var item = {
      unsynced: true
    };
    angular.forEach(roleMap, function(role, key){
      item[key] = false;
    });
    $scope.userList.push(item);
    $timeout(function(){
      table.find('.form-control:last').focus();
    });
  };

  // 删除一个用户
  $scope.remove = function(item, index){
    $scope.userList.splice(index, 1);
  };

  // 保存包权限设置
  $scope.save = function(){
    var options = getOptions(),
        oldUserList = _options.userList,
        newUserList = validate(options.userList),
        isPublic = options.visibility === 'public',
        store = {},
        remove = {},
        params = {};

    if (newUserList === null) {
      // 无效的用户名
      Dialog.alert({
        message: '请输入有效的用户名！',
        level: 'warning'
      });
      return;
    } else if (!angular.isArray(newUserList)) {
      // 重复的用户名
      Dialog.alert({
        message: '有重复的用户名 "' + newUserList + '"" ！',
        level: 'warning'
      });
      return;
    }

    angular.forEach(roleMap, function(role){
      store[role] = [];
      remove[role] = [];
    });

    // 检测角色变换
    angular.forEach(oldUserList, function(row){
      var item = _.findWhere(newUserList, {name: row.name});
      if (item) {
        angular.forEach(roleMap, function(role, key){
          if (isPublic && role === 'operator') {
            return;
          }
          // 权限发生变化
          if (row[key] !== item[key]) {
            if (item[key]) {
              // 新增角色
              store[role].push(item.name);
            } else {
              // 删除角色
              remove[role].push(item.name);
            }
          }
        });
      } else {
        angular.forEach(roleMap, function(role){
          if (isPublic && role === 'operator') {
            return;
          }
          // 删除角色
          remove[role].push(row.name);
        });
      }
    });
    angular.forEach(newUserList, function(row){
      if (!_.findWhere(oldUserList, {name: row.name})) {
        angular.forEach(roleMap, function(role, key){
          if (isPublic && role === 'operator') {
            return;
          }
          if (row[key]) {
            // 新增角色
            store[role].push(row.name);
          }
        });
      }
    });

    params.store = store;
    params.remove = remove;

    // 是否有变更
    var changed = _.some(params, function(row){
      return _.some(row, function(list){
        return list.length;
      });
    });

    // 包属性更改
    if (options.visibility !== _options.visibility) {
      params.visibility = options.visibility;
      changed = true;
    }

    if (!changed) {
      // 没有任何变更
      Dialog.alert({
        message: '没有任何变更！',
        level: 'warning'
      });
      return;
    }

    // 保存包权限设置
    $scope.storing = true;
    PkgManager.updateSettings(pkg, params)
    .then(function(data){
      console.log('PkgManager.updateSettings', data);
      Dialog.alert({
        message: '保存包权限设置成功！',
        level: 'success'
      });
      $scope.readonly = true;
      $scope.userList = _.filter($scope.userList, function(row){
        row.unsynced = false;
        return _.some(roleMap, function(role, key){
          return row[key];
        });
      });
    })['catch'](function(reason){
      HandleAjaxError('保存包权限设置失败！', reason);
    })['finally'](function(){
      $scope.storing = false;
    });
  };

  // 验证用户列表
  function validate(userList) {
    var invalid,
        duplicated,
        nameRegExp = /^\w+$/,
        names = [],
        list = _.filter(userList, function(row){
          if (!row.name || !nameRegExp.test(row.name)) {
            // 无效的用户名
            invalid = true;
          } else if (_.indexOf(names, row.name) !== -1) {
            // 重复的用户名
            duplicated = row.name;
          } else {
            names.push(row.name);
          }
          return _.some(roleMap, function(role, key){
            return row[key];
          });
        });
    return invalid ? null : duplicated ? duplicated : list;
  }
}])

// 实例列表
.controller('PkgInstancesCtrl', [
        '$scope', '$stateParams', '$state', '$timeout', 'Dialog', 'PkgManager', 'Utils', 'pkg', 'instanceData',
function($scope ,  $stateParams ,  $state ,  $timeout ,  Dialog ,  PkgManager ,  Utils ,  pkg ,  instanceData){
  var defaults = {
        page: 1,
        ips: null
      },
      filters = _.defaults($stateParams, defaults),
      info = instanceData.info;
  $scope.filters = filters;
  $scope.totalItems = instanceData.total;
  // 如果指定IP，则单页全部显示
  $scope.itemsPerPage = filters.ips ? $scope.totalItems : 50;
  $scope.instanceList = instanceData.instanceList;

  // 查看单个版本的实例列表
  if ($stateParams.version) {
    pkg.version = $stateParams.version;
  }

  // 处理默认数据，以对比过滤参数是否更改
  function trim(filters) {
    var options = {};
    angular.forEach(filters, function(value, key){
      options[key] = defaults[key] === value ? null : value;
    });
    return options;
  }

  $scope.$watch('filters', function(newValue, oldValue){
    var options = trim(newValue);
    if (!angular.equals(options, trim(oldValue))) {
      window.scrollTo(0, 0);
      $state.go('pkg.instances', _.defaults({}, pkg, options));
    }
  }, true);

  // 过滤 IP
  $scope.filterIps = function(){
    var ips = filters.ips ? filters.ips.split(',') : null;
    // 通知过滤 IP 对话框
    $scope.$parent.$broadcast('filter ips', ips);
  };

  // 勾选
  $scope.checkedAtLeastOne = false;
  $scope.all = {
    checked: false
  };
  $scope.rowChecked = function(){
    var all = true;
    angular.forEach($scope.instanceList, function(row){
      if (! row.checked) {
        all = false;
      } else {
        $scope.checkedAtLeastOne = true;
      }
    });
    $scope.all.checked = all;
  };
  $scope.allChecked = function(){
    var all = $scope.all.checked;
    angular.forEach($scope.instanceList, function(row){
      row.checked = all;
    });
    $scope.checkedAtLeastOne = all;
  };

  // 获取操作参数
  function getOptions(type) {
    var options = {
          frameworkType: info.frameworkType,
          packageUser: info.packageUser,
          ipList: [],
          installPath: null,
          version: null
        },
        multiPath = false,
        multiVersion = false;

    angular.forEach($scope.instanceList, function(row){
      if (row.checked) {
        options.ipList.push(row.ip);
        if (!options.installPath) {
          options.installPath = row.installPath;
        } else if (options.installPath !== row.installPath) {
          multiPath = true;
        }
        if (!options.version) {
          options.version = row.packageVersion;
        } else if (options.version !== row.packageVersion) {
          multiVersion = true;
        }
      }
    });

    if (!options.ipList.length) {
      Dialog.alert({
        message: '请至少选中一个IP',
        level: 'warning'
      });
      return;
    }

    switch (type) {
      case 'update':
      case 'rollback':
        if (multiVersion) {
          // 升级/回滚，需要同一版本
          Dialog.alert({
            message: '你选择的实例包含不同版本，请分开选择！',
            level: 'warning'
          });
          return;
        }
        /* falls through */
      case 'maintenance':
        if (multiPath) {
          // 升级/回滚/启动/停止/重启/卸载，需要同一安装路径
          Dialog.alert({
            message: '你选择的实例包含不同安装路径，请分开选择！',
            level: 'warning'
          });
          return;
        }
    }

    var fields;
    switch (type) {
      // 升级
      case 'update':
        options.fromVersion = options.version;
        fields = ['ipList', 'installPath', 'fromVersion'];
        break;

      // 回滚
      case 'rollback':
        options.currentVersion = options.version;
        fields = ['ipList', 'installPath', 'currentVersion'];
        break;

      // 启动/停止/重启/卸载
      case 'maintenance':
        fields = ['ipList', 'installPath', 'frameworkType', 'packageUser'];
        break;

      // 获取 IP 列表
      default:
        fields = ['ipList'];
    }
    return _.pick(options, fields);
  }

  $scope.update = function(){
    var options = getOptions('update');
    if (options) {
      $scope.$parent.$broadcast('update', options);
    }
  };

  $scope.rollback = function(){
    var options = getOptions('rollback');
    if (options) {
      $scope.$parent.$broadcast('rollback', options);
    }
  };

  $scope.maintenance = function(operation){
    var options = getOptions('maintenance');
    if (options) {
      $scope.$parent.$broadcast('maintenance', operation, options);
    }
  };

  // 获取勾选的 IP 列表，用于复制
  $scope.getIpsToCopy = function(){
    var options = getOptions();
    return options ? options.ipList.join('\n') : null;
  };

  // 复制后提示
  $scope.copied = Utils.copied;

  $scope.$on('$destroy', function(){
    // 离开时，删除 `version` 参数
    delete pkg.version;
  });
}])

// 实例列表 对话框
.controller('PkgInstancesDialogsCtrl', [
        '$scope', '$state', '$timeout', 'PkgManager', 'HandleAjaxError', 'console', 'Utils', 'pkg',
function($scope ,  $state ,  $timeout ,  PkgManager ,  HandleAjaxError ,  console ,  Utils ,  pkg){
  // 升级
  var modalUpdate = $('#modal-update'),
      updateDefaults = {
        forceUpdate: 1,
        stopBeforeUpdate: 1,
        restartAfterUpdate: 1,
        updateAppName: 0,
        updatePort: 1,
        updateStartStopScript: 1
      };

  $scope.$on('update', function(e, options){
    $scope.updateOptions = _.defaults(options, updateDefaults);
    $scope.updateVersions = [{
      label: '加载中',
      value: null
    }];
    $scope.updateOptions.toVersion = null;
    $scope.getUpdateRemark = null;

    // 先获取可升级的版本列表
    PkgManager.getGreaterVersionList(pkg.product, pkg.name, options.fromVersion)
    .then(function(data){
      console.log('PkgManager.getGreaterVersionList', data);
      $scope.updateVersions = _.map(data, function(row){
        return {
          label: 'v' + row.version,
          value: row.version
        };
      });
      if (data.length) {
        $scope.updateOptions.toVersion = data[0].version;
        // 版本说明
        $scope.getUpdateRemark = function(){
          var item = _.findWhere(data, {version: $scope.updateOptions.toVersion});
          if (item) {
            return item.remark;
          }
        };
      } else {
        $scope.updateVersions = [{
          label: '没有找到更新版本',
          value: null
        }];
      }
    })['catch'](function(reason){
      HandleAjaxError('查询更新版本失败！', reason);
    });

    $scope.updateConfirm = function(){
      $scope.updateProcessing = true;
      PkgManager.update(pkg, options)
      .then(function(data){
        console.log('PkgManager.update', data);
        modalUpdate.modal('hide');
        $state.go('task-detail', {taskId: data.taskId});
      })['catch'](function(reason){
        $scope.updateProcessing = false;
        HandleAjaxError('升级失败！', reason);
      });
    };

    $scope.batchEnabled = false;
    modalUpdate.modal({
      show: true,
      backdrop: 'static'
    });
  });

  // 启动/停止/重启/卸载
  var modalMaintenance = $('#modal-maintenance');

  $scope.$on('maintenance', function(e, operation, options){
    $scope.maintenanceOperation = operation;
    $scope.maintenanceOptions = options;

    $scope.maintenanceConfirm = function(){
      $scope.maintenanceProcessing = true;
      PkgManager.maintenance(pkg, operation, options)
      .then(function(data){
        console.log('PkgManager.maintenance', data);
        modalMaintenance.modal('hide');
        $state.go('task-detail', {taskId: data.taskId});
      })['catch'](function(reason){
        $scope.maintenanceProcessing = false;
        HandleAjaxError('操作失败！', reason);
      })['finally'](function(){
        modalMaintenance.modal('hide');
      });
    };

    $scope.batchEnabled = false;
    modalMaintenance.modal({
      show: true,
      backdrop: 'static'
    });
  });

  // 回滚
  var modalRollback = $('#modal-rollback');

  $scope.$on('rollback', function(e, options){
    $scope.rollbackOptions = options;

    $scope.rollbackConfirm = function(){
      $scope.rollbackProcessing = true;
      PkgManager.rollback(pkg, options)
      .then(function(data){
        console.log('PkgManager.rollback', data);
        modalRollback.modal('hide');
        $state.go('task-detail', {taskId: data.taskId});
      })['catch'](function(reason){
        $scope.rollbackProcessing = false;
        HandleAjaxError('回滚失败！', reason);
      })['finally'](function(){
        modalRollback.modal('hide');
      });
    };

    modalRollback.modal({
      show: true,
      backdrop: 'static'
    });
  });

  // 过滤 IP
  var modalFilterIps = $('#modal-filter-ips'),
      textareaIps = modalFilterIps.find('textarea');
  $scope.$on('filter ips', function(e, ips){
    $scope.filters = {
      ips: ips ? ips.join('\n') : null
    };
    $scope.clearFilters = function(){
      modalFilterIps.modal('hide');
      $state.go('pkg.instances', _.defaults({ips: null}, pkg));
    };
    $scope.setFilters = function(){
      var ips = Utils.matchIps($scope.filters.ips);
      if (ips) {
        ips = ips.join(',');
      }
      modalFilterIps.modal('hide');
      $state.go('pkg.instances', _.defaults({ips: ips}, pkg));
    };

    modalFilterIps.modal({
      show: true,
      backdrop: 'static'
    });

    $timeout(function(){
      textareaIps.focus().select();
    });
  });
}])

// 设备列表
.controller('DevicesCtrl', [
        '$scope', 'DeviceManager', 'Dialog', 'HandleAjaxError', 'Utils', 'console', 'deviceList',
function($scope ,  DeviceManager ,  Dialog ,  HandleAjaxError ,  Utils ,  console ,  deviceList){
  var deviceTotalList,
      inited,
      filters ={
        ipText: null,
        ips: null,
        business: null,
        idc: null,
        page: 1
      };

  $scope.filters = filters;
  $scope.itemsPerPage = 50;

  // 业务列表
  function setBusinessList() {
    $scope.businessList = _.uniq(_.map(deviceList, 'business')).sort();
  }

  // 机房列表
  function setIdcList() {
     $scope.idcList = _.uniq(_.pluck(deviceList, 'idc')).sort();
  }

  setBusinessList();
  setIdcList();

  // 处理默认数据，以对比过滤参数是否更改
  function trim(filters) {
    var options = angular.copy(filters);
    delete options.ipText;
    return options;
  }

  // 过滤参数更改
  $scope.$watch('filters', function(newValue, oldValue){
    var equals = angular.equals(trim(newValue), trim(oldValue));
    if (equals && inited) {
      return;
    }
    roll();
    if (inited) {
      window.scrollTo(0, 0);
    } else {
      inited = true;
    }
  }, true);

  function roll() {
    var begin = ((filters.page - 1) * $scope.itemsPerPage),
        end = begin + $scope.itemsPerPage;
    $scope.filteredDeviceList = deviceTotalList.slice(begin, end);
    $scope.all.checked = false;
  }

  // 过滤：页码、业务、机房、IP等
  function filter() {
    var ips = Utils.matchIps(filters.ipText || ''),
        business = filters.business,
        idc = filters.idc,
        list = angular.copy(deviceList);
    filters.ips = ips;
    if (ips) {
      list = _.filter(list, function(row){
        return _.contains(ips, row.deviceId);
      });
    }
    if (business !== null) {
      list = _.filter(list, function(row){
        return row.business === business;
      });
    }
    if (idc !== null) {
      list = _.filter(list, function(row){
        return row.idc === idc;
      });
    }
    deviceTotalList = list;
    $scope.totalItems = list.length;
    filters.page = 1;
  }

  $scope.filtersChanged = filter;

  // 勾选
  $scope.checkedAtLeastOne = false;
  $scope.all = {
    checked: false
  };
  $scope.rowChecked = function(){
    var all = true;
    angular.forEach($scope.filteredDeviceList, function(row){
      if (! row.checked) {
        all = false;
      } else {
        $scope.checkedAtLeastOne = true;
      }
    });
    $scope.all.checked = all;
  };
  $scope.allChecked = function(){
    var all = $scope.all.checked;
    angular.forEach($scope.filteredDeviceList, function(row){
      row.checked = all;
    });
    $scope.checkedAtLeastOne = all;
  };

  // 删除设备
  $scope.remove = function(item){
    Dialog['delete']({
      message: '确认删除设备 ' + item.deviceId + ' 吗？',
      confirm: function(){
        Dialog.processing();
        DeviceManager.removeDevices([item.deviceId])
        .then(function(data){
          console.log('DeviceManager.removeDevices', data);
          Dialog.close();

          function after(row) {
            return row.deviceId !== item.deviceId;
          }

          deviceList = _.filter(deviceList, after);
          deviceTotalList = _.filter(deviceTotalList, after);
          $scope.filteredDeviceList = _.filter($scope.filteredDeviceList, after);

          roll();
        })['catch'](function(reason){
          HandleAjaxError('删除设备失败！', reason);
        });
      }
    });
  };

  // 批量删除设备
  $scope.removeChecked = function(){
    var ips = [];
    angular.forEach($scope.filteredDeviceList, function(row){
      if (row.checked) {
        ips.push(row.deviceId);
      }
    });

    if (!ips.length) {
      Dialog.alert({
        message: '请至少选中一台设备！',
        level: 'warning'
      });
      return;
    }

    Dialog['delete']({
      message: '确认删除这 ' + ips.length + ' 台设备吗？',
      confirm: function(){
        Dialog.processing();
        DeviceManager.removeDevices(ips)
        .then(function(data){
          console.log('DeviceManager.removeDevices', data);
          Dialog.close();

          function after(row) {
            return !_.contains(ips, row.deviceId);
          }

          deviceList = _.filter(deviceList, after);
          deviceTotalList = _.filter(deviceTotalList, after);
          $scope.filteredDeviceList = _.filter($scope.filteredDeviceList, after);

          roll();
        })['catch'](function(reason){
          HandleAjaxError('删除设备失败！', reason);
        });
      }
    });
  };

  // 修改选中设备
  $scope.editChecked = function(){
    var devices = _.where($scope.filteredDeviceList, {checked: true});
    if (!devices.length) {
      Dialog.alert({
        message: '请至少选中一台设备！',
        level: 'warning'
      });
      return;
    }
    $scope.$broadcast('edit devices', angular.copy(devices));
  };

  // 修改单台设备
  $scope.edit = function(item){
    $scope.$broadcast('edit devices', angular.copy([item]));
  };

  // 有设备修改
  $scope.$on('devices updated', function(e, devices){
    angular.forEach(devices, function(row){
      replace(deviceList, row);
      replace(deviceTotalList, row);
      replace($scope.filteredDeviceList, row);
    });
    setBusinessList();
    setIdcList();
  });

  function replace(list, row) {
    var item = _.findWhere(list, {deviceId: row.deviceId});
    item.business = row.business;
    item.idc = row.idc;
  }

  // 要复制的IP列表
  $scope.getIpsToCopy = function(){
    var ips = [];
    angular.forEach($scope.filteredDeviceList, function(row){
      if (row.checked) {
        ips.push(row.deviceId);
      }
    });
    return ips.join('\n');
  };

  // 复制成功
  $scope.copied = Utils.copied;

  filter();
}])

// 修改设备对话框
.controller('DevicesDialogsCtrl', [
        '$scope', 'DeviceManager', 'Dialog', 'HandleAjaxError', 'console',
function($scope ,  DeviceManager ,  Dialog ,  HandleAjaxError ,  console){
  var modalUpdateDevices = $('#modal-update-devices');

  $scope.$on('edit devices', function(e, devices){
    // console.log(devices);
    var formData = $scope.formData = {
      ips: _.pluck(devices, 'deviceId').join('\n')
    };
    var idc = devices[0].idc,
        business = devices[0].business,
        breakIdc,
        breakBusiness;
    _.some(devices.slice(1), function(row){
      if (!breakIdc && row.idc !== idc) {
        breakIdc = true;
      }
      if (!breakBusiness && row.business !== business) {
        breakBusiness = true;
      }
      return breakIdc && breakBusiness;
    });
    if (!breakIdc) {
      formData.idc = idc;
    }
    if (!breakBusiness) {
      formData.business = business;
    }
    $scope.formData = formData;

    modalUpdateDevices.modal({
      show: true,
      backdrop: 'static'
    });

    // 导入设备密码
    $scope.updateConfirm = function(){
      if (!formData.business && !formData.idc) {
        Dialog.alert({
          message: '没有任何变更！',
          level: 'warning'
        });
        return;
      }

      angular.forEach(devices, function(row){
        if (formData.business) {
          row.business = formData.business;
        }
        if (formData.idc) {
          row.idc = formData.idc;
        }
      });

      $scope.updating = true;
      DeviceManager.importDevices(devices)
      .then(function(data){
        console.log('DeviceManager.importDevices', data);
        modalUpdateDevices.modal('hide');
        $scope.$emit('devices updated', devices);
      })['catch'](function(reason){
        HandleAjaxError('修改设备信息失败！', reason);
      })['finally'](function(){
        $scope.updating = false;
      });
    };
  });
}])

// 批量导入设备密码
.controller('DevicesPasswordsCtrl', [
        '$scope', 'DeviceManager', 'Dialog', 'HandleAjaxError', 'console',
function($scope ,  DeviceManager ,  Dialog ,  HandleAjaxError ,  console){
  // 每行一组 `IP:password`
  var regExp = /^([\d\.]+)\:(.+)$/,
      formData = {};

  $scope.formData = formData;

  // 导入设备密码
  $scope.importDevicePassword = function(){
    var devices = [],
        devicesTextList = formData.devices.split(/\r?\n/g),
        allMatch = true;

    angular.forEach(devicesTextList, function(text){
      if (!text) {
        return;
      }
      var matches = text.match(regExp);
      if (matches) {
        devices.push({
          deviceId: matches[1],
          password: matches[2]
        });
      } else {
        allMatch = false;
      }
    });

    if (!allMatch || devices.length === 0) {
      Dialog.alert({
        message: '请按指定格式输入IP和密码',
        level: 'warning'
      });
      return;
    }

    $scope.importing = true;
    DeviceManager.importPasswords(devices)
    .then(function(data){
      console.log('DeviceManager.importPasswords', data);
      Dialog.alert({
        message: '导入设备密码成功！',
        level: 'success'
      });
      formData.devices = null;
    })['catch'](function(reason){
      HandleAjaxError('导入设备密码失败！', reason);
    })['finally'](function(){
      $scope.importing = false;
    });
  };
}])

// 批量导入设备密码
.controller('DevicesImportCtrl', [
        '$scope', 'DeviceManager', 'Dialog', 'HandleAjaxError', 'Utils', 'console', 'deviceAttrs',
function($scope ,  DeviceManager ,  Dialog ,  HandleAjaxError ,  Utils ,  console ,  deviceAttrs){
  var formData = {};
  $scope.formData = formData;

  $scope.businessList = deviceAttrs.businessList;
  $scope.idcList = deviceAttrs.idcList;

  // 导入设备密码
  $scope.importDevicePassword = function(){
    var ips = Utils.matchIps(formData.ips || '');

    if (!ips) {
      Dialog.alert({
        message: '请输入设备 IP 列表',
        level: 'warning'
      });
      return;
    }

    var devices = _.map(ips, function(ip){
      return {
        deviceId: ip,
        idc: formData.idc,
        business: formData.business
      };
    });

    $scope.importing = true;
    DeviceManager.importDevices(devices)
    .then(function(data){
      console.log('DeviceManager.importDevices', data);
      Dialog.alert({
        message: '导入设备列表成功！',
        level: 'success'
      });
      formData.ips = null;
    })['catch'](function(reason){
      HandleAjaxError('导入设备列表失败！', reason);
    })['finally'](function(){
      $scope.importing = false;
    });
  };
}])

// 用户管理
.controller('UsersCtrl', [
        '$scope', 'UserManager', 'Dialog', 'HandleAjaxError', 'console', 'userList',
function($scope ,  UserManager ,  Dialog ,  HandleAjaxError ,  console ,  userList){
  // 每行一组 `username:password`
  var regExp = /^([a-zA-Z]\w{3,16})\:(.{5,16})$/,
      formData = {
        role: 'user'
      },
      updateData = {
      };
  $scope.userList = userList;
  $scope.roleList = [{
    value: 'user',
    text: '普通用户'
  }, {
    value: 'admin',
    text: '管理员'
  }];
  $scope.formData = formData;
  $scope.updateData = updateData;

  /*var roleMap = {};
  angular.forEach($scope.roleList, function(row){
    roleMap[row.value] = row.text;
  });
  $scope.getRoleName = function(role){
    return roleMap[role] ? roleMap[role] : role;
  };*/

  // 勾选
  $scope.checkedAtLeastOne = false;
  $scope.all = {
    checked: false
  };
  $scope.rowChecked = function(){
    var all = true;
    angular.forEach($scope.userList, function(row){
      if (! row.checked) {
        all = false;
      } else {
        $scope.checkedAtLeastOne = true;
      }
    });
    $scope.all.checked = all;
  };
  $scope.allChecked = function(){
    var all = $scope.all.checked;
    angular.forEach($scope.userList, function(row){
      row.checked = all;
    });
    $scope.checkedAtLeastOne = all;
  };

  /*$scope.updateRole = function(){
    var users = [],
        role = updateData.role;
    angular.forEach($scope.userList, function(row){
      if (row.checked) {
        users.push({
          username: row.username,
          role: role
        });
      }
    });
    if (!users.length) {
      return;
    }
    $scope.updating = true;
    UserManager.update(users)
    .then(function(data){
      console.log('UserManager.update', data);
      Dialog.alert({
        message: '修改用户角色成功！',
        level: 'success'
      });
      relist();
    })['catch'](function(reason){
      HandleAjaxError('修改用户角色失败！', reason);
    })['finally'](function(){
      $scope.updating = false;
    });
  };*/

  // 保存用户
  $scope.storeUsers = function(){
    var users = [],
        role = formData.role,
        usersTextList = formData.users.split(/\r?\n/g),
        allMatch = true;

    angular.forEach(usersTextList, function(text){
      if (!text) {
        return;
      }
      var matches = text.match(regExp);
      if (matches) {
        users.push({
          username: matches[1],
          password: matches[2],
          role: role
        });
      } else {
        allMatch = false;
      }
    });

    if (!allMatch || users.length === 0) {
      Dialog.alert({
        message: '请按指定格式输入用户名和密码',
        level: 'warning'
      });
      return;
    }

    $scope.storing = true;
    UserManager.store(users)
    .then(function(data){
      console.log('UserManager.store', data);
      Dialog.alert({
        message: '添加用户成功！',
        level: 'success'
      });
      formData.users = null;
      // 保存后，重新拉取用户列表
      relist();
    })['catch'](function(reason){
      HandleAjaxError('添加用户失败！', reason);
    })['finally'](function(){
      $scope.storing = false;
    });
  };

  // 重新拉取用户列表
  function relist() {
    UserManager.list()
    .then(function(data){
      $scope.userList = data;
    })['catch'](function(reason){
      HandleAjaxError('查询用户列表失败！', reason);
    });
  }
}])

// 业务管理
.controller('ProductManageCtrl', [
        '$scope', 'PkgManager', 'Dialog', 'HandleAjaxError', 'console', 'productList',
function($scope ,  PkgManager ,  Dialog ,  HandleAjaxError ,  console ,  productList){
  // 每行一组 `product:chinese`
  var regExp = /^(\w+)\:(.+)$/,
      formData = {};

  $scope.formData = formData;
  $scope.productList = productList;

  // 勾选
  $scope.checkedAtLeastOne = false;
  $scope.all = {
    checked: false
  };
  $scope.rowChecked = function(){
    var all = true;
    angular.forEach($scope.productList, function(row){
      if (! row.checked) {
        all = false;
      } else {
        $scope.checkedAtLeastOne = true;
      }
    });
    $scope.all.checked = all;
  };
  $scope.allChecked = function(){
    var all = $scope.all.checked;
    angular.forEach($scope.productList, function(row){
      row.checked = all;
    });
    $scope.checkedAtLeastOne = all;
  };

  // 删除选中的业务
  $scope.removeCheckedProducts = function(){
    var ids = [];
    angular.forEach($scope.productList, function(row){
      if (row.checked) {
        ids.push(row.product);
      }
    });
    if (!ids.length) {
      return;
    }
    $scope.removing = true;
    PkgManager.removeProducts(ids)
    .then(function(data){
      console.log('PkgManager.removeProducts', data);
      Dialog.alert({
        message: '删除业务成功！',
        level: 'success'
      });
      relist();
    })['catch'](function(reason){
      HandleAjaxError('删除业务失败！', reason);
    })['finally'](function(){
      $scope.removing = false;
    });
  };

  // 删除一个业务
  $scope.removeProduct = function(item){
    item.removing = true;
    PkgManager.removeProducts([item.product])
    .then(function(data){
      console.log('PkgManager.removeProducts', data);
      Dialog.alert({
        message: '删除业务成功！',
        level: 'success'
      });
      relist();
    })['catch'](function(reason){
      HandleAjaxError('删除业务失败！', reason);
    })['finally'](function(){
      item.removing = false;
    });
  };

  // 批量导入业务
  $scope.storeProducts = function(){
    var products = [],
        productsTextList = formData.products.split(/\r?\n/g),
        allMatch = true;

    angular.forEach(productsTextList, function(text){
      if (!text) {
        return;
      }
      var matches = text.match(regExp);
      if (matches) {
        products.push({
          product: matches[1],
          chinese: matches[2]
        });
      } else {
        allMatch = false;
      }
    });

    if (!allMatch || products.length === 0) {
      Dialog.alert({
        message: '请按指定格式输入ID和名称！',
        level: 'warning'
      });
      return;
    }

    $scope.storing = true;
    PkgManager.storeProducts(products)
    .then(function(data){
      console.log('PkgManager.storeProducts', data);
      Dialog.alert({
        message: '添加业务成功！',
        level: 'success'
      });
      formData.products = null;
      // 保存后，重新拉取业务列表
      relist();
    })['catch'](function(reason){
      HandleAjaxError('添加业务失败！', reason);
    })['finally'](function(){
      $scope.storing = false;
    });
  };

  // 重新拉取业务列表
  function relist() {
    PkgManager.relistProducts()
    .then(function(data){
      $scope.productList = data;
      // 通知顶部导航，业务列表有更新
      $scope.$parent.$broadcast('relist products');
    })['catch'](function(reason){
      HandleAjaxError('查询业务列表失败！', reason);
    });
  }
}])

// 我的账户
.controller('AccountCtrl', [
        '$scope', 'UserManager', 'Me', 'HandleAjaxError', 'Dialog', 'console',
function($scope ,  UserManager ,  Me ,  HandleAjaxError ,  Dialog ,  console){
  var formData = {};
  $scope.role = Me.getRole();
  $scope.formData = formData;

  // 更新密码
  $scope.updatePassword = function(){
    if (formData.password !== formData.password2) {
      Dialog.alert({
        message: '两次输入的密码不一致！',
        level: 'warning'
      });
      return false;
    }
    $scope.updatingPassword = true;
    UserManager.update(_.pick(formData, 'old_password', 'password'))
    .then(function(data){
      console.log('UserManager.update', data);
      Dialog.alert({
        message: '修改密码成功！',
        level: 'success'
      });
      formData.old_password = formData.password = formData.password2 = null;
    })['catch'](function(reason){
      HandleAjaxError('修改密码失败！', reason);
    })['finally'](function(){
      $scope.updatingPassword = false;
    });
  };
}]);
