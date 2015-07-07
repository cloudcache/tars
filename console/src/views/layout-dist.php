<!doctype html>
<html ng-app="tars">
<head>
<meta charset="utf-8">
<title><?php echo $title; ?></title>
<link rel="icon" type="image/png" href="/assets/images/favicon.png">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="google" value="notranslate">
<base href="/">

<!--[if lt IE 9]>
<script>location.href="/modern-browser-required";</script>
<![endif]-->

<link rel="stylesheet" href="/assets/css/style-97dfd20ccd.css"/>

<link rel="stylesheet" href="/assets/css/main-26421d87aa.css"/>

<script src="/assets/js/vendor-65c6988e47.js"></script>

<script src="/assets/js/main-bc8d3080e7.js"></script>

<script>
var _auth_by_third_part = <?php echo json_encode(!!Flight::get('auth_by_third_part')); ?>;
var _username = <?php echo json_encode(Flight::get('username')); ?>;
var _userrole = <?php echo json_encode(Flight::get('userrole')); ?>;
</script>
</head>
<body ng-class="{index:isIndex}">

<!-- Fixed navbar -->
<nav class="navbar navbar-default navbar-fixed-top" ng-controller="NavbarCtrl">
  <div class="container">
    <div class="navbar-header">
      <a class="navbar-brand" ui-sref="home"></a>
    </div>
    <div class="navbar-collapse collapse">
      <ul class="nav navbar-nav">
        <li class="dropdown"
          ng-class="{active:pkgActive(),open:pkgOpen}"
          ng-mouseenter="pkgEnter()"
          ng-mouseleave="pkgLeave()">
          <a ui-sref="home" ng-click="closePkg()">包管理</a>
          <ul class="dropdown-menu dropdown-pkg" ng-if="!productList || productList.length">
            <li class="dropdown-title" ng-if="loadingProducts"><a>加载业务列表中...</a></li>
            <li class="dropdown-title" ng-if="!loadingProducts"><a>业务列表</a></li>
            <li class="divider" ng-if="!loadingProducts"></li>
            <li ng-repeat="row in productList">
              <a ui-sref="product(row)" ng-click="closePkg()">{{row.chinese}}</a>
            </li>
          </ul>
        </li>
        <li ng-class="{active:devicesActive()}"><a ui-sref="devices">设备管理</a></li>
      </ul>
      <form class="navbar-form navbar-left" ng-if="!disabledNavbarForm" ng-submit="search()">
        <input type="search" class="form-control" ng-model="params.q" name="q" placeholder="输入包名称" required>
      </form>
      <div class="navbar-spinner">
        <i class="icon-spinner" ng-if="progressing"></i>
      </div>
      <ul class="nav navbar-nav navbar-right">
        <li class="navbar-icon dropdown"
          ui-sref-active="active"
          ng-class="{open:myTaskOpen}"
          ng-mouseenter="myTaskEnter()"
          ng-mouseleave="myTaskLeave()">
          <a ui-sref="tasks" ng-click="closeMyTask()"><i class="navbar-icon-cloud" ng-class="globalTaskStatus()"></i></a>
          <ul class="dropdown-menu mytask">
            <li class="task-clear" ng-if="taskList.length>10">
              <a href="#" ng-click="clearAllTask()">清除全部</a>
            </li>
            <li class="divider" ng-if="taskList.length>10"></li>
            <li ng-repeat="row in taskList">
              <span class="task-handle">
                <a ui-sref="task-detail({taskId: row.task_id})" ng-click="closeMyTask()">详情</a>
                <i class="icon-close" ng-click="clearTask(row, $index)"></i>
              </span>
              <i class="icon-status" ng-class="row.task_status"></i>
              <span class="task-pkg-name" title="{{row.name}}">{{row.name}}</span> {{row.op_type | operationType}}{{row | taskStatus:1}} （{{row | taskProgress}}台）
            </li>
            <li class="text-center" ng-if="taskList.length===0">
              没有未读任务
            </li>
            <li class="text-center" ng-if="taskLoading">
              加载中...
            </li>
            <li class="divider" ng-if="taskList.length&&taskList.length<=10"></li>
            <li class="task-clear" ng-if="taskList.length&&taskList.length<=10">
              <a href="#" ng-click="clearAllTask()">清除全部</a>
            </li>
          </ul>
        </li>
        <li class="divider"></li>
        <li class="navbar-icon" ui-sref-active="active"><a ui-sref="new"><i class="navbar-icon-create"></i></a></li>
        <li class="divider"></li>
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
            欢迎您，<span ng-bind="username"></span>
            <span class="caret"></span>
          </a>
          <ul class="dropdown-menu fixwidth">
            <li><a ui-sref="account" ng-if="!authByThirdPart">我的账户</a></li>
            <li><a ui-sref="users" ng-if="isAdmin&&!authByThirdPart">用户管理</a></li>
            <li><a ui-sref="product-manage" ng-if="isAdmin">业务管理</a></li>
            <li><a href="/signout" target="_self">退出</a></li>
          </ul>
        </li>
      </ul>
    </div><!--/.nav-collapse -->
  </div>
</nav>

<!-- 主视图 -->
<div ui-view></div>

<div class="footer">
  Powered by <a href="http://tars.qq.com/" target="_blank">TARS</a> under <a href="/LICENSE" target="_blank">BSD</a>.
</div>

<!-- 警告框 start -->
<div class="modal" id="modal-dailog" tabindex="-1" role="dialog" aria-hidden="true" ng-controller="DialogCtrl">
  <div class="modal-dialog" ng-class="options.level">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">{{options.title}}</h4>
      </div>
      <div class="modal-body">
        {{options.message}}
        <form name="formPrompt" ng-submit="options.confirm(options.input)" ng-if="type==='prompt'">
          <input type="text" class="form-control"
            title="options.inputTitle"
            ng-model="options.input"
            ng-pattern="options.pattern"
            required>
        </form>
        <pre ng-if="options.log">{{options.log}}</pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default"
          ng-if="type!=='alert'"
          ng-click="options.cancel()"
          ng-disabled="processing">
          {{options.cancelText}}
        </button>
        <button type="button" class="btn js-confirm"
          ng-class="options.confirmClass"
          ng-click="options.confirm(options.input)"
          ng-disabled="processing">
          {{options.confirmText}}
        </button>
      </div>
    </div>
  </div>
</div>
<!-- 警告框 end -->

<script>
$(function(){
  $('body').on('click', 'a[href="#"]', function(e){
    e.preventDefault();
  });
});
</script>

</body>
</html>
