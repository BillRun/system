app.directive('applyNotifier', function ($timeout) {
  'use strict';
  return {

    scope: {
      state: '=',
    }, 
    link: function (scope) {
       var statusClass;
       if(!scope.state) {
          scope.statusClass ="glyphicon glyphicon-ok" ;
          $timeout(function() { 
              scope.statusClass="glyphicon glyphicon-remove";
          },2000);
       }
    },
    template: '<span> Apply {{state}} <i class="{{statusClass}}"></i></span>'
  };
})