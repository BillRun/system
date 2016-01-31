// migrate customer plans (COS)
db.tmp_COS_SERVICEPROVIDERS.find().forEach(
	function(obj20) {
		db.plans.insert({
			name:obj20.COS_NAME,
			from:ISODate('2015-12-01'),
			to:ISODate('2099-12-31 23:59:59'),
			type:'customer',
			external_id: obj20.COS_ID,
			external_code: obj20.COS_CODE,
			service_provider:obj20.SP_NAME
		});
	}
);

