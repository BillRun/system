app.factory('Utils', ['$rootScope', function ($rootScope) {
    'use strict';

    function getDisplayValue(str, coll) {
      if (!_.isUndefined($rootScope.fields[coll]['display_value'][str]))
        return $rootScope.fields[coll]['display_value'][str];
      if (!_.isUndefined($rootScope.fields['display_value'][str]))
        return $rootScope.fields['display_value'][str];
      return _.capitalize(str.replace('_', ' '));
    }

    function display(field, coll) {
      if (!_.isUndefined($rootScope.fields[coll]['display'][field]))
        return parseInt($rootScope.fields[coll]['display'][field], 10);
      if (!_.isUndefined($rootScope.fields['display'][field]))
        return parseInt($rootScope.fields['display'][field], 10);
      return true;
    }

    function disabled(field, coll) {
      if (!_.isUndefined($rootScope.fields[coll]['disabled'][field]))
        return parseInt($rootScope.fields[coll]['disabled'][field], 10);
      if (!_.isUndefined($rootScope.fields['disabled'][field]))
        return parseInt($rootScope.fields['disabled'][field], 10);
      return false;
    }

    return {
      getDisplayValue: getDisplayValue,
      display: display,
      disabled: disabled
    };
  }]);