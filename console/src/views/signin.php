<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>登录 - Tars</title>
<link rel="icon" type="image/png" href="/assets/images/favicon.png">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="google" value="notranslate">
<base href="/">

<!--[if lt IE 9]>
  <script>location.href="/modern-browser-required";</script>
<![endif]-->

<link rel="stylesheet" type="text/css" href="/assets/lib/bootstrap/dist/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="/assets/css/non-responsive.css">
<link rel="stylesheet" type="text/css" href="/assets/css/default.css">

<style type="text/css">
.panel-signin {
  width: 320px;
  margin: 0 auto;
}
.form-signin .control-label {
  width: 70px;
}
.form-signin .form-control {
  width: 200px;
}
.form-signin .btn {
  margin-left: 70px;
}
.well,
.alert {
  margin: 10px 0;
  padding: 10px 15px;
}
</style>

<script src="/assets/lib/angular/angular.min.js"></script>
<script>
angular.module('signin', [])
.controller('SigninCtrl', function($scope, $http, $timeout){
  var formData = {};
  $scope.formData = formData;
  $scope.showWell = true;
  $scope.signin = function(){
    $scope.showWell = false;
    if (formData.username && formData.password) {
      $scope.signining = true;
      $scope.signinError = $scope.signinSuccess = false;
      $scope.errorMessage = '';

      $http.post('/api/session', angular.copy(formData))
      .success(function(data){
        if (angular.isObject(data)) {
          $scope.signinSuccess = true;
          $timeout(function(){
            location.href = '/';
          });
        } else {
          error('登录错误，请重试');
        }
      }).error(function(data, status){
        if (data && data.data) {
          var code = data.data.code;
          switch (data.data.code) {
            case -1001:
            case -1002:
              error('用户名或密码错误');
              break;
            case 1007:
              error('用户不存在');
          }
        } else {
          error('登录失败 [' + ((data && data.httpStatus) || status) + ']');
        }
      });
    }
    return false;
  };

  function error(message) {
    $scope.signining = false;
    $scope.signinError = true;
    $scope.errorMessage = message;
  }
});
</script>
</head>
<body>

<div class="panel panel-default panel-signin" ng-app="signin" ng-controller="SigninCtrl">
  <div class="panel-heading">
    <h3 class="panel-title">登录到 Tars</h3>
  </div>
  <div class="panel-body">
    <form class="form-horizontal form-signin" name="formSignin" ng-submit="signin()">
      <div class="form-group">
        <label class="control-label" for="username">
          用户名
        </label>
        <input type="text" class="form-control"
          name="username"
          id="username"
          ng-model="formData.username"
          autofocus="true"
          required>
      </div>
      <div class="form-group">
        <label class="control-label" for="password">
          密码
        </label>
        <input type="password" class="form-control"
          name="password"
          ng-model="formData.password"
          id="password"
          required>
      </div>
      <div class="form-group">
        <button type="submit" class="btn btn-primary" ng-disabled="signining">
          {{signining ? '登录中...' : '登 录'}}
        </button>
      </div>
    </form>
    <div class="well well-sm" ng-if="showWell">
      现在可以使用来宾账户 "guest:guest"<br>
      也可以使用管理员账户 "admin:admin"
    </div>
    <div class="alert alert-success" ng-if="signinSuccess">
      登录成功，现在将跳转到<a href="#" class="alert-link">首页</a>
    </div>
    <div class="alert alert-danger" ng-if="signinError">
      {{errorMessage}}
    </div>
  </div>
</div>

</body>
</html>
