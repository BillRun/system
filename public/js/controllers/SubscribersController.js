app.controller('SubscribersController', ['$scope', '$window', '$routeParams', 'Database', '$controller', 'utils',
  function ($scope, $window, $routeParams, Database, $controller, utils) {
    'use strict';

    $controller('EditController', {$scope: $scope});

    $scope.flash =  { 
                      message :"" ,
                      cls:""
                    } ; 

    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/' + $routeParams.collection;
    };
    $scope.save = function (redirect) {
      var params = {
        entity: $scope.entity,
        coll: 'subscribers',
        type: $scope.action
      };
      
      $scope.err ={};
      Database.saveEntity(params).then(function (res) {
        console.log(res)  ;
            if(redirect) { 
              $window.location = baseUrl + '/admin/' + $routeParams.collection;
            }
      }, function (err) {
        $scope.err=err;
        console.log(err);
      });
    };

    $scope.addIMSI = function () {
      

      if($scope.entity.imsi.length >=2) { 
       
        $scope.flash.message ="Maximum 2 imsi for subscriber" ;
        $scope.flash.cls ="alert alert-danger" ;
        utils.flashMessage('flash',$scope);

        return false ;
      }

      var idx = _.findIndex( $scope.entity.imsi , function(i) {
          return ( _.trim(i) == '' || !_.trim(i) );
      });

      if(idx>0 ) {
        return ;
      } else { 
        $scope.entity.imsi.push("");
      }

    };

    $scope.deleteIMSI = function (imsiIndex) {
      if (imsiIndex === undefined)
        return;
      $scope.entity.imsi.splice(imsiIndex, 1);
    };

    $scope.init = function () {
      $scope.action = $routeParams.action;
      $scope.entity = {imsi: []};
      if ($scope.action.toLowerCase() !== "new") {
        var params = {
          coll: $routeParams.collection,
          id: $routeParams.id
        };
        Database.getEntity(params).then(function (res) {
          $scope.entity = res.data.entity;
          if ($scope.entity.imsi && _.isString($scope.entity.imsi)) {
            $scope.entity.imsi = [$scope.entity.imsi];
          }
          $scope.authorized_write = res.data.authorized_write;
        }, function (err) {
          alert("Connection error!");
        });
      }
      Database.getAvailableServiceProviders().then(function (res) {
        $scope.availableServiceProviders = res.data;
      });
      Database.getAvailablePlans().then(function (res) {
        $scope.availablePlans = res.data;
      });
      $scope.availableLanguages = ["Hebrew", "English", "Arabic", "Russian", "Thai"];
      $scope.availableChargingTypes = ["prepaid", "postpaid"];
    };
  }]);