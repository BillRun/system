app.factory('Utils', ['$rootScope', function ($rootScope) {
    'use strict';

    function getDisplayValue(str, coll) {
      if ($rootScope.fields === undefined)
        return str.replace(/_/g, ' ');
      if ($rootScope.fields[coll] && $rootScope.fields[coll][str]
        && $rootScope.fields[coll][str]['display_value'])
        return $rootScope.fields[coll][str]['display_value'];
      if ($rootScope.fields[str] && $rootScope.fields[str]['display_value'])
        return $rootScope.fields[str]['display_value'];
      return _.capitalize(str.replace(/_/g, ' '));
     
      var d1 = _.result($rootScope.fields, coll + '.' + str + '.display_value');
      var d2 = _.result($rootScope.fields, str + '.display_value');

      var returnStr  =  (d1 || d2) || str ;
     
      return _.capitalize(returnStr.replace(/_/g, ' '));
    }

    function display(field, coll) {
      if ($rootScope.fields === undefined)
        return false;
      if ($rootScope.fields[coll] && $rootScope.fields[coll][field] && $rootScope.fields[coll][field]['display'])
        return parseInt($rootScope.fields[coll][field]['display'], 10);
      if ($rootScope.fields[field] && $rootScope.fields[field]['display'])
        return parseInt($rootScope.fields[field]['display'], 10);
      return true;
    }

    function disabled(field, coll) {
      if ($rootScope.fields === undefined)
        return true;
      if ($rootScope.fields[coll] && $rootScope.fields[coll][field] && $rootScope.fields[coll][field]['disabled'])
        return parseInt($rootScope.fields[coll][field]['disabled'], 10);
      if ($rootScope.fields[field] && $rootScope.fields[field]['disabled'])
        return parseInt($rootScope.fields[field]['disabled'], 10);
      return false;
    }

    function getDateFormat(field, coll) {
      if (field === undefined) {
        if (coll === undefined) {
          return $rootScope.fields['date_format'];
        }
        return $rootScope.fields[coll]['date_format'];
      }
      if (coll === undefined) {
        return $rootScope.fields[field]['date_format'];
      }
      return $rootScope.fields[coll][field]['date_format'];
    }

   

    return {
      getDisplayValue: getDisplayValue,
      display: display,
      disabled: disabled,
      getDateFormat: getDateFormat

    };
  }]);