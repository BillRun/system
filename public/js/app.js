var app = angular.module('BillrunApp', ['ngRoute', 'JSONedit', 'ui.bootstrap']);
app.config(function ($httpProvider, $routeProvider, $locationProvider) {
  $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded;charset=utf-8';
  /**
   * The workhorse; converts an object to x-www-form-urlencoded serialization.
   * @param {Object} obj
   * @return {String}
   */
  var param = function (obj) {
    var query = '', name, value, fullSubName, subName, subValue, innerObj, i;

    for (name in obj) {
      value = obj[name];

      if (value instanceof Array) {
        for (i = 0; i < value.length; ++i) {
          subValue = value[i];
          fullSubName = name + '[' + i + ']';
          innerObj = {};
          innerObj[fullSubName] = subValue;
          query += param(innerObj) + '&';
        }
      } else if (value instanceof Object) {
        for (subName in value) {
          subValue = value[subName];

          // TODO : Convert this in the directive!
          if (subValue && subValue.toDateString)
            subValue = twoDigit(subValue.getDate()) + '-' + twoDigit(subValue.getMonth() + 1) + '-' + subValue.getFullYear();
          // TODO

          fullSubName = name + '[' + subName + ']';
          innerObj = {};
          innerObj[fullSubName] = subValue;
          query += param(innerObj) + '&';
        }
      } else if (value !== undefined && value !== null) {
        query += encodeURIComponent(name) + '=' + encodeURIComponent(value) + '&';
      }
    }

    return query.length ? query.substr(0, query.length - 1) : query;
  };

  // Override $http service's default transformRequest
  $httpProvider.defaults.transformRequest = [function (data) {
    if (!data) data = {};
    return angular.isObject(data) && String(data) !== '[object File]' ? param(data) : data;
  }];

  $routeProvider.when('/:collection/:action/:id', {
	  templateUrl: function (urlattr) {
		  return 'views/' + urlattr.collection + '/edit.html';
	  }
  }).when('/:collection/:action', {
    templateUrl: function (urlattr) {
      return 'views/' + urlattr.collection + '/edit.html';
    }
  }).when('/:collection/list', {
    templateUrl: 'views/partials/collectionList.html',
    controller: 'CollectionsController'
  });
  $locationProvider.html5Mode({enabled: false, requireBase: false});
});