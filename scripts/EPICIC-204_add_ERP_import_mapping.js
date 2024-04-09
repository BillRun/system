var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

var erp_mapping = {
			"label": "Missing ERP Mappings",
			"map": [
				{
					"field": "price_from",
					"value": "0"
				},
				{
					"field": "params.product",
					"value": "__csvindex__2"
				},
				{
					"field": "mtn_ind",
					"value": "__csvindex__5"
				},
				{
					"field": "usage_type_value",
					"value": "erp_mapping"
				},
				{
					"field": "usage_type_unit",
					"value": "counter"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__3"
				},
				{
					"field": "params.user_summarisation",
					"value": "__csvindex__10"
				},
				{
					"field": "gl_account_description",
					"value": "__csvindex__9"
				},
				{
					"field": "price_to",
					"value": "UNLIMITED"
				},
				{
					"field": "params.cash_flow",
					"value": "__csvindex__7"
				},
				{
					"field": "gl_account",
					"value": "__csvindex__6"
				},
				{
					"field": "params.component",
					"value": "__csvindex__11"
				},
				{
					"field": "params.scenario",
					"value": "__csvindex__4"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "object_id",
					"value": "__csvindex__8"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "0"
				},
				{
					"field": "prod_serv",
					"value": "__csvindex__1"
				},
				{
					"field": "key",
					"value": "__csvindex__0"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		}
	
	lastConfig["import"]["mapping"].push(erp_mapping);

db.config.insert(lastConfig);