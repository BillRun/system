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



//db.plans.insert({
//	"from": ISODate("2015-12-01T00:00:00Z"),
//	"to": ISODate("2099-12-31T23:59:59Z"),
//	"name": "PP_Netstick_Adi",
//	"type": "customer",
//	"external_id": "1385",
//	"external_code": "ND",
//	"service_provider": "Pelephone",
//	"pp_threshold": {
//		"1": 0,
//		"2": 0,
//		"3": 0,
//		"4": 0,
//		"5": -500,
//		"6": 0,
//		"7": 0,
//		"8": -38654705664,
//		"9": 0,
//		"10": 0
//	},
//});
//
//
//db.plans.update({name: {$in: ["PP_Netstick_Adi", "PP_Netstick_Talk", "RL_TEST", "RLFIX1", "TEST_1", "Z_Michaeli", "Z_Phil_Netstick", "Z_Sharon", "Z_TM_Netstick"]}}, {$set: {"pp_threshold.5": -500}}, {multi: 1});
//
//db.plans.update({name: {$in: ["Z_Philippines"]}}, {$set: {"pp_threshold.5": -472}}, {multi: 1});
//
//db.plans.update({name: {$in: ["Z_First_Class", "Z_Nepal", "Z_Prepost_1", "Z_Talk"]}}, {$set: {"pp_threshold.5": -100}}, {multi: 1});
//
//db.plans.update({name: {$in: ["Z_TMarket"]}}, {$set: {"pp_threshold.5": -47.2}}, {multi: 1});
//
//db.plans.update({name: "Z_Philippines"}, {$set: {"disallowed_rates": ["PREM_CALL_99", "PREM_CALL_98", "PREM_CALL_97", "PREM_CALL_96", "PREM_CALL_95", "PREM_CALL_94", "PREM_CALL_93", "PREM_CALL_92", "PREM_CALL_91", "PREM_CALL_90", "PREM_CALL_9.9", "PREM_CALL_9", "PREM_CALL_89", "PREM_CALL_88", "PREM_CALL_87", "PREM_CALL_86", "PREM_CALL_85", "PREM_CALL_84", "PREM_CALL_83", "PREM_CALL_82", "PREM_CALL_81", "PREM_CALL_80", "PREM_CALL_8", "PREM_CALL_79", "PREM_CALL_78", "PREM_CALL_77", "PREM_CALL_76", "PREM_CALL_75", "PREM_CALL_74", "PREM_CALL_73", "PREM_CALL_72", "PREM_CALL_71", "PREM_CALL_70", "PREM_CALL_7", "PREM_CALL_69", "PREM_CALL_68", "PREM_CALL_67", "PREM_CALL_66", "PREM_CALL_65", "PREM_CALL_64", "PREM_CALL_63", "PREM_CALL_62", "PREM_CALL_61", "PREM_CALL_60", "PREM_CALL_6", "PREM_CALL_59", "PREM_CALL_58", "PREM_CALL_57", "PREM_CALL_56", "PREM_CALL_55", "PREM_CALL_54", "PREM_CALL_53", "PREM_CALL_52", "PREM_CALL_51", "PREM_CALL_50", "PREM_CALL_5.99", "PREM_CALL_5", "PREM_CALL_49.99", "PREM_CALL_49", "PREM_CALL_48", "PREM_CALL_47", "PREM_CALL_46", "PREM_CALL_45", "PREM_CALL_44", "PREM_CALL_43", "PREM_CALL_42", "PREM_CALL_41", "PREM_CALL_40", "PREM_CALL_4.5", "PREM_CALL_4", "PREM_CALL_39", "PREM_CALL_38", "PREM_CALL_37", "PREM_CALL_36", "PREM_CALL_35", "PREM_CALL_34", "PREM_CALL_32", "PREM_CALL_31", "PREM_CALL_30", "PREM_CALL_3.5", "PREM_CALL_3", "PREM_CALL_29", "PREM_CALL_28", "PREM_CALL_27", "PREM_CALL_26", "PREM_CALL_25", "PREM_CALL_24", "PREM_CALL_23", "PREM_CALL_22", "PREM_CALL_21", "PREM_CALL_20", "PREM_CALL_2.5", "PREM_CALL_2", "PREM_CALL_19.9", "PREM_CALL_19", "PREM_CALL_18", "PREM_CALL_17", "PREM_CALL_16", "PREM_CALL_15", "PREM_CALL_14.99", "PREM_CALL_14.9", "PREM_CALL_14", "PREM_CALL_13", "PREM_CALL_12", "PREM_CALL_11.99", "PREM_CALL_11", "PREM_CALL_100", "PREM_CALL_10", "PREM_CALL_1", "PREM_CALL_0.99", "1902_MINUTE_9.9", "1902_MINUTE_9", "1902_MINUTE_8", "1902_MINUTE_7", "1902_MINUTE_6", "1902_MINUTE_50", "1902_MINUTE_5", "1902_MINUTE_45", "1902_MINUTE_40", "1902_MINUTE_4", "1902_MINUTE_35", "1902_MINUTE_30", "1902_MINUTE_3", "1902_MINUTE_25", "1902_MINUTE_20", "1902_MINUTE_2.5", "1902_MINUTE_2", "1902_MINUTE_15", "1902_MINUTE_12.9", "1902_MINUTE_12", "1902_MINUTE_10", "1902_MINUTE_1.5", "1902_MINUTE_1", "1902_MINUTE_0.5", "1902_MIN_5_MAX_80", "1902_MIN_2_MAX_80", "1902_MIN_2_MAX_60", "1902_MIN_1_MAX_90", "1901_MINUTE_9.9", "1901_MINUTE_9", "1901_MINUTE_8", "1901_MINUTE_7", "1901_MINUTE_6", "1901_MINUTE_5", "1901_MINUTE_4", "1901_MINUTE_3", "1901_MINUTE_2.5", "1901_MINUTE_2", "1901_MINUTE_15", "1901_MINUTE_14", "1901_MINUTE_13", "1901_MINUTE_12.9", "1901_MINUTE_12", "1901_MINUTE_11", "1901_MINUTE_10", "1901_MINUTE_1.5", "1901_MINUTE_1", "1901_MINUTE_0.5", "1901_MIN_5_MAX_30", "1901_MIN_2_MAX_30", "1901_MIN_2_MAX_10", "1901_MIN_1_MAX_40", "1900_MINUTE_0.50_MAX5", "1900_MINUTE_0.50", "1900_MINUTE_0.49", "1900_MINUTE_0.40", "1900_MINUTE_0.35", "1900_MINUTE_0.30", "1900_MINUTE_0.25", "1900_MINUTE_0.20", "1900_MINUTE_0.18", "1900_MINUTE_0.15", "1900_MINUTE_0.13", "1900_MINUTE_0.12", "1900_MINUTE_0.11", "1900_MINUTE_0.10", "1900_MINUTE_0.09", "1900_MINUTE_0.08", "1900_MINUTE_0.07", "1900_MINUTE_0.06", "1900_MINUTE_0.05"]}})