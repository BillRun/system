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

db.plans.update({"name":{$in:["PP_Class1_Int","PP_Class1","PP_Class2","PP_Class2_Int","PP_Class3_Int","PP_Class3","PP_Dat_Int","PP_Peak_59","PP_ESC","PP_ESC_Int","PP_Mango_Pro","PP_Mango_Card","PP_Flat_Rate","PP_ESC_Minute","PP_IZI_Minute_1","PP_IZI_12_1","PP_Pele_Hul_1","PP_IZI49","PP_IZI75","PP_Flat_Rate_69","PP_Fix_72","PP_Flat_55","PP_Flat_84","PP_UMTS_1","TEST_1","PP_Pele_Hul_2","PP_UMTS_2","PP_Fix_49","PP_Block"]}},{$set:{"data_from_currency":true}},{multi:1});

// Add period to all charging plans
var count = db.plans.find({"type": "charging", "$or": [{"recurring": 0}, {"recurring": {"$exists": 0}}]}).count();
var res = db.plans.update({"type": "charging", "$or": [{"recurring": 0}, {"recurring": {"$exists": 0}}]},
                          {"$set":
                           {
                             "period":
                             {
                               "unit": "months",
                               "duration": 12
                             }
                           }
                          },
                          {multi: true});
if (res.nModified !== count) {
  print("Found " + count + " but only updated " + res.nModified + "!!");
} else {
  print("Modified " + res.nModified + " of " + count + " records!");
}
