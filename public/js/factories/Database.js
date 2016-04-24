app.factory('Database', ['$http', function ($http) {
		'use strict';

		function getEntity(params) {
			if (params.type === undefined)
				params.type = 'update';
			return $http.get(baseUrl + '/admin/getEntity', {params: params});
		}

		function saveEntity(params) {
			var ajaxOpts = {
				id: params.entity._id,
				coll: params.coll,
				type: params.type,
				duplicate_rates: params.duplicate_rates,
				batch: (params.batch !== undefined ? params.batch : false),
				range: (params.range !== undefined ? params.range : false),
				data: JSON.stringify(params.entity)
			};
			return $http.post(baseUrl + '/admin/save', ajaxOpts);
		}

		function removeEntity(params) {
			if (!_.isArray(params.ids) && _.isString(params.ids))
				params.ids = [params.ids];
			params.type = 'remove';
			return $http.post(baseUrl + '/admin/remove', params);
		}

		function getAvailableServiceProviders(params) {
			if (params === undefined)
				params = {};
			return $http.get(baseUrl + '/admin/getAvailableServiceProviders', {params: params});
		}

		function getAvailablePlans(type, full_objects) {
			if (full_objects === undefined)
				full_objects = false;
			if (type === undefined)
				type = 'customer';
			return $http.get(baseUrl + '/admin/getAvailablePlans', {params: {type: type, full_objects: full_objects}});
		}

		function getAvailableInterconnect() {
			return $http.get(baseUrl + '/admin/getAvailableInterconnect');
		}

		function getAvailableRates() {
			return $http.get(baseUrl + '/admin/getAvailableRates');
		}

		function getAvailablePPIncludes(params) {
			if (params === undefined)
				params = {};
			return $http.get(baseUrl + '/admin/getAvailablePPIncludes', {params: params});
		}

		function getCollectionItems(params) {
			return $http.get(baseUrl + '/admin/getCollectionItems', {params: params});
		}

		function filterCollectionItems(params) {
			return $http.get(baseUrl + '/admin/getCollectionItems', {params: params});
		}

		function getSubscriberDetails() {
			return $http.get(baseUrl + '/admin/getSubscriberDetails');
		}

		function getBandwidthCapDetails() {
			return $http.get(baseUrl + '/admin/getBandwidthCapDetails');
		}

		function saveBandwidthCap(params) {
			return $http.post(baseUrl + '/admin/saveBandwidthCap', params);
		}
		function removeBandwidthCap(params) {
			return $http.post(baseUrl + '/admin/removeBandwidthCap', params);
		}
		function getRatesWithSamePrefix(params) {
			if (params === undefined)
				params = {};
			return $http.get(baseUrl + '/admin/getRatesWithSamePrefix', {params: params});
		}

		return {
			getEntity: getEntity,
			saveEntity: saveEntity,
			removeEntity: removeEntity,
			getAvailablePlans: getAvailablePlans,
			getAvailableInterconnect: getAvailableInterconnect,
			getAvailableRates: getAvailableRates,
			getAvailableServiceProviders: getAvailableServiceProviders,
			getCollectionItems: getCollectionItems,
			filterCollectionItems: filterCollectionItems,
			getAvailablePPIncludes: getAvailablePPIncludes,
			getSubscriberDetails: getSubscriberDetails,
			getBandwidthCapDetails: getBandwidthCapDetails,
			saveBandwidthCap: saveBandwidthCap,
			removeBandwidthCap: removeBandwidthCap,
			getRatesWithSamePrefix: getRatesWithSamePrefix
		};
	}]);