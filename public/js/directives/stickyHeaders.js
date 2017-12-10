app.directive('stickyHeaders', function ($window) {
	var $win = angular.element($window); // wrap window object as jQuery object
	return {
		restrict: 'AE',
		scope: {
			tbody: '@',
			offset: '@'
		},
		link: function (scope, element, attrs) {
			var topClass = attrs.stickyHeaders, // get CSS class from directive's attribute value
					offsetTop = element.offset().top, // get element's offset top relative to document
					tbody = angular.element(scope.tbody);
			scope.offset += 159;

			$win.on('scroll', function (e) {
				if ($win.scrollTop() >= (tbody.position().top + tbody.outerHeight(true) - 100)) {
					element.removeClass(topClass);
				} else {
					if ($win.scrollTop() + parseInt(scope.offset, 10) >= offsetTop) {
						element.addClass(topClass);
						console.log(element);
					} else {
						element.removeClass(topClass);
					}
				}
			});
		}
	};
});