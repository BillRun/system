app.factory('Database', ['$http', function ($http) {
    function getEntity(coll, id) {
      var params = {
        id: id,
        coll: coll,
        type: 'update'
      };
      return $http.get('/admin/get', {params: params});
    }

    function saveEntity(entity, coll) {
      var ajaxOpts = {
        id: entity._id,
        coll: coll,
        type: 'update',
        duplicate_rates: false,
        data: JSON.stringify(entity)
      };
      return $http.post(baseUrl + '/admin/save', ajaxOpts);
    };

    return {
      getEntity: getEntity,
      saveEntity: saveEntity
    };
  }]);