db.rates.find({params:{$ne:[]}}).forEach(function (obj) {
	var _doc = { "from" : ISODate("2012-06-01T00:00:00Z"), "key" : "ILD_PREPAID", "params" : [ ], "rates" : { "call" : { "unit" : "seconds", "rate" : [ { "to" : 2147483647, "price" : 0, "interval" : 1 } ], "category" : "intl", "access" : 4 } }, "to" : ISODate("2113-08-28T17:23:55Z") };
	print("=========================================================");
	print(obj.key);
	_doc.params = obj.params;
	for (i = 0; i < obj.params.prefix.length; i++) {
		prefix = obj.params.prefix[i];
		print(prefix);
		_doc.params.prefix[i] = '#' + prefix;
	}
	_doc.key = obj.key + '_PREPAID';
	db.rates.save(_doc);
	print("=========================================================");
});

var _doc = { "_id" : ObjectId("521e07fcd88db0e73f0001e2"), "from" : ISODate("2012-06-01T00:00:00Z"), "key" : "ILD_PREPAID", "params" : [ ], "rates" : { "call" : { "unit" : "seconds", "rate" : [ { "to" : 2147483647, "price" : 0, "interval" : 1 } ], "category" : "intl", "access" : 4 } }, "to" : ISODate("2113-08-28T17:23:55Z") };
