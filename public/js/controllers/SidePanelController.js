angular
		.module('BillrunApp')
		.controller('SidePanelController', SidePanelController);

function SidePanelController(Database, Utils) {
	'use strict';

	var vm = this;
	vm.Utils = Utils;
	vm.showSidePanel = (localStorage.getItem('showSidePanel') === "true");

	vm.print = function (value) {
		if (_.isArray(value)) {
			return value.join("<br/>");
		} else if (_.isObject(value)) {
			var values = [];
			_.forEach(_.keys(value), function (key) {
				values.push(key + ": " + value[key]);
			});
			return values.join("<br/>");
		} else if (_.isBoolean(value)) {
			if (value === true)
				return "Yes";
			else if (value === false)
				return "No";
		}
		return value;
	};

	vm.togglePanel = function () {
		vm.showSidePanel = !vm.showSidePanel;
		localStorage.setItem('showSidePanel', vm.showSidePanel);
	};

	vm.init = function () {
		Database.getSubscriberDetails().then(function (res) {
			if (res.data.subscriber) {
				if (_.isEmpty(res.data.subscriber))
					return;
				vm.subscriber = res.data.subscriber;
				var format = Utils.getDateFormat().toUpperCase() + " HH:MM:SS";
				if (vm.subscriber.data_slowness_enter && vm.subscriber.data_slowness_enter.sec) {
					vm.subscriber.data_slowness_enter = 
							moment(vm.subscriber.data_slowness_enter.sec * 1000 + vm.subscriber.data_slowness_enter.usec).format(format);
				}
				if (vm.subscriber.data_slowness_exit && vm.subscriber.data_slowness_exit.sec) {
					vm.subscriber.data_slowness_exit = 
							moment(vm.subscriber.data_slowness_exit.sec * 1000 + vm.subscriber.data_slowness_exit.usec).format(format);
				}
			} else {
				vm.showSidePanel = false;
			}
		});
	};
}