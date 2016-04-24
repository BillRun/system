angular
		.module('BillrunApp')
		.controller('MenuController', MenuController);

function MenuController($rootScope, $location) {
	'use strict';

	var vm = this;

	vm.goToPage = function (page) {
		$rootScope.active_page = page;
		$location.path('/' + page + "/list");
	};

	vm.init = function () {
	};
}