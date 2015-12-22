app.directive('inputField', function () {
  'use strict';
  return {
    scope: {
      model: '=',
      type: '='
    }, templateUrl: 'views/partials/inputField.html'
  };
});