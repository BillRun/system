app.directive('prettyError', function ($timeout) {
	'use strict';
	return {
		scope: {
			errors: '=',
		},
		link: function (scope, elm, attrs) {
			scope.$watch('errors', function(n) {
				if (typeof(n) !== 'undefined' &&
						((typeof(n.summaryReport) !== 'undefined' && n.summaryReport.length) ||n.message)) {
					$("body").animate({scrollTop: 0}, "fast")
				}
			}, true);
		},
		template: '<ul class="list-group" ng-show="errors.summaryReport.length ||errors.message">'
				+ '<br/><br/><br/>'
				+ '<li class="list-group-item h4 list-group-item-danger">Please solve the problems below</li>'
				+ '<li class="errorMessage list-group-item" ng-repeat="m in errors.summaryReport track by $index">{{m}}</li>'
				+ '<li class="errorMessage list-group-item" ng-show="errors.message">{{errors.message}}</li>'
				+ '</ul>'

	};
})