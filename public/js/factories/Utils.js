app.factory('Utils', ['$rootScope', function ($rootScope) {
    'use strict';

    function getDisplayValue(str, coll) {
      console.log();
      var str = _.result($rootScope.fields, coll + '.' + str + '.display_value');
      var globalStr = _.result($rootScope.fields, str + '.display_value', str);
      var returnStr  = globalStr|| str ; 
      return _.capitalize(returnStr.replace(/_/g, ' '));
    }

    function display(field, coll) {
      var str = _.result($rootScope.fields, coll + '.' + field + 'display');
      if(str !== undefined)  {
          return ( parseInt(str,10) == 1 ?  true :  false ); 
      } 

      var globalStr = _.result($rootScope.fields, field + '.display');
      if(globalStr !== undefined)  {
          return ( parseInt(globalStr,10) == 1 ?  true : false ); 
      }
      return true;
    }

    function disabled(field, coll, action) {
      if (action === 'new') return false;
      var str = _.result($rootScope.fields, coll + '.' + field + '.disabled');
      if(str !== undefined)  {
          return (parseInt(str,10) === 1 ?  true : false );
      }
      var globalStr = _.result($rootScope.fields, field + '.disabled');
      if(globalStr !== undefined)  {
          return ( parseInt(globalStr,10) === 1 ?  true :  false );
      }
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