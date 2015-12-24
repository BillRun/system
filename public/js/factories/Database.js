app.factory('Database', ['$http', function ($http) {
    'use strict';

    function getEntity(params) {
      if (params.type === undefined) params.type = 'update';
      return $http.get(baseUrl + '/admin/getEntity', {params: params});
    }

    function saveEntity(params) {
      var ajaxOpts = {
        id: params.entity._id,
        coll: params.coll,
        type: params.type,
        duplicate_rates: false,
        data: JSON.stringify(params.entity)
      };
      return $http.post(baseUrl + '/admin/save', ajaxOpts);
    }

    function getAvailablePlans() {
      return $http.get(baseUrl + '/admin/getAvailablePlans');
    }

    function getCollectionItems(params) {
      return $http.get(baseUrl + '/admin/getCollectionItems', {params: params});
    }

    function filterCollectionItems(params) {
      return $http.post(baseUrl + '/admin/getCollectionItems', params);
    }

    return {
      getEntity: getEntity,
      saveEntity: saveEntity,
      getAvailablePlans: getAvailablePlans,
      getCollectionItems: getCollectionItems,
      filterCollectionItems: filterCollectionItems
    };
  }]);