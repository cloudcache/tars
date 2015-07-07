/*!
 * Tars directives.js
 * 指令
 * @author steveswwang
 */

angular.module('tars.directives', [])

// 分页控制器
// @see https://github.com/angular-ui/bootstrap/blob/master/src/pagination/pagination.js
.controller('PaginationController', [
        '$scope', '$attrs', '$parse',
function($scope ,  $attrs ,  $parse){
  var self = this,
      ngModelCtrl = { $setViewValue: angular.noop }, // nullModelCtrl
      setNumPages = $attrs.numPages ? $parse($attrs.numPages).assign : angular.noop;

  this.init = function(ngModelCtrl_) {
    ngModelCtrl = ngModelCtrl_;

    ngModelCtrl.$render = function() {
      self.render();
    };

    if ($attrs.itemsPerPage) {
      $scope.$parent.$watch($parse($attrs.itemsPerPage), function(value) {
        self.itemsPerPage = parseInt(value, 10);
        $scope.totalPages = self.calculateTotalPages();
      });
    } else {
      self.itemsPerPage = 20;
    }
  };

  this.calculateTotalPages = function() {
    var totalPages = this.itemsPerPage < 1 ? 1 : Math.ceil($scope.totalItems / this.itemsPerPage);
    return Math.max(totalPages || 0, 1);
  };

  this.render = function() {
    $scope.page = parseInt(ngModelCtrl.$viewValue, 10) || 1;
  };

  $scope.selectPage = function(page) {
    if ( $scope.page !== page && page > 0 && page <= $scope.totalPages) {
      ngModelCtrl.$setViewValue(page);
      ngModelCtrl.$render();
    }
  };

  $scope.noPrevious = function() {
    return $scope.page === 1;
  };
  $scope.noNext = function() {
    return $scope.page === $scope.totalPages;
  };

  $scope.$watch('totalItems', function() {
    $scope.totalPages = self.calculateTotalPages();
  });

  $scope.$watch('totalPages', function(value) {
    setNumPages($scope.$parent, value); // Readonly variable

    if ( $scope.page > value ) {
      $scope.selectPage(value);
    } else {
      ngModelCtrl.$render();
    }
  });
}])

// 分页指令 <tars-pagination>
.directive('tarsPagination', function(){
  return {
    restrict: 'EA',
    scope: {
      totalItems: '='
    },
    require: ['tarsPagination', '?ngModel'],
    controller: 'PaginationController',
    templateUrl: '/templates/pagination.html',
    replace: true,
    link: function(scope, element, attrs, ctrls){
      var paginationCtrl = ctrls[0],
          ngModelCtrl = ctrls[1];

      if (!ngModelCtrl) {
         return; // do nothing if no ng-model
      }

      paginationCtrl.init(ngModelCtrl);
    }
  };
})

.directive('tooltip', function(){
  return {
    restrict: 'A',
    link: function(scope, element){
      $(element).tooltip({
        animation: false
      });
    }
  };
})

.directive('popover', function(){
  return {
    restrict: 'A',
    link: function(scope, element){
      $(element).popover({
        animation: false
      });
    }
  };
});
