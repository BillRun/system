app.directive('applyNotifier', function ($timeout) {
  'use strict';
  return {

    scope: {
      onerr: '=',
      text  :'@' ,
      ngClick:'&'
    }, 
    link: function (scope,elm,attrs) {
       scope.statusClass ="";
            
            elm.bind('click', function(event) {
               
                 scope.clicked = true;

                 $timeout(function() { 
                        if(_.keys(scope.onerr).length)
                          scope.statusClass="glyphicon glyphicon-remove danger";
                        else
                            scope.statusClass="glyphicon glyphicon-ok success";
                    },1000);  
                  $timeout(function() { 
                        scope.statusClass="";
                    },3000);  
            });               
       },
    template: '<span> {{text}} <i class="{{statusClass}}"></i></span>'
  };
})