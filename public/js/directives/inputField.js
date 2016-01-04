app.directive('inputField', function () {
  'use strict';
  return {
    scope: {
      model: '=',
      type: '=',
      field: '='
    }, link: function (scope) {
    },templateUrl: 'views/partials/inputField.html'
  };
})
.directive('errorMessage', function () {
  'use strict';
  return {

    scope: {
      field: '@',
      messages: '='
    }, 
    link: function (scope) {
        if (_.isObject(scope.messages) && !_.isUndefined(scope.messages[scope.field])) {
            scope.messages = {} ; 
            scope.messages[scope.field] = [] ;
         }
    },
    template: '<span class="errorMessage" ng-repeat="e in messages[field]"> {{e}} </span>'
  };
})
.factory('utils', ['$http', '$timeout',function ($http,$timeout) {
    'use strict';

   function flashMessage(flashObject,scope) {
      $timeout(function() { 
          scope[flashObject] = {} ;
       },2000)
    } ;

    return {
      flashMessage: flashMessage,
     };
  }]);