var app = angular.module('BillrunApp', ['ngRoute', 'JSONedit', 'ui.bootstrap', 'pageslide-directive']);
app.run(function ($rootScope, $interval, $http) {
  var lastDigestRun = Date.now();
  var idleCheck = $interval(function () {
    var now = Date.now();
    if (now - lastDigestRun > 30 * 60 * 1000) {
      window.location = '/admin/logout';
    }
  }, 15 * 60 * 1000);

  $rootScope.$on('$routeChangeStart', function (evt) {
    lastDigestRun = Date.now();
  });

  $http.get(baseUrl + '/admin/getViewINI').then(function (res) {
    $rootScope.fields = res.data;
  });

  $rootScope.$on("$routeChangeStart", function () {
    angular.element('.component.container').remove();
  });
}).config(function ($httpProvider, $routeProvider, $locationProvider) {
  function twoDigit(n) {
    return (n < 10) ? "0" + n : n.toString();
  }

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
      if (!data)
        data = {};
      return angular.isObject(data) && String(data) !== '[object File]' ? param(data) : data;
    }];

  $routeProvider.when('/service_providers', {
    templateUrl: 'views/service_providers.html',
    controller: 'ServiceProvidersController',
    controllerAs: 'vm'
  }).when('/pp_includes', {
    templateUrl: 'views/pp_includes.html',
    controller: 'PrepaidIncludesController',
    controllerAs: 'vm'
  }).when('/:collection/list', {
    templateUrl: 'views/partials/collectionList.html',
    controller: 'ListController',
    controllerAs: 'vm'
  }).when('/:collection/:action/:id?', {
    templateUrl: function (urlattr) {
      return 'views/' + urlattr.collection + '/edit.html';
    }
  });
  $locationProvider.html5Mode({enabled: false, requireBase: false});
});