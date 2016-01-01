app.factory('Utils', ['$rootScope', function ($rootScope) {
    'use strict';

    function getDisplayValue(str, coll) {
      if ($rootScope.fields[coll] && $rootScope.fields[coll][str]) return $rootScope.fields[coll][str];
      if ($rootScope.fields[str]) return $rootScope.fields[str];
      return str;
    }

    return {
      getDisplayValue: getDisplayValue
    };
  }]);