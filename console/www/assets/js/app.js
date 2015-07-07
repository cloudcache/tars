/*!
 * Tars app.js
 * 应用配置
 * @author steveswwang
 */

angular.module('tars', ['ui.router', 'ngClipboard', 'ui.codemirror', 'tars.directives',
    'tars.services', 'tars.controllers'])


//--- 常量配置 start
// DEBUG 为 false 时，不会有日志输出
.constant('DEBUG', true)

// 顶部任务刷新间隔 - 30s
.constant('NAVBAR_TASK_INTERVAL', 3e4)

// 任务详情刷新间隔 - 5s
.constant('TASK_DETAIL_INTERVAL', 5e3)

// 任务列表默认时间范围 (today, yesterday, week, month, all)
.constant('TASK_DEFAULT_DATE_RANGE', 'week')

// 包用户列表
// .constant('PKG_USER_LIST', ['user_00', 'user_01', 'user_02', 'user_03', 'user_04', 'user_05',
//     'user_06', 'user_07', 'user_08', 'user_09', 'root'])

// 默认包用户
.constant('PKG_DEFAULT_USER', null)

// 默认包框架 (server, undefined)
.constant('PKG_DEFAULT_FRAMEWORK_TYPE', 'server')

// 默认包版本
.constant('PKG_DEFAULT_VERSION', '1.0.0')
//--- 常量配置 end


//--- 配置函数 start
.config([
        '$httpProvider', 'ngClipProvider',
function($httpProvider ,  ngClipProvider){
  // 添加 X-Requested-With 头，以便服务端辨别 Ajax 请求
  $httpProvider.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

  // 剪贴板 swf 路径
  ngClipProvider.setPath('/assets/lib/zeroclipboard/dist/ZeroClipboard.swf');

  // 代码编辑器语言模式配置
  CodeMirror.modeURL = '/assets/lib/codemirror/mode/%N/%N.js';
}])
//--- 配置函数 end


//--- 运行 start
.run([
        '$rootScope', 'Dialog', 'Me', 'console',
function($rootScope ,  Dialog ,  Me ,  console){
  // 页面切换错误
  $rootScope.$on('$stateChangeError', function(event, toState, toParams, fromState, fromParams, error){
    console.error('$stateChangeError', error);

    // 忽略主动中断
    if (error.status === 0) {
      return;
    }

    // 提示页面请求错误
    Dialog.alert({
      message: '请求错误',
      level: 'error',
      log: {
        url: error.config.url,
        status: error.status + ' ' + error.statusText,
        data: error.data
      }
    });
  });

  // 是否管理员
  $rootScope.isAdmin = Me.getRole() === 'admin';

  // 是否启用第三方登录
  $rootScope.authByThirdPart = window._auth_by_third_part;
}])
//--- 运行 end


//--- $state 配置 start
.config([
        '$stateProvider', '$urlRouterProvider', '$locationProvider',
function($stateProvider ,  $urlRouterProvider ,  $locationProvider){
  $locationProvider.html5Mode(true);

  $stateProvider

  // 首页
  .state('home', {
    url: '/',
    templateUrl: '/templates/index.html',
    controller: 'HomeCtrl',
    resolve: {
      productList: [
                'PkgManager',
        function(PkgManager){
              return PkgManager.listProducts();
        }
      ]
    },
    onEnter: [
              '$rootScope',
      function($rootScope){
        $rootScope.isIndex = true;
      }
    ],
    onExit: [
              '$rootScope',
      function($rootScope){
        $rootScope.isIndex = false;
      }
    ]
  })

  // 搜索结果页
  .state('search', {
    url: '/search?q&p',
    templateUrl: '/templates/search.html',
    controller: 'SearchCtrl',
    resolve: {
      resultList: [
                '$stateParams', 'PkgManager',
        function($stateParams ,  PkgManager){
          return PkgManager.search($stateParams.q, $stateParams.p);
        }
      ]
    }
  })

  // 指定业务的包列表页
  .state('product', {
    url: '/p/{product:\\w+}',
    templateUrl: '/templates/product.html',
    controller: 'ProductCtrl',
    resolve: {
      pkgList: [
                '$stateParams', 'PkgManager',
        function($stateParams ,  PkgManager){
          return PkgManager.search(null, $stateParams.product);
        }
      ]
    }
  })

  // 我的任务列表页
  .state('tasks', {
    url: '/task?range&page',
    views: {
      '': {
        templateUrl: '/templates/tasks.html'
      },
      '@tasks': {
        templateUrl: '/templates/tasks-content.html',
        controller: 'TasksCtrl',
        resolve: {
          taskData: [
                    '$stateParams', 'TaskManager',
            function($stateParams ,  TaskManager){
              return TaskManager.list($stateParams.range, $stateParams.page);
            }
          ],
          pkg: function(){
            return null;
          }
        }
      }
    }
  })

  // 任务详情页
  .state('task-detail', {
    url: '/task/{taskId:\\w+}',
    templateUrl: '/templates/task-detail.html',
    controller: 'TaskDetailCtrl',
    resolve: {
      taskDetail: [
                '$stateParams', 'TaskManager',
        function($stateParams ,  TaskManager){
          return TaskManager.detail(0, $stateParams.taskId);
        }
      ]
    }
  })

  // 创建新包页
  .state('new', {
    url: '/new?product', // 可指定业务
    views: {
      '': {
        templateUrl: '/templates/new.html',
        controller: 'NewCtrl',
        resolve: {
          productList: [
                    'PkgManager',
            function(PkgManager){
              return PkgManager.listProducts();
            }
          ],
          versionDetail: function(){
            return null;
          },
          pkgDetail: function(){
            return null;
          }
        },
      },
      'dialogs@new': { // 本地上传文件，现网拉取文件等对话框
        templateUrl: '/templates/new-dialogs.html',
        controller: 'NewDialogsCtrl',
      },
      'editor-dialogs@new': { // 文本编辑器等对话框
        templateUrl: '/templates/editor-dialogs.html',
        controller: 'EditorDialogsCtrl'
      }
    }
  })

  // 版本详情页
  .state('version-detail', {
    url: '/p/{product:\\w+}/{name:\\w+}/v{version:[\\d\\.]+}',
    views: {
      '': { // 共用 `创建新包页`
        templateUrl: '/templates/new.html',
        controller: 'NewCtrl',
        resolve: {
          productList: [
                    'PkgManager',
            function(PkgManager){
              return PkgManager.listProducts();
            }
          ],
          versionDetail: [
                    '$stateParams', 'PkgManager',
            function($stateParams ,  PkgManager){
              return PkgManager.getVersionDetail($stateParams.product, $stateParams.name, $stateParams.version);
            }
          ],
          pkgDetail: function(){
            return null;
          }
        }
      },
      'dialogs@version-detail': { // 安装等对话框
        templateUrl: '/templates/install-dialogs.html',
        controller: 'InstallDialogsCtrl',
      },
      'editor-dialogs@version-detail': { // 文本编辑器等对话框
        templateUrl: '/templates/editor-dialogs.html',
        controller: 'EditorDialogsCtrl'
      }
    }
  })

  // 包 (abstract)
  .state('pkg', {
    abstract: true,
    url: '/p/{product:\\w+}/{name:\\w+}',
    templateUrl: '/templates/pkg.html',
    controller: 'PkgCtrl',
    resolve: {
      pkg: [
                '$stateParams',
        function($stateParams){
          return $stateParams;
        }
      ]
    }
  })

  // 包的版本列表页
  .state('pkg.versions', {
    url: '',
    views: {
      '': {
        templateUrl: '/templates/pkg-versions.html',
        controller: 'PkgVersionsCtrl',
        resolve: {
          versionList: [
                    'PkgManager', 'pkg',
            function(PkgManager ,  pkg){
              return PkgManager.getVersionList(pkg.product, pkg.name);
            }
          ]
        }
      },
      'dialogs2': { // 安装等对话框
        templateUrl: '/templates/install-dialogs.html',
        controller: 'InstallDialogsCtrl',
      }
    }
  })

  // 包的实例列表页
  .state('pkg.instances', {
    url: '/instances?version&ips&page&size',
    views: {
      '': {
        templateUrl: '/templates/pkg-instances.html',
        controller: 'PkgInstancesCtrl',
        resolve: {
          instanceData: [
                    '$stateParams', 'PkgManager', 'pkg',
            function($stateParams ,  PkgManager ,  pkg){
              return PkgManager.getInstanceData(pkg.product, pkg.name, $stateParams);
            }
          ]
        }
      },
      'dialogs': { // 启动、重启、停止、卸载、回滚等操作对话框
        templateUrl: '/templates/pkg-instances-dialogs.html',
        controller: 'PkgInstancesDialogsCtrl'
      },
      'dialogs2': { // 安装、筛选设备等对话框
        templateUrl: '/templates/install-dialogs.html',
        controller: 'InstallDialogsCtrl',
      }
    }
  })

  // 包的设置页
  .state('pkg.settings', {
    url: '/settings',
    templateUrl: '/templates/pkg-settings.html',
    controller: 'PkgSettingsCtrl',
    resolve: {
      settings: [
                'PkgManager', 'pkg',
        function(PkgManager ,  pkg){
          return PkgManager.getSettings(pkg.product, pkg.name);
        }
      ]
    }
  })

  // 包的任务列表页
  .state('pkg.tasks', {
    url: '/tasks?range&page',
    templateUrl: '/templates/tasks-content.html',
    controller: 'TasksCtrl',
    resolve: {
      taskData: [
                '$stateParams', 'TaskManager', 'pkg',
        function($stateParams ,  TaskManager ,  pkg){
          return TaskManager.list($stateParams.range, $stateParams.page, pkg);
        }
      ]
    }
  })

  // 包的创建新版本页
  .state('pkg.new', {
    url: '/new',
    views: {
      '@': { // 共用 `创建新包页`
        templateUrl: '/templates/new.html',
        controller: 'NewCtrl',
        resolve: {
          productList: [
                    'PkgManager',
            function(PkgManager){
              return PkgManager.listProducts();
            }
          ],
          versionDetail: function(){
            return null;
          },
          pkgDetail: [
                    'PkgManager', 'pkg',
            function(PkgManager ,  pkg){
              return PkgManager.getPkgDetail(pkg.product, pkg.name);
            }
          ]
        }
      },
      'dialogs@pkg.new': {
        templateUrl: '/templates/new-dialogs.html',
        controller: 'NewDialogsCtrl',
      },
      'editor-dialogs@pkg.new': {
        templateUrl: '/templates/editor-dialogs.html',
        controller: 'EditorDialogsCtrl'
      }
    }
  })

  // 用户管理页
  .state('users', {
    url: '/users',
    templateUrl: '/templates/users.html',
    controller: 'UsersCtrl',
    resolve: {
      userList: [
                'UserManager',
        function(UserManager){
          return UserManager.list();
        }
      ]
    }
  })

  // 设备列表页
  .state('devices', {
    url: '/devices',
    views: {
      '': {
        templateUrl: '/templates/devices.html',
        controller: 'DevicesCtrl',
        resolve: {
          deviceList: [
                    'DeviceManager',
            function(DeviceManager){
              return DeviceManager.list();
            }
          ]
        }
      },
      'dialogs@devices': { // 修改设备信息等对话框
        templateUrl: '/templates/devices-dialogs.html',
        controller: 'DevicesDialogsCtrl',
      }
    }
  })

  // 批量导入设备列表
  .state('devices-import', {
    url: '/devices/import',
    templateUrl: '/templates/devices-import.html',
    controller: 'DevicesImportCtrl',
    resolve: {
      deviceAttrs: [
                'DeviceManager',
        function(DeviceManager){
          return DeviceManager.listAttrs();
        }
      ]
    }
  })

  // 批量导入设备密码
  .state('devices-passwords', {
    url: '/devices/passwords',
    templateUrl: '/templates/devices-passwords.html',
    controller: 'DevicesPasswordsCtrl'
  })

  // 业务管理页
  .state('product-manage', {
    url: '/p',
    templateUrl: '/templates/product-manage.html',
    controller: 'ProductManageCtrl',
    resolve: {
      productList: [
                'PkgManager',
        function(PkgManager){
          return PkgManager.listProducts();
        }
      ]
    }
  })

  // 我的账户页
  .state('account', {
    url: '/account',
    templateUrl: '/templates/account.html',
    controller: 'AccountCtrl'
  });

  $urlRouterProvider.otherwise('/');
}]);
//--- $state 配置 end
