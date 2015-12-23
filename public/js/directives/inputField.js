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
});