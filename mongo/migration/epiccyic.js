function getIn(arr, path) {
	path = path.split('.');
	for (var i = 0, len = path.length; i < len - 1; i++) {
		arr = arr[path[i]];
		if (typeof arr === 'undefined') {
			return arr;
		}
	}
	return arr[path[len - 1]];
}

function setIn(arr, path, value) {
	path = path.split('.');
	for (var i = 0, len = path.length; i < len - 1; i++) {
		if (typeof arr[path[i]] === 'undefined') {
			arr[path[i]] = {};
		}
		arr = arr[path[i]];
	}
	arr[path[len - 1]] = value;
}

function addToConfig(config, lastConf) {
	for (var path in config) {
		var values = config[path];
		if (typeof getIn(lastConf, path) === 'undefined') {
			if (Array.isArray(values)) {
				setIn(lastConf, path, []);
			}
		}
		var fields = getIn(lastConf, path);
		if (Array.isArray(values)) {
			var new_values = values.filter(x => !fields.includes(x));
			setIn(lastConf, path, fields.concat(new_values));

		} else {
			setIn(lastConf, path, values);
		}
	}
	return lastConf;
}

function addFieldToConfig(lastConf, fieldConf, entityName) {
    if (typeof lastConf[entityName] === 'undefined') {
            lastConf[entityName] = {'fields': []};
    }
    var fields = lastConf[entityName]['fields'];
    var found = false;
    for (var field_key in fields) {
        if (fields[field_key].field_name === fieldConf.field_name) {
						fields[field_key] = fieldConf;
            found = true;
        }
    }
    if (!found) {
        fields.push(fieldConf);
    }
    lastConf[entityName]['fields'] = fields;

    return lastConf;
}

// Perform specific migrations only once
// Important note: runOnce is guaranteed to run some migration code once per task code only if the whole migration script completes without errors.
function runOnce(lastConfig, taskCode, callback) {
	if (typeof lastConfig.past_migration_tasks === 'undefined') {
		lastConfig['past_migration_tasks'] = [];
	}
	taskCode = taskCode.toUpperCase();
	if (!lastConfig.past_migration_tasks.includes(taskCode)) {
		if (new RegExp(/.*-\d+$/).test(taskCode)) {
			callback();
			lastConfig.past_migration_tasks.push(taskCode);
		} else {
			print('Illegal task code ' + taskCode);
		}
	}
	return lastConfig;
}

var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

lastConfig = runOnce(lastConfig, 'EPICIC-119', function () {
    //import mappers
	if (typeof lastConfig.import === 'undefined') {
		lastConfig['import'] = {};
	}
	lastConfig["import"]["mapping"] = [
		{
			"label": "One file loader - Rates create I calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "incoming_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create O calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "outgoing_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create TI calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "transit_incoming_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create TO calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "transit_outgoing_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create I SMS",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "incoming_sms"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "counter"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__14"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create O SMS",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "outgoing_sms"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "counter"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__14"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates update",
			"map": [
				{
					"field": "price_from",
					"value": "0"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "effective_date",
					"value": "__csvindex__10"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "price_to",
					"value": "UNLIMITED"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				}
			],
			"updater": {
				"field": "key",
				"value": "__csvindex__2"
			},
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Tier create",
			"map": [
				{
					"field": "params.type",
					"value": "tier_cb"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "parameter_tier_cb"
				},
				{
					"field": "from",
					"value": "__csvindex__6"
				},
				{
					"field": "usage_type_unit",
					"value": "counter"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.cash_flow",
					"value": "__csvindex__5"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "params.prefix",
					"value": "__csvindex__9"
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
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": [
				{
					"field": "params.prefix",
					"value": "append"
				}
			]
		},
		{
			"label": "One file loader - Tier update",
			"map": [
				{
					"field": "effective_date",
					"value": "__csvindex__6"
				},
				{
					"field": "params.prefix",
					"value": "__csvindex__9"
				},
				{
					"field": "price_from",
					"value": "0"
				},
				{
					"field": "price_to",
					"value": "UNLIMITED"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.cash_flow",
					"value": "__csvindex__5"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				}
			],
			"updater": {
				"field": "key",
				"value": "__csvindex__2"
			},
			"linker": [],
			"multiFieldAction": [
				{
					"field": "params.prefix",
					"value": "append"
				}
			]
		},
		{
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

	];
});

//EPICIC-127 - change the import file header to be the same order as in the exported file
lastConfig = runOnce(lastConfig, 'EPICIC-127', function () {
   for (var i = 0; i < lastConfig.export_generators.length; i++) {
            if (lastConfig.import.mapping[i].label === "Missing ERP Mappings") {
                lastConfig["import"]['mapping'][i]["map"] = [
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
			]
            }
    }
});

lastConfig = runOnce(lastConfig, 'EPICIC-2', function () {
	//Add plugin
	if (!lastConfig['plugins'].includes("epicCyIcPlugin")) {
		lastConfig.plugins.push("epicCyIcPlugin");
	}
	
//Activity types
	lastConfig["usage_types"] = [
		{
			"usage_type": "incoming_call",
			"label": "incoming_call",
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "outgoing_call",
			"label": "outgoing_call",
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_product",
			"label": "parameter_product"
		},
		{
			"usage_type": "parameter_operator",
			"label": "parameter_operator",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "parameter_scenario",
			"label": "parameter_scenario",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_component",
			"label": "parameter_component"
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_tier_cb",
			"label": "parameter_tier_cb"
		},
		{
			"usage_type": "parameter_tier_aba",
			"label": "parameter_tier_aba",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_tier_pb",
			"label": "parameter_tier_pb"
		},
		{
			"usage_type": "parameter_tier_pb_anaa",
			"label": "parameter_tier_pb_anaa",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "transit_incoming_call",
			"label": "transit_incoming_call"
		},
		{
			"usage_type": "transit_outgoing_call",
			"label": "transit_outgoing_call",
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "incoming_sms",
			"label": "incoming_sms",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "outgoing_sms",
			"label": "outgoing_sms",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "erp_mapping",
			"label": "erp_mapping",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type" : "parameter_naa",
			"label" : "parameter_naa",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		}
	],
//Input processor
	lastConfig["file_types"][0] =
			{
				"file_type": "ICT",
				"parser": {
					"type": "fixed",
					"line_types": {
						"H": "/^none$/",
						"D": "//",
						"T": "/^none$/"
					},
					"separator": "",
					"structure": [
						{
							"name": "RECORD_SEQUENCE_NUMBER",
							"checked": true,
							"width": "40"
						},
						{
							"name": "RECORD_TYPE",
							"checked": true,
							"width": "2"
						},
						{
							"name": "INCOMING_NODE",
							"checked": true,
							"width": "20"
						},
						{
							"name": "OUTGOING_NODE",
							"checked": true,
							"width": "20"
						},
						{
							"name": "INCOMING_PATH",
							"checked": true,
							"width": "20"
						},
						{
							"name": "OUTGOING_PATH",
							"checked": true,
							"width": "20"
						},
						{
							"name": "ANUM",
							"checked": true,
							"width": "50"
						},
						{
							"name": "BNUM",
							"checked": true,
							"width": "50"
						},
						{
							"name": "EVENT_START_DATE",
							"checked": true,
							"width": "8"
						},
						{
							"name": "EVENT_START_TIME",
							"checked": true,
							"width": "6"
						},
						{
							"name": "EVENT_DURATION",
							"checked": true,
							"width": "10"
						},
						{
							"name": "DATA_VOLUME",
							"checked": true,
							"width": "25"
						},
						{
							"name": "DATA_UNIT",
							"checked": true,
							"width": "8"
						},
						{
							"name": "DATA_VOLUME_2",
							"checked": true,
							"width": "25"
						},
						{
							"name": "DATA_UNIT_2",
							"checked": true,
							"width": "8"
						},
						{
							"name": "DATA_VOLUME_3",
							"checked": true,
							"width": "25"
						},
						{
							"name": "DATA_UNIT_3",
							"checked": true,
							"width": "8"
						},
						{
							"name": "USER_SUMMARISATION",
							"checked": true,
							"width": "20"
						},
						{
							"name": "USER_DATA",
							"checked": true,
							"width": "20"
						},
						{
							"name": "USER_DATA2",
							"checked": true,
							"width": "80"
						},
						{
							"name": "USER_DATA3",
							"checked": true,
							"width": "80"
						},
						{
							"name": "REPAIR_INDICATOR",
							"checked": true,
							"width": "1"
						},
						{
							"name": "REASON_FOR_CLEARDOWN",
							"checked": true,
							"width": "4"
						}
					],
					"csv_has_header": false,
					"csv_has_footer": false,
					"custom_keys": [
						"RECORD_SEQUENCE_NUMBER",
						"RECORD_TYPE",
						"INCOMING_NODE",
						"OUTGOING_NODE",
						"INCOMING_PATH",
						"OUTGOING_PATH",
						"ANUM",
						"BNUM",
						"EVENT_START_DATE",
						"EVENT_START_TIME",
						"EVENT_DURATION",
						"DATA_VOLUME",
						"DATA_UNIT",
						"DATA_VOLUME_2",
						"DATA_UNIT_2",
						"DATA_VOLUME_3",
						"DATA_UNIT_3",
						"USER_SUMMARISATION",
						"USER_DATA",
						"USER_DATA2",
						"USER_DATA3",
						"REPAIR_INDICATOR",
						"REASON_FOR_CLEARDOWN"
					]
				},
				"processor": {
					"type": "Usage",
					"date_field": "EVENT_START_DATE",
					"usaget_mapping": [
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_operator",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_product",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_scenario",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_component",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_tier_cb",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_tier_aba",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_tier_pb",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_tier_pb_anaa",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "OUTGOING_PATH",
							"conditions": [
								{
									"src_field": "INCOMING_PATH",
									"pattern": "^(?!\\s*$).+",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "OUTGOING_PATH",
									"pattern": "^(?!\\s*$).+",
									"op": "$regex",
									"op_label": "Regex"
								}
							],
							"pattern": "^(?!\\s*$).+",
							"usaget": "transit_incoming_call",
							"unit": "seconds",
							"volume_type": "field",
							"volume_src": [
								"EVENT_DURATION"
							]
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "transit_outgoing_call",
							"unit": "seconds",
							"volume_type": "field",
							"volume_src": [
								"EVENT_DURATION"
							]
						},
						{
							"src_field": "OUTGOING_PATH",
							"conditions": [
								{
									"src_field": "BNUM",
									"pattern": "^S",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "INCOMING_PATH",
									"pattern": "^$",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "OUTGOING_PATH",
									"pattern": "^(?!\\s*$).+",
									"op": "$regex",
									"op_label": "Regex"
								}
							],
							"pattern": "^(?!\\s*$).+",
							"usaget": "outgoing_sms",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "OUTGOING_PATH",
							"conditions": [
								{
									"src_field": "BNUM",
									"pattern": "^S",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "INCOMING_PATH",
									"pattern": "^(?!\\s*$).+",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "OUTGOING_PATH",
									"pattern": "^$",
									"op": "$regex",
									"op_label": "Regex"
								}
							],
							"pattern": "^$",
							"usaget": "incoming_sms",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						},
						{
							"src_field": "OUTGOING_PATH",
							"conditions": [
								{
									"src_field": "BNUM",
									"pattern": "^[0-9]",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "INCOMING_PATH",
									"pattern": "^(?!\\s*$).+",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "OUTGOING_PATH",
									"pattern": "^$",
									"op": "$regex",
									"op_label": "Regex"
								}
							],
							"pattern": "^$",
							"usaget": "incoming_call",
							"unit": "seconds",
							"volume_type": "field",
							"volume_src": [
								"EVENT_DURATION"
							]
						},
						{
							"src_field": "OUTGOING_PATH",
							"conditions": [
								{
									"src_field": "BNUM",
									"pattern": "^[0-9]",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "INCOMING_PATH",
									"pattern": "^$",
									"op": "$regex",
									"op_label": "Regex"
								},
								{
									"src_field": "OUTGOING_PATH",
									"pattern": "^(?!\\s*$).+",
									"op": "$regex",
									"op_label": "Regex"
								}
							],
							"pattern": "^(?!\\s*$).+",
							"usaget": "outgoing_call",
							"unit": "seconds",
							"volume_type": "field",
							"volume_src": [
								"EVENT_DURATION"
							]
						},
						{
							"src_field": "DATA_UNIT",
							"conditions": [
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$eq",
									"op_label": "Equals"
								},
								{
									"src_field": "DATA_UNIT",
									"pattern": "a",
									"op": "$ne",
									"op_label": "Not Equals"
								}
							],
							"pattern": "a",
							"usaget": "parameter_naa",
							"unit": "counter",
							"volume_type": "value",
							"volume_src": 1
						}
					],
					"time_field": "EVENT_START_TIME",
					"date_format": "Ymd",
					"time_format": "His",
					"calculated_fields": [
						{
							"target_field": "call_direction",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "incoming_operator",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "outgoing_operator",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "operator",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "anaa",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "bnaa",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "product_title",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "product",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "product_group",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "event_direction",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "scenario",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "component",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "settlement_operator",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "virtual_operator",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "cash_flow",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "incoming_poin",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "outgoing_poin",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "poin",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "tier",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "tier_derivation",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "operator_title",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "anaa_group",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						},
						{
							"target_field": "anaa_title",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						}
					],
					"orphan_files_time": "6 hours"
				},
				"customer_identification_fields": {
					"incoming_sms": [
						{
							"target_key": "operator_path",
							"src_key": "INCOMING_PATH",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"transit_outgoing_call": [
						{
							"target_key": "operator_path",
							"src_key": "OUTGOING_PATH",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_tier_cb": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"outgoing_sms": [
						{
							"target_key": "operator_path",
							"src_key": "OUTGOING_PATH",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_scenario": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_component": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"transit_incoming_call": [
						{
							"target_key": "operator_path",
							"src_key": "INCOMING_PATH",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"outgoing_call": [
						{
							"target_key": "operator_path",
							"src_key": "OUTGOING_PATH",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_tier_pb_anaa": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"incoming_call": [
						{
							"target_key": "operator_path",
							"src_key": "INCOMING_PATH",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_naa": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_tier_aba": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_product": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_tier_pb": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					],
					"parameter_operator": [
						{
							"target_key": "sid",
							"src_key": "REASON_FOR_CLEARDOWN",
							"conditions": [
								{
									"field": "usaget",
									"regex": "/.*/"
								}
							],
							"clear_regex": "//"
						}
					]
				},
				"rate_calculators": {
					"retail": {
						"incoming_sms": [
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							]
						],
						"transit_outgoing_call": [
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							]
						],
						"parameter_tier_cb": [
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "tier"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "operator",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.cash_flow",
									"line_key": "cash_flow"
								},
								{
									"type": "longestPrefix",
									"rate_key": "params.prefix",
									"line_key": "BNUM"
								}
							]
						],
						"outgoing_sms": [
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							]
						],
						"parameter_scenario": [
							[
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/T(I|O)/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "T"
											},
											"on_false": {
												"key": "call_direction",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "anaa"
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "bnaa"
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "INCOMING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "incoming_operator",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "OUTGOING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "outgoing_operator",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "INCOMING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "OUTGOING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/T(I|O)/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "T"
											},
											"on_false": {
												"key": "call_direction",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "condition_result",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "bnaa"
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "INCOMING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "incoming_operator",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "OUTGOING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "outgoing_operator",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "INCOMING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "OUTGOING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/T(I|O)/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "T"
											},
											"on_false": {
												"key": "call_direction",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "BNUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "INCOMING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "incoming_operator",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "OUTGOING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "outgoing_operator",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "INCOMING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "OUTGOING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/T(I|O)/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "T"
											},
											"on_false": {
												"key": "call_direction",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "condition_result",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "INCOMING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_product",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "OUTGOING_PATH"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": {
												"key": "product",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							]
						],
						"parameter_component": [
							[
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/I$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "anaa",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/O$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "bnaa",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/I$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "incoming_operator",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/O$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "outgoing_operator",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.scenario",
									"line_key": "scenario"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/I$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "anaa",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/O$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "bnaa",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.scenario",
									"line_key": "scenario"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.scenario",
									"line_key": "scenario"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/I$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "incoming_operator",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/O$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "outgoing_operator",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											}
										}
									}
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.scenario",
									"line_key": "scenario"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.incoming_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.outgoing_operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								}
							]
						],
						"transit_incoming_call": [
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							]
						],
						"outgoing_call": [
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							]
						],
						"parameter_tier_pb_anaa": [
							[
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "anaa"
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "bnaa"
								},
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.poin",
									"line_key": "poin"
								}
							]
						],
						"incoming_call": [
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "ANUM"
											}
										],
										"operator": "$exists",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "hard_coded",
												"regex": "",
												"value": "*"
											},
											"on_false": []
										}
									}
								},
								{
									"type": "match",
									"rate_key": "params.product",
									"line_key": "product"
								},
								{
									"type": "match",
									"rate_key": "params.component",
									"line_key": "component"
								},
								{
									"type": "match",
									"rate_key": "params.direction",
									"line_key": "call_direction"
								},
								{
									"type": "match",
									"rate_key": "params.tier",
									"line_key": "tier"
								}
							]
						],
						"parameter_naa": [
							[
								{
									"type": "longestPrefix",
									"rate_key": "params.prefix",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "anaa"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "ANUM",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "BNUM",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							]
						],
						"parameter_tier_aba": [
							[
								{
									"type": "match",
									"rate_key": "params.anaa",
									"line_key": "anaa"
								},
								{
									"type": "match",
									"rate_key": "params.bnaa",
									"line_key": "bnaa"
								},
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								}
							]
						],
						"parameter_product": [
							[
								{
									"type": "longestPrefix",
									"rate_key": "params.prefix",
									"line_key": "BNUM"
								}
							]
						],
						"parameter_tier_pb": [
							[
								{
									"type": "longestPrefix",
									"rate_key": "params.prefix",
									"line_key": "BNUM"
								},
								{
									"type": "match",
									"rate_key": "params.operator",
									"line_key": "operator"
								},
								{
									"type": "match",
									"rate_key": "params.poin",
									"line_key": "poin"
								}
							]
						],
						"parameter_operator": [
							[
								{
									"type": "match",
									"rate_key": "params.path",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/^I$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "INCOMING_PATH",
												"regex": "",
												"value": "operator"
											},
											"on_false": {
												"key": "condition_result",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.path",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "call_direction"
											},
											{
												"key": "/^O$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": true,
										"projection": {
											"on_true": {
												"key": "OUTGOING_PATH",
												"regex": "",
												"value": "operator"
											},
											"on_false": {
												"key": "condition_result",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							],
							[
								{
									"type": "match",
									"rate_key": "params.path",
									"line_key": "computed",
									"computed": {
										"line_keys": [
											{
												"key": "incoming_operator"
											},
											{
												"key": "/^$/"
											}
										],
										"operator": "$regex",
										"type": "condition",
										"must_met": false,
										"projection": {
											"on_true": {
												"key": "INCOMING_PATH",
												"regex": "",
												"value": ""
											},
											"on_false": {
												"key": "OUTGOING_PATH",
												"regex": "",
												"value": ""
											}
										}
									}
								}
							]
						]
					}
				},
				"pricing": {
					"incoming_sms": [],
					"transit_outgoing_call": [],
					"parameter_tier_cb": [],
					"outgoing_sms": [],
					"parameter_scenario": [],
					"parameter_component": [],
					"transit_incoming_call": [],
					"outgoing_call": [],
					"parameter_tier_pb_anaa": [],
					"incoming_call": [],
					"parameter_naa": [],
					"parameter_tier_aba": [],
					"parameter_product": [],
					"parameter_tier_pb": [],
					"parameter_operator": []
				},
				"receiver": {
					"type": "ftp",
					"connections": [
						{
							"receiver_type": "ssh",
							"passive": false,
							"delete_received": false,
							"name": "",
							"host": "",
							"user": "",
							"password": "",
							"remote_directory": ""
						}
					],
					"limit": 3
				},
				"unify": {
					"unification_fields": {
						"required": {
							"fields": [
								"urt",
								"type",
								"aid"
							],
							"match": []
						},
						"date_seperation": "Ymd",
						"stamp": {
							"value": [
								"usaget",
								"aid",
								"sid",
								"plan",
								"arate_key",
								"billrun",
								"tax_data.taxes.0.key",
								"tax_data.taxes.0.description",
								"tax_data.taxes.0.tax",
								"tax_data.taxes.0.type",
								"tax_data.taxes.0.pass_to_customer",
								"uf.EVENT_START_DATE",
								"uf.USER_SUMMARISATION",
								"cf.product",
								"cf.operator",
								"cf.call_direction",
								"cf.incoming_operator",
								"cf.outgoing_operator",
								"cf.tier",
								"cf.component",
								"cf.scenario",
								"cf.operator_title",
								"cf.anaa_title",
								"cf.anaa_group"
							],
							"field": []
						},
						"fields": [
							{
								"match": {
									"type": "/^ICT/"
								},
								"update": [
									{
										"operation": "$setOnInsert",
										"data": [
											"arate",
											"arate_key",
											"usaget",
											"urt",
											"plan",
											"connection_type",
											"aid",
											"sid",
											"subscriber",
											"foreign",
                                                                                        "firstname",
                                                                                        "lastname",
											"billrun",
											"tax_data",
											"usagev",
											"usagev_unit",
											"aprice",
											"final_charge",
											"uf.EVENT_START_DATE",
											"uf.USER_SUMMARISATION",
											"cf.rate_type",
											"cf.rate_price",
											"cf.cash_flow",
											"cf.product",
											"cf.operator",
											"cf.call_direction",
											"cf.incoming_operator",
											"cf.outgoing_operator",
											"cf.product_group",
											"cf.tier",
											"cf.component",
											"cf.scenario",
											"cf.product_title",
											"cf.anaa_title",
											"cf.anaa_group"
										]
									},
									{
										"operation": "$set",
										"data": [
											"process_time"
										]
									},
									{
										"operation": "$inc",
										"data": [
											"usagev",
											"aprice",
											"final_charge",
											"tax_data.total_amount",
											"tax_data.taxes.0.amount"
										]
									}
								]
							}
						]
					}
				},
				"filters": [],
				"enabled": true
			},

lastConfig["export"] = 1;
lastConfig["export_generators"][0] =
		{
			"filtration": [
				{
					"collection": "archive",
					"query": [
						{
							"field": "type",
							"op": "in",
							"value": [
								"ICT"
							]
						}
					],
					"time_range": "-90 days"
				}
			],
			"name": "DATA_WAREHOUSE",
			"generator": {
				"type": "separator",
				"separator": ",",
				"force_header" : true,
				"force_footer" : true,
				"record_type_mapping": [
					{
						"record_type": "ICT",
						"conditions": [
							{
								"field": "type",
								"op": "in",
								"value": [
									"ICT"
								]
							}
						]
					}
				],
				"header_structure": [
					{
						"name": "EVENT_START_TIME",
						"type": "string",
						"path": 1,
						"hard_coded_value": "EVENT_START_TIME"
					},
					{
						"name": "USER_SUMMARISATION",
						"type": "string",
						"hard_coded_value": "USER_SUMMARISATION",
						"path": 2
					},
					{
						"name": "RATING_COMPONENT",
						"type": "string",
						"hard_coded_value": "RATING_COMPONENT",
						"path": 3
					},
					{
						"name": "CASH_FLOW",
						"type": "string",
						"hard_coded_value": "CASH_FLOW",
						"path": 4
					},
					{
						"name": "PRODUCT_GROUP",
						"type": "string",
						"hard_coded_value": "PRODUCT_GROUP",
						"path": 5
					},
					{
						"name": "EVENT_DIRECTION",
						"type": "string",
						"hard_coded_value": "EVENT_DIRECTION",
						"path": 6
					},
					{
						"name": "ANUM",
						"type": "string",
						"hard_coded_value": "ANUM",
						"path": 7
					},
					{
						"name": "BNUM",
						"type": "string",
						"hard_coded_value": "BNUM",
						"path": 8
					},
					{
						"name": "OUTGOING_PRODUCT",
						"type": "string",
						"hard_coded_value": "OUTGOING_PRODUCT",
						"path": 9
					},
					{
						"name": "INCOMING_PRODUCT",
						"type": "string",
						"hard_coded_value": "INCOMING_PRODUCT",
						"path": 10
					},
					{
						"name": "SETTLEMENT_OPERATOR",
						"type": "string",
						"hard_coded_value": "SETTLEMENT_OPERATOR",
						"path": 11
					},
					{
						"name": "BILLED_PRODUCT",
						"type": "string",
						"hard_coded_value": "BILLED_PRODUCT",
						"path": 12
					},
					{
						"name": "NETWORK_ADDRESS_AGGR_ANUM",
						"type": "string",
						"hard_coded_value": "NETWORK_ADDRESS_AGGR_ANUM",
						"path": 13
					},
					{
						"name": "NETWORK_ADDRESS_AGGR_BNUM",
						"type": "string",
						"hard_coded_value": "NETWORK_ADDRESS_AGGR_BNUM",
						"path": 14
					},
					{
						"name": "USER_DATA",
						"type": "string",
						"hard_coded_value": "USER_DATA",
						"path": 15
					},
					{
						"name": "USER_DATA_2",
						"type": "string",
						"hard_coded_value": "USER_DATA_2",
						"path": 16
					},
					{
						"name": "USER_DATA_3",
						"type": "string",
						"hard_coded_value": "USER_DATA_3",
						"path": 17
					},
					{
						"name": "COMPONENT_DIRECTION",
						"type": "string",
						"hard_coded_value": "COMPONENT_DIRECTION",
						"path": 18
					},
					{
						"name": "CURRENCY",
						"type": "string",
						"hard_coded_value": "CURRENCY",
						"path": 19
					},
					{
						"name": "ACTUAL_USAGE",
						"type": "string",
						"hard_coded_value": "ACTUAL_USAGE",
						"path": 20
					},
					{
						"name": "APPORTIONED_DURATION_SECONDS",
						"type": "string",
						"hard_coded_value": "APPORTIONED_DURATION_SECONDS",
						"path": 21
					},
					{
						"name": "AMOUNT",
						"type": "string",
						"hard_coded_value": "AMOUNT",
						"path": 22
					},
					{
						"name": "BILLING_METHOD",
						"type": "string",
						"hard_coded_value": "BILLING_METHOD",
						"path": 23
					},
					{
						"name": "INCOMING_NODE",
						"type": "string",
						"hard_coded_value": "INCOMING_NODE",
						"path": 24
					},
					{
						"name": "OUTGOING_NODE",
						"type": "string",
						"hard_coded_value": "OUTGOING_NODE",
						"path": 25
					},
					{
						"name": "INCOMING_POI",
						"type": "string",
						"hard_coded_value": "INCOMING_POI",
						"path": 26
					},
					{
						"name": "OUTGOING_POI",
						"type": "string",
						"hard_coded_value": "OUTGOING_POI",
						"path": 27
					},
					{
						"name": "TIER",
						"type": "string",
						"hard_coded_value": "TIER",
						"path": 28
					},
					{
						"name": "RECORD_SEQUENCE_NUMBER",
						"type": "string",
						"hard_coded_value": "RECORD_SEQUENCE_NUMBER",
						"path": 29
					},
					{
						"name": "PROCESS_DATE",
						"type": "string",
						"hard_coded_value": "PROCESS_DATE",
						"path": 30
					},
					{
						"name": "FILENAME",
						"type": "string",
						"hard_coded_value": "FILENAME",
						"path": 31
					},
					{
						"name": "MESSAGE_DATE",
						"type": "string",
						"hard_coded_value": "MESSAGE_DATE",
						"path": 32
					},
					{
						"name": "BILLING_DATE",
						"type": "string",
						"hard_coded_value": "BILLING_DATE",
						"path": 33
					},
					{
						"name": "ADJUSTED_DATE",
						"type": "string",
						"hard_coded_value": "ADJUSTED_DATE",
						"path": 34
					},
					{
						"name": "BILLRUN_UNIQUE_RECORD_ID",
						"type": "string",
						"hard_coded_value": "BILLRUN_UNIQUE_RECORD_ID",
						"path": 35
					},
					{
						"name": "LAST_RECALCULATION",
						"type": "string",
						"hard_coded_value": "LAST_RECALCULATION",
						"path": 36
					}
				],
				"data_structure": {
					"ICT": [
						{
							"name": "USER_SUMMARISATION",
							"type": "string",
							"format": "",
							"path": 1,
							"linked_entity": {
								"field_name": "uf.USER_SUMMARISATION",
								"entity": "line"
							}
						},
						{
							"name": "EVENT_START_TIME",
							"type": "string",
							"path": 2,
							"linked_entity": {
								"field_name": "uf.EVENT_START_TIME",
								"entity": "line"
							}
						},
						{
							"name": "RATING_COMPONENT",
							"type": "string",
							"path": 3,
							"linked_entity": {
								"field_name": "cf.component",
								"entity": "line"
							}
						},
						{
							"name": "CASH_FLOW",
							"type": "string",
							"path": 4,
							"linked_entity": {
								"field_name": "cf.cash_flow",
								"entity": "line"
							}
						},
						{
							"name": "PRODUCT_GROUP",
							"type": "string",
							"path": 5,
							"linked_entity": {
								"field_name": "cf.product_group",
								"entity": "line"
							}
						},
						{
							"name": "EVENT_DIRECTION",
							"type": "string",
							"path": 6,
							"linked_entity": {
								"field_name": "cf.event_direction",
								"entity": "line"
							}
						},
						{
							"name": "ANUM",
							"type": "string",
							"path": 7,
							"linked_entity": {
								"field_name": "uf.ANUM",
								"entity": "line"
							}
						},
						{
							"name": "BNUM",
							"type": "string",
							"path": 8,
							"linked_entity": {
								"field_name": "uf.BNUM",
								"entity": "line"
							}
						},
						{
							"name": "OUTGOING_PRODUCT",
							"type": "string",
							"path": 9,
							"linked_entity": {
								"field_name": "cf.product",
								"entity": "line"
							}
						},
						{
							"name": "INCOMING_PRODUCT",
							"type": "string",
							"path": 10,
							"linked_entity": {
								"field_name": "cf.product",
								"entity": "line"
							}
						},
						{
							"name": "SETTLEMENT_OPERATOR",
							"type": "string",
							"path": 11,
							"linked_entity": {
								"field_name": "cf.operator",
								"entity": "line"
							}
						},
						{
							"name": "BILLED_PRODUCT",
							"type": "string",
							"path": 12,
							"linked_entity": {
								"field_name": "cf.product",
								"entity": "line"
							}
						},
						{
							"name": "NETWORK_ADDRESS_AGGR_ANUM",
							"type": "string",
							"path": 13,
							"linked_entity": {
								"field_name": "cf.anaa",
								"entity": "line"
							}
						},
						{
							"name": "NETWORK_ADDRESS_AGGR_BNUM",
							"type": "string",
							"path": 14,
							"linked_entity": {
								"field_name": "cf.bnaa",
								"entity": "line"
							}
						},
						{
							"name": "USER_DATA",
							"type": "string",
							"path": 15,
							"linked_entity": {
								"field_name": "uf.USER_DATA",
								"entity": "line"
							}
						},
						{
							"name": "USER_DATA_2",
							"type": "string",
							"path": 16,
							"linked_entity": {
								"field_name": "uf.USER_DATA2",
								"entity": "line"
							}
						},
						{
							"name": "USER_DATA_3",
							"type": "string",
							"path": 17,
							"linked_entity": {
								"field_name": "uf.USER_DATA3",
								"entity": "line"
							}
						},
						{
							"name": "COMPONENT_DIRECTION",
							"type": "string",
							"path": 18,
							"linked_entity": {
								"field_name": "cf.call_direction",
								"entity": "line"
							}
						},
						{
							"name": "CURRENCY",
							"type": "string",
							"hard_coded_value": "EUR",
							"path": 19
						},
						{
							"name": "ACTUAL_USAGE",
							"type": "string",
							"path": 20,
							"linked_entity": {
								"field_name": "usagev",
								"entity": "line"
							}
						},
						{
							"name": "APPORTIONED_DURATION_SECONDS",
							"type": "string",
							"path": 21,
							"linked_entity": {
								"field_name": "usagev",
								"entity": "line"
							}
						},
						{
							"name": "AMOUNT",
							"type": "string",
							"path": 22,
							"linked_entity": {
								"field_name": "aprice",
								"entity": "line"
							}
						},
						{
							"name": "BILLING_METHOD",
							"type": "string",
							"hard_coded_value": "I",
							"path": 23
						},
						{
							"name": "INCOMING_NODE",
							"type": "string",
							"path": 24,
							"linked_entity": {
								"field_name": "uf.INCOMING_NODE",
								"entity": "line"
							}
						},
						{
							"name": "OUTGOING_NODE",
							"type": "string",
							"path": 25,
							"linked_entity": {
								"field_name": "uf.OUTGOING_NODE",
								"entity": "line"
							}
						},
						{
							"name": "INCOMING_POI",
							"type": "string",
							"path": 26,
							"linked_entity": {
								"field_name": "cf.incoming_poin",
								"entity": "line"
							}
						},
						{
							"name": "OUTGOING_POI",
							"type": "string",
							"path": 27,
							"linked_entity": {
								"field_name": "cf.outgoing_poin",
								"entity": "line"
							}
						},
						{
							"name": "TIER",
							"type": "string",
							"path": 28,
							"linked_entity": {
								"field_name": "cf.tier",
								"entity": "line"
							}
						},
						{
							"name": "RECORD_SEQUENCE_NUMBER",
							"type": "string",
							"path": 29,
							"linked_entity": {
								"field_name": "uf.RECORD_SEQUENCE_NUMBER",
								"entity": "line"
							}
						},
						{
							"name": "PROCESS_DATE",
							"type": "date",
							"path": 30,
							"linked_entity": {
								"field_name": "process_time",
								"entity": "line"
							},
							"format": "YmdHis"
						},
						{
							"name": "FILENAME",
							"type": "string",
							"path": 31,
							"linked_entity": {
								"field_name": "file",
								"entity": "line"
							}
						},
						{
							"name": "MESSAGE_DATE",
							"type": "date",
							"path": 32,
							"linked_entity": {
								"field_name": "urt",
								"entity": "line"
							},
							"format": "YmdHis"
						},
						{
							"name": "BILLING_DATE",
							"type": "date",
							"path": 33,
							"linked_entity": {
								"field_name": "urt",
								"entity": "line"
							},
							"format": "YmdHis"
						},
						{
							"name": "ADJUSTED_DATE",
							"type": "date",
							"path": 34,
							"linked_entity": {
								"field_name": "urt",
								"entity": "line"
							},
							"format": "YmdHis"
						},
						{
							"name": "BILLRUN_UNIQUE_RECORD_ID",
							"type": "string",
							"path": 35,
							"linked_entity": {
								"field_name": "stamp",
								"entity": "line"
							}
						},
						{
							"name" : "LAST_RECALCULATION",
							"type" : "date",
							"path" : 36,
							"linked_entity" : {
								"field_name" : "rebalance",
								"entity" : "line"
							},
							"format": "YmdHis"
						}
					]
				}
			},
			"senders": {
				"connections": [
					{
						"connection_type": "",
						"name": "",
						"host": "",
						"password": "",
						"user": "",
						"remote_directory": ""
					}
				]
			}
		}			
	
	//Subscriber custom fields

	lastConfig["subscribers"]["subscriber"]["fields"] =
			[
				{
					"field_name": "sid",
					"generated": true,
					"system": true,
					"unique": true,
					"editable": false,
					"display": false,
					"mandatory": true
				},
				{
					"field_name": "aid",
					"mandatory": true,
					"system": true,
					"editable": false,
					"display": false
				},
				{
					"field_name": "firstname",
					"system": true,
					"mandatory": true,
					"title": "First name",
					"editable": true,
					"display": true
				},
				{
					"field_name": "lastname",
					"system": true,
					"mandatory": true,
					"title": "Last name",
					"editable": true,
					"display": true
				},
				{
					"field_name": "plan",
					"system": true,
					"mandatory": true
				},
				{
					"field_name": "plan_activation",
					"system": true,
					"mandatory": false
				},
				{
					"field_name": "address",
					"system": true,
					"mandatory": true,
					"title": "Address",
					"editable": true,
					"display": true
				},
				{
					"field_name": "country",
					"system": true,
					"title": "Country",
					"editable": true,
					"display": true
				},
				{
					"field_name": "services",
					"system": true,
					"mandatory": false
				},
				{
					"field_name": "operator_path",
					"title": "Paths",
					"editable": true,
					"display": true,
					"multiple": true
				}
			];
//Porduct custom fields

	lastConfig["rates"]["fields"] =
			[
				{
					"field_name": "key",
					"system": true,
					"mandatory": true
				},
				{
					"field_name": "from",
					"system": true,
					"mandatory": true,
					"type": "date"
				},
				{
					"field_name": "to",
					"system": true,
					"mandatory": true,
					"type": "date"
				},
				{
					"field_name": "description",
					"system": true,
					"mandatory": true
				},
				{
					"field_name": "rates",
					"system": true,
					"mandatory": true
				},
				{
					"select_list": true,
					"display": true,
					"editable": true,
					"system": false,
					"field_name": "tariff_category",
					"default_value": "retail",
					"show_in_list": true,
					"title": "Tariff category",
					"mandatory": true,
					"changeable_props": [
						"select_options"
					],
					"select_options": "retail"
				},
				{
					"editable": true,
					"display": true,
					"title": "Prefix",
					"field_name": "params.prefix",
					"searchable": true,
					"default_value": [],
					"multiple": true
				},
				{
					"system": true,
					"display": true,
					"editable": true,
					"field_name": "invoice_label",
					"default_value": "",
					"show_in_list": true,
					"title": "Invoice label"
				},
				{
					"field_name": "params.operator",
					"title": "Operator",
					"editable": true,
					"display": true,
					"default_value": []
				},
				{
					"field_name": "params.product",
					"title": "Product",
					"editable": true,
					"display": true,
					"default_value": []
				},
				{
					"field_name": "params.path",
					"title": "Path",
					"editable": true,
					"display": true,
					"multiple": true
				},
				{
					"field_name": "params.poin",
					"title": "Point of interconnect",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.type",
					"title": "Parameter type",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.direction",
					"title": "Call Direction",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.scenario",
					"title": "Rating Scenario",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.component",
					"title": "Rating component",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.cash_flow",
					"title": "Cash Flow",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.tier_derivation",
					"title": "Tier Derivation",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.tier",
					"title": "Tier",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.incoming_operator",
					"title": "Incoming Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.outgoing_operator",
					"title": "Outgoing Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.incoming_product",
					"title": "Incoming Product",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.outgoing_product",
					"title": "Outgoing Product",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.anaa",
					"title": "Anum NAA",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.bnaa",
					"title": "Bnum NAA",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.product_group",
					"title": "Product Group",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.additional_charge",
					"title": "Additional Charge",
					"editable": true,
					"description": "This field is used to record the price of calls with one-time charge"
				},
				{
					"field_name": "params.settlement_operator",
					"title": "Settlement Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.virtual_operator",
					"title": "Virtual Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.user_summarisation",
					"title": "User Summarisation",
					"editable": true,
					"display": true
				},
				{
					"field_name": "gl_account",
					"title": "GL Account",
					"editable": true,
					"display": true
				},
				{
					"field_name": "object_id",
					"title": "Accounting Object Id",
					"editable": true,
					"display": true
				},
				{
					"field_name": "gl_account_description",
					"title": "GL Account Description",
					"editable": true,
					"display": true
				},
				{
					"field_name": "mtn_ind",
					"title": "Mtn/Ind",
					"editable": true,
					"display": true
				},
				{
					"field_name": "prod_serv",
					"title": "Prod/Serv",
					"editable": true,
					"display": true
				},
				{
					"field_name" : "params.naa",
					"title" : "Network Address Aggregation",
					"editable" : true,
					"display" : true,
					"searchable" : true
				},
				{
					"field_name" : "params.naa_parent",
					"title" : "Network Address Aggregation Parent",
					"editable" : true,
					"display" : true,
					"searchable" : true
				}
			];

//foreign fields
	lastConfig["lines"]["fields"] =
			[
				{
					"field_name": "foreign.activation_date",
					"foreign": {
						"entity": "service",
						"field": "start",
						"translate": {
							"type": "unixTimeToString",
							"format": "Y-m-d H:i:s"
						}
					}
				},
				{
					"field_name": "foreign.service.description",
					"foreign": {
						"entity": "service",
						"field": "invoice_description"
					}
				},
				{
					"field_name": "foreign.account.vat_code",
					"title": "Vat Code",
					"foreign": {
						"entity": "account",
						"field": "vat_code"
					},
					"conditions": []
				},
				{
					"field_name": "foreign.rate.rates",
					"title": "Rate",
					"foreign": {
						"entity": "rate",
						"field": "rates"
					},
					"conditions": []
				},
				{
					"field_name": "foreign.rate.description",
					"title": "tier_title",
					"foreign": {
						"entity": "rate",
						"field": "description"
					},
					"conditions": []
				},
				{
					"field_name": "foreign.account.ifs_operator_id",
					"title": "ifs_cust_code",
					"foreign": {
						"entity": "account",
						"field": "ifs_operator_id"
					},
					"conditions": []
				}
			];

//taxes
	lastConfig["taxation"] =
			{
				"tax_type": "usage",
				"default": {
					"key": ""
				},
				"mapping": {
					"vat": {
						"priorities": [
							{
								"filters": [
									{
										"line_key": "foreign.account.vat_code",
										"type": "match",
										"entity_key": "params.vat_code"
									}
								],
								"cache_db_queries": true
							}
						],
						"default_fallback": true
					}
				},
				"vat": 0,
				"vat_label": "Vat"
			};

//import mappers
	if (typeof lastConfig.import === 'undefined') {
		lastConfig['import'] = {};
	}
	lastConfig["import"]["mapping"] = [
		{
			"label": "One file loader - Rates create I calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "incoming_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create O calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "outgoing_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create TI calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "transit_incoming_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create TO calls",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "transit_outgoing_call"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "seconds"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create I SMS",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "incoming_sms"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "counter"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__14"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates create O SMS",
			"map": [
				{
					"field": "params.type",
					"value": "rate"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "outgoing_sms"
				},
				{
					"field": "from",
					"value": "__csvindex__10"
				},
				{
					"field": "usage_type_unit",
					"value": "counter"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__14"
				},
				{
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Rates update",
			"map": [
				{
					"field": "price_from",
					"value": "0"
				},
				{
					"field": "params.additional_charge",
					"value": "__csvindex__14"
				},
				{
					"field": "effective_date",
					"value": "__csvindex__10"
				},
				{
					"field": "params.product",
					"value": "__csvindex__6"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "price_to",
					"value": "UNLIMITED"
				},
				{
					"field": "params.component",
					"value": "__csvindex__5"
				},
				{
					"field": "params.direction",
					"value": "__csvindex__7"
				},
				{
					"field": "price_interval",
					"value": "1"
				},
				{
					"field": "price_value",
					"value": "__csvindex__29"
				}
			],
			"updater": {
				"field": "key",
				"value": "__csvindex__2"
			},
			"linker": [],
			"multiFieldAction": []
		},
		{
			"label": "One file loader - Tier create",
			"map": [
				{
					"field": "params.type",
					"value": "tier_cb"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				},
				{
					"field": "usage_type_value",
					"value": "parameter_tier_cb"
				},
				{
					"field": "from",
					"value": "__csvindex__6"
				},
				{
					"field": "usage_type_unit",
					"value": "counter"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.cash_flow",
					"value": "__csvindex__5"
				},
				{
					"field": "tariff_category",
					"value": "retail"
				},
				{
					"field": "params.prefix",
					"value": "__csvindex__9"
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
					"field": "description",
					"value": "__csvindex__8"
				},
				{
					"field": "key",
					"value": "__csvindex__2"
				}
			],
			"updater": [],
			"linker": [],
			"multiFieldAction": [
				{
					"field": "params.prefix",
					"value": "append"
				}
			]
		},
		{
			"label": "One file loader - Tier update",
			"map": [
				{
					"field": "effective_date",
					"value": "__csvindex__6"
				},
				{
					"field": "params.prefix",
					"value": "__csvindex__9"
				},
				{
					"field": "price_from",
					"value": "0"
				},
				{
					"field": "price_to",
					"value": "UNLIMITED"
				},
				{
					"field": "params.operator",
					"value": "__csvindex__4"
				},
				{
					"field": "params.cash_flow",
					"value": "__csvindex__5"
				},
				{
					"field": "params.tier",
					"value": "__csvindex__8"
				}
			],
			"updater": {
				"field": "key",
				"value": "__csvindex__2"
			},
			"linker": [],
			"multiFieldAction": [
				{
					"field": "params.prefix",
					"value": "append"
				}
			]
		},
		{
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

	];

	var report_MissingERPMappings = {
		"name": 'Missing ERP Mappings',
		"id": "87a7991a-d195-4d75-8a41-2d64887b0e33",
		"enable": true,
		"day": "2",
		"hour": "05",
		"send_by_email": [],
		"csv_name": "MissingERPMappings",
		"need_post_process": false,
		"params": [
			{
				"template_tag": "from",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of previous month"]
			},
			{
				"template_tag": "to",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of this month", "-1 day"]
			}
		]
	};

	var report_Armadilo = {
		"name": 'Armadilo',
		"id": "bb8f7c00-920d-42a3-b40f-3247beca065c",
		"enable": true,
		"day": "2",
		"hour": "4",
		"csv_name": "Armadilo",
		"need_post_process": false,
		"params": [
			{
				"template_tag": "from",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of previous month"]
			},
			{
				"template_tag": "to",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of this month", "-1 day"]
			}
		]
	};

	var report_Armadilo_SMS = {
		"name": 'Armadilo_SMS',
		"id": "d4bc8f9a-3dd9-403c-b159-c2afeb83335e",
		"enable": true,
		"day": "2",
		"hour": "4",
		"csv_name": "Armadilo_SMS",
		"need_post_process": false,
		"params": [
			{
				"template_tag": "from",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of previous month"]
			},
			{
				"template_tag": "to",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of this month", "-1 day"]
			}
		]
	};

	var report_Armadilo_VCE = {
		"name": 'Armadilo_VCE',
		"id": "4b639bfe-e967-43c6-8c8a-e6d8a8cd0e6c",
		"enable": true,
		"day": "2",
		"hour": "4",
		"csv_name": "Armadilo_VCE",
		"need_post_process": false,
		"params": [
			{
				"template_tag": "from",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of previous month"]
			},
			{
				"template_tag": "to",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of this month", "-1 day"]
			}
		]
	};

	var report_Billing_Cycle = {
		"name": 'Billing_Cycle',
		"id": "85a7d6cd-7c33-4673-a5ab-ae7728635336",
		"enable": true,
		"day": "2",
		"hour": "4",
		"csv_name": "Billing_Cycle",
		"need_post_process": false,
		"params": [
			{
				"template_tag": "from",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of previous month"]
			},
			{
				"template_tag": "to",
				"type": "date",
				"format": "Y-m-d",
				"value": ["first day of this month", "-1 day"]
			}
		]
	};

	var reports = [report_Armadilo, report_Armadilo_SMS, report_Armadilo_VCE, report_MissingERPMappings, report_Billing_Cycle];
	var cy_ic_plugin =
			{
				"name": "epicCyIcPlugin",
				"enabled": true,
				"system": false,
				"hide_from_ui": false,
				"configuration": {'values': {'ict': {'reports': reports}}}
			};
	lastConfig.plugins = [cy_ic_plugin];

        //EPICIC-48
	var grouping = {
		'billrun.grouping.fields': ['cf.operator', 'cf.scenario', 'cf.product', 'cf.component', 'cf.cash_flow', 'uf.USER_SUMMARISATION', 'foreign.account.ifs_operator_id']
	};
	lastConfig = addToConfig(grouping, lastConfig);
//add taxes and modify default tax
	db.taxes.update({key: "DEFAULT_VAT"}, {$set: {description: "VATL19", rate: 0.19, params: {vat_code: "VATL19"}}});
	db.taxes.save({
		"_id": ObjectId("601bb06eeac6fc628f122f12"),
		"from": ISODate("2010-01-01T00:00:00Z"),
		"key": "VIESS",
		"description": "VIESS",
		"rate": 0,
		"embed_tax": false,
		"to": ISODate("2170-02-04T08:29:34Z"),
		"creation_time": ISODate("2010-01-01T00:00:00Z"),
		"params": {
			"vat_code": "VIESS"
		}
	});
	db.taxes.save({
		"_id": ObjectId("601bb08c7918b949df330202"),
		"from": ISODate("2010-01-01T00:00:00Z"),
		"key": "VATLOS",
		"description": "VATLOS",
		"rate": 0,
		"embed_tax": false,
		"to": ISODate("2170-02-04T08:30:04Z"),
		"creation_time": ISODate("2010-01-01T00:00:00Z"),
		"params": {
			"vat_code": "VATLOS"
		}
	});

	db.rates.dropIndex("params.prefix_1");
	db.rates.ensureIndex({'params.prefix': 1}, {unique: false, sparse: false, background: true, name: "params.prefix_new"});
	db.rates.ensureIndex({'params.anaa': 1, 'params.bnaa': 1, 'params.incoming_operator': 1, 'params.outgoing_operator': 1}, {unique: false, sparse: true, background: true});
	db.rates.ensureIndex({'params.path': 1}, {unique: false, sparse: true, background: true});
	db.rates.ensureIndex({'params.operator': 1, 'params.anaa': 1, 'params.bnaa': 1}, {unique: false, sparse: false, background: true});
	db.rates.ensureIndex({'params.component': 1, 'params.operator': 1, 'params.tier': 1}, {unique: false, sparse: true, background: true});

});

var conf = {
    //EPICIC-52
    'billrun.compute.suggestions.rate_recalculations.enabled': 1,
	'log.debug.filterParams.priority.v': 5

};
lastConfig = addToConfig(conf, lastConfig);

//EPICIC-63: Timezone should be Europe/Nicosia, currency = EUR
lastConfig["pricing"]["currency"] = "EUR";
lastConfig["billrun"]["timezone"] = {
			"v" : "Europe/Nicosia",
			"t" : "Timezone"
		};

//EPICIC-59: Make more custom products fields searchable
var searchableProductFields = ["params.prefix", "params.operator", "params.product", "params.path", "params.poin", "params.direction", "params.scenario", "params.component", "params.cash_flow", "params.tier_derivation", "params.tier", "params.incoming_operator", "params.outgoing_operator", "params.incoming_product", "params.outgoing_product", "params.anaa", "params.bnaa"];
for (var i = 0; i < lastConfig["rates"]["fields"].length; i++) {
	if(searchableProductFields.includes(lastConfig["rates"]["fields"][i].field_name)) {
		lastConfig["rates"]["fields"][i]["searchable"] = true;
	}
}

//EPICIC-24: Initial customer custom fields
var operator = {
					"field_name" : "operator",
					"title" : "Operator",
					"editable" : true,
					"display" : true,
					"unique" : true,
					"mandatory" : true
				};
var operator_label = {
					"field_name" : "operator_label",
					"title" : "Operator Label",
					"editable" : true,
					"display" : true
				};
var contact = {
					"field_name" : "contact",
					"title" : "Contact",
					"editable" : true,
					"display" : true
				};
var days_to_settle = {
					"field_name" : "days_to_settle",
					"title" : "Days to settle",
					"editable" : true,
					"display" : true
				};
var ifs_operator_id = {
					"field_name" : "ifs_operator_id",
					"title" : "IFS Operator ID",
					"editable" : true,
					"display" : true
				};
var include_vat = {
					"field_name" : "include_vat",
					"title" : "Include VAT",
					"editable" : true,
					"display" : true
				};
var location = {
					"field_name" : "location",
					"title" : "Location",
					"editable" : true,
					"display" : true
				};
var vat_code = {
					"field_name" : "vat_code",
					"title" : "VAT Code",
					"editable" : true,
					"display" : true,
					"mandatory" : true
				};
var billable = {
					"field_name" : "billable",
					"title" : "Billable",
					"editable" : true,
					"display" : true,
					"type" : "boolean",
					"default_value" : false
				};
				
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], operator, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], operator_label, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], contact, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], days_to_settle, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], ifs_operator_id, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], include_vat, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], location, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], vat_code, 'account');
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], billable, 'account');

//EPICIC-75 "Undefined index: stamp" when processing files (now included directly in the processor's configuration)
//for (var i = 0; i < lastConfig.file_types.length; i++) {
//	if (lastConfig.file_types[i].file_type === "ICT") {//search for the relevant i.p
//		var cfFieldsArray = lastConfig["file_types"][i]["processor"]["calculated_fields"];
//		for (var j = 0; j < cfFieldsArray.length; j++) {
//			cfFieldsArray[j]["line_keys"] =
//					[
//						{
//							"key": "ANUM",
//						},
//						{
//							"key": "ANUM",
//						}
//					];
//			cfFieldsArray[j]["operator"] = "$eq";
//			cfFieldsArray[j]["type"] = "condition";
//			cfFieldsArray[j]["must_met"] = true;
//			cfFieldsArray[j]["projection"] = {
//				"on_true": {
//					"key": "hard_coded",
//					"value": ""
//				}
//			};
//		}
//	}
//}

lastConfig = runOnce(lastConfig, 'EPICIC-88', function () {

//Activity types
	lastConfig["usage_types"] = [
		{
			"usage_type": "incoming_call",
			"label": "incoming_call",
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "outgoing_call",
			"label": "outgoing_call",
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_product",
			"label": "parameter_product"
		},
		{
			"usage_type": "parameter_operator",
			"label": "parameter_operator",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "parameter_scenario",
			"label": "parameter_scenario",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_component",
			"label": "parameter_component"
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_tier_cb",
			"label": "parameter_tier_cb"
		},
		{
			"usage_type": "parameter_tier_aba",
			"label": "parameter_tier_aba",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "parameter_tier_pb",
			"label": "parameter_tier_pb"
		},
		{
			"usage_type": "parameter_tier_pb_anaa",
			"label": "parameter_tier_pb_anaa",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": "",
			"usage_type": "transit_incoming_call",
			"label": "transit_incoming_call"
		},
		{
			"usage_type": "transit_outgoing_call",
			"label": "transit_outgoing_call",
			"property_type": "time",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "incoming_sms",
			"label": "incoming_sms",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "outgoing_sms",
			"label": "outgoing_sms",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "erp_mapping",
			"label": "erp_mapping",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		},
		{
			"usage_type": "parameter_naa",
			"label": "parameter_naa",
			"property_type": "counter",
			"invoice_uom": "",
			"input_uom": ""
		}
	];
	for (var i = 0; i < lastConfig.file_types.length; i++) {
		if (lastConfig.file_types[i].file_type === "ICT") {
			lastConfig.file_types[i].processor = {
				"type": "Usage",
				"date_field": "EVENT_START_DATE",
				"usaget_mapping": [
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_operator",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_product",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_scenario",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_component",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_tier_cb",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_tier_aba",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_tier_pb",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_tier_pb_anaa",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "OUTGOING_PATH",
						"conditions": [
							{
								"src_field": "INCOMING_PATH",
								"pattern": "^(?!\\s*$).+",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "OUTGOING_PATH",
								"pattern": "^(?!\\s*$).+",
								"op": "$regex",
								"op_label": "Regex"
							}
						],
						"pattern": "^(?!\\s*$).+",
						"usaget": "transit_incoming_call",
						"unit": "seconds",
						"volume_type": "field",
						"volume_src": [
							"EVENT_DURATION"
						]
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "transit_outgoing_call",
						"unit": "seconds",
						"volume_type": "field",
						"volume_src": [
							"EVENT_DURATION"
						]
					},
					{
						"src_field": "OUTGOING_PATH",
						"conditions": [
							{
								"src_field": "BNUM",
								"pattern": "^S",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "INCOMING_PATH",
								"pattern": "^$",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "OUTGOING_PATH",
								"pattern": "^(?!\\s*$).+",
								"op": "$regex",
								"op_label": "Regex"
							}
						],
						"pattern": "^(?!\\s*$).+",
						"usaget": "outgoing_sms",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "OUTGOING_PATH",
						"conditions": [
							{
								"src_field": "BNUM",
								"pattern": "^S",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "INCOMING_PATH",
								"pattern": "^(?!\\s*$).+",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "OUTGOING_PATH",
								"pattern": "^$",
								"op": "$regex",
								"op_label": "Regex"
							}
						],
						"pattern": "^$",
						"usaget": "incoming_sms",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					},
					{
						"src_field": "OUTGOING_PATH",
						"conditions": [
							{
								"src_field": "BNUM",
								"pattern": "^[0-9]",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "INCOMING_PATH",
								"pattern": "^(?!\\s*$).+",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "OUTGOING_PATH",
								"pattern": "^$",
								"op": "$regex",
								"op_label": "Regex"
							}
						],
						"pattern": "^$",
						"usaget": "incoming_call",
						"unit": "seconds",
						"volume_type": "field",
						"volume_src": [
							"EVENT_DURATION"
						]
					},
					{
						"src_field": "OUTGOING_PATH",
						"conditions": [
							{
								"src_field": "BNUM",
								"pattern": "^[0-9]",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "INCOMING_PATH",
								"pattern": "^$",
								"op": "$regex",
								"op_label": "Regex"
							},
							{
								"src_field": "OUTGOING_PATH",
								"pattern": "^(?!\\s*$).+",
								"op": "$regex",
								"op_label": "Regex"
							}
						],
						"pattern": "^(?!\\s*$).+",
						"usaget": "outgoing_call",
						"unit": "seconds",
						"volume_type": "field",
						"volume_src": [
							"EVENT_DURATION"
						]
					},
					{
						"src_field": "DATA_UNIT",
						"conditions": [
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$eq",
								"op_label": "Equals"
							},
							{
								"src_field": "DATA_UNIT",
								"pattern": "a",
								"op": "$ne",
								"op_label": "Not Equals"
							}
						],
						"pattern": "a",
						"usaget": "parameter_naa",
						"unit": "counter",
						"volume_type": "value",
						"volume_src": 1
					}
				],
				"time_field": "EVENT_START_TIME",
				"date_format": "Ymd",
				"time_format": "His",
				"calculated_fields": [
					{
						"target_field": "call_direction",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "incoming_operator",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "outgoing_operator",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "operator",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "anaa",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "bnaa",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "product_title",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "product",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "product_group",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "event_direction",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "scenario",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "component",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "settlement_operator",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "virtual_operator",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "cash_flow",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "incoming_poin",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "outgoing_poin",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "poin",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "tier",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "tier_derivation",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "operator_title",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "anaa_group",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					},
					{
						"target_field": "anaa_title",
						"line_keys": [
							{
								"key": "ANUM"
							},
							{
								"key": "ANUM"
							}
						],
						"operator": "$eq",
						"type": "condition",
						"must_met": true,
						"projection": {
							"on_true": {
								"key": "hard_coded",
								"value": ""
							}
						}
					}
				],
				"orphan_files_time": "6 hours"
			};
			lastConfig.file_types[i].customer_identification_fields = {
				"incoming_sms": [
					{
						"target_key": "operator_path",
						"src_key": "INCOMING_PATH",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"transit_outgoing_call": [
					{
						"target_key": "operator_path",
						"src_key": "OUTGOING_PATH",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_tier_cb": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"outgoing_sms": [
					{
						"target_key": "operator_path",
						"src_key": "OUTGOING_PATH",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_scenario": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_component": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"transit_incoming_call": [
					{
						"target_key": "operator_path",
						"src_key": "INCOMING_PATH",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"outgoing_call": [
					{
						"target_key": "operator_path",
						"src_key": "OUTGOING_PATH",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_tier_pb_anaa": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"incoming_call": [
					{
						"target_key": "operator_path",
						"src_key": "INCOMING_PATH",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_naa": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_tier_aba": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_product": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_tier_pb": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				],
				"parameter_operator": [
					{
						"target_key": "sid",
						"src_key": "REASON_FOR_CLEARDOWN",
						"conditions": [
							{
								"field": "usaget",
								"regex": "/.*/"
							}
						],
						"clear_regex": "//"
					}
				]
			};
			lastConfig.file_types[i].rate_calculators = {
				"retail": {
					"incoming_sms": [
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						]
					],
					"transit_outgoing_call": [
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						]
					],
					"parameter_tier_cb": [
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "tier"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "operator",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.cash_flow",
								"line_key": "cash_flow"
							},
							{
								"type": "longestPrefix",
								"rate_key": "params.prefix",
								"line_key": "BNUM"
							}
						]
					],
					"outgoing_sms": [
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						]
					],
					"parameter_scenario": [
						[
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/T(I|O)/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "T"
										},
										"on_false": {
											"key": "call_direction",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "anaa"
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "bnaa"
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "INCOMING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "incoming_operator",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "OUTGOING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "outgoing_operator",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "INCOMING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "OUTGOING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/T(I|O)/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "T"
										},
										"on_false": {
											"key": "call_direction",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "condition_result",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "bnaa"
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "INCOMING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "incoming_operator",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "OUTGOING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "outgoing_operator",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "INCOMING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "OUTGOING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/T(I|O)/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "T"
										},
										"on_false": {
											"key": "call_direction",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "BNUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "INCOMING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "incoming_operator",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "OUTGOING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "outgoing_operator",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "INCOMING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "OUTGOING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/T(I|O)/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "T"
										},
										"on_false": {
											"key": "call_direction",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "condition_result",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "INCOMING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_product",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "OUTGOING_PATH"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": {
											"key": "product",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						]
					],
					"parameter_component": [
						[
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/I$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "anaa",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/O$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "bnaa",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/I$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "incoming_operator",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/O$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "outgoing_operator",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.scenario",
								"line_key": "scenario"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/I$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "anaa",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/O$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "bnaa",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.scenario",
								"line_key": "scenario"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.scenario",
								"line_key": "scenario"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/I$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "incoming_operator",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/O$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "outgoing_operator",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										}
									}
								}
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.scenario",
								"line_key": "scenario"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.incoming_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.outgoing_operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							}
						]
					],
					"transit_incoming_call": [
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						]
					],
					"outgoing_call": [
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						]
					],
					"parameter_tier_pb_anaa": [
						[
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "anaa"
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "bnaa"
							},
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.poin",
								"line_key": "poin"
							}
						]
					],
					"incoming_call": [
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "ANUM"
										}
									],
									"operator": "$exists",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "hard_coded",
											"regex": "",
											"value": "*"
										},
										"on_false": []
									}
								}
							},
							{
								"type": "match",
								"rate_key": "params.product",
								"line_key": "product"
							},
							{
								"type": "match",
								"rate_key": "params.component",
								"line_key": "component"
							},
							{
								"type": "match",
								"rate_key": "params.direction",
								"line_key": "call_direction"
							},
							{
								"type": "match",
								"rate_key": "params.tier",
								"line_key": "tier"
							}
						]
					],
					"parameter_naa": [
						[
							{
								"type": "longestPrefix",
								"rate_key": "params.prefix",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "anaa"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "ANUM",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "BNUM",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						]
					],
					"parameter_tier_aba": [
						[
							{
								"type": "match",
								"rate_key": "params.anaa",
								"line_key": "anaa"
							},
							{
								"type": "match",
								"rate_key": "params.bnaa",
								"line_key": "bnaa"
							},
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							}
						]
					],
					"parameter_product": [
						[
							{
								"type": "longestPrefix",
								"rate_key": "params.prefix",
								"line_key": "BNUM"
							}
						]
					],
					"parameter_tier_pb": [
						[
							{
								"type": "longestPrefix",
								"rate_key": "params.prefix",
								"line_key": "BNUM"
							},
							{
								"type": "match",
								"rate_key": "params.operator",
								"line_key": "operator"
							},
							{
								"type": "match",
								"rate_key": "params.poin",
								"line_key": "poin"
							}
						]
					],
					"parameter_operator": [
						[
							{
								"type": "match",
								"rate_key": "params.path",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/^I$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "INCOMING_PATH",
											"regex": "",
											"value": "operator"
										},
										"on_false": {
											"key": "condition_result",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.path",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "call_direction"
										},
										{
											"key": "/^O$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": true,
									"projection": {
										"on_true": {
											"key": "OUTGOING_PATH",
											"regex": "",
											"value": "operator"
										},
										"on_false": {
											"key": "condition_result",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						],
						[
							{
								"type": "match",
								"rate_key": "params.path",
								"line_key": "computed",
								"computed": {
									"line_keys": [
										{
											"key": "incoming_operator"
										},
										{
											"key": "/^$/"
										}
									],
									"operator": "$regex",
									"type": "condition",
									"must_met": false,
									"projection": {
										"on_true": {
											"key": "INCOMING_PATH",
											"regex": "",
											"value": ""
										},
										"on_false": {
											"key": "OUTGOING_PATH",
											"regex": "",
											"value": ""
										}
									}
								}
							}
						]
					]
				}
			};
			lastConfig.file_types[i].pricing = {
				"incoming_sms": [],
				"transit_outgoing_call": [],
				"parameter_tier_cb": [],
				"outgoing_sms": [],
				"parameter_scenario": [],
				"parameter_component": [],
				"transit_incoming_call": [],
				"outgoing_call": [],
				"parameter_tier_pb_anaa": [],
				"incoming_call": [],
				"parameter_naa": [],
				"parameter_tier_aba": [],
				"parameter_product": [],
				"parameter_tier_pb": [],
				"parameter_operator": []
			};
			lastConfig.file_types[i].unify = {
				"unification_fields": {
					"required": {
						"fields": [
							"urt",
							"type",
							"aid"
						],
						"match": []
					},
					"date_seperation": "Ymd",
					"stamp": {
						"value": [
							"usaget",
							"aid",
							"sid",
							"plan",
							"arate_key",
							"billrun",
							"tax_data.taxes.0.key",
							"tax_data.taxes.0.description",
							"tax_data.taxes.0.tax",
							"tax_data.taxes.0.type",
							"tax_data.taxes.0.pass_to_customer",
							"uf.EVENT_START_DATE",
							"uf.USER_SUMMARISATION",
							"cf.product",
							"cf.operator",
							"cf.call_direction",
							"cf.incoming_operator",
							"cf.outgoing_operator",
							"cf.tier",
							"cf.component",
							"cf.scenario",
							"cf.operator_title",
							"cf.anaa_title",
							"cf.anaa_group"
						],
						"field": []
					},
					"fields": [
						{
							"match": {
								"type": "/^ICT/"
							},
							"update": [
								{
									"operation": "$setOnInsert",
									"data": [
										"arate",
										"arate_key",
										"usaget",
										"urt",
										"plan",
										"connection_type",
										"aid",
										"sid",
										"subscriber",
										"foreign",
                                                                                "firstname",
                                                                                "lastname",                                                                                
										"billrun",
										"tax_data",
										"usagev",
										"usagev_unit",
										"aprice",
										"final_charge",
										"uf.EVENT_START_DATE",
										"uf.USER_SUMMARISATION",
										"cf.rate_type",
										"cf.rate_price",
										"cf.cash_flow",
										"cf.product",
										"cf.operator",
										"cf.call_direction",
										"cf.incoming_operator",
										"cf.outgoing_operator",
										"cf.product_group",
										"cf.tier",
										"cf.component",
										"cf.scenario",
										"cf.product_title",
										"cf.operator_title",
										"cf.anaa_title",
										"cf.anaa_group"
									]
								},
								{
									"operation": "$set",
									"data": [
										"process_time"
									]
								},
								{
									"operation": "$inc",
									"data": [
										"usagev",
										"aprice",
										"final_charge",
										"tax_data.total_amount",
										"tax_data.taxes.0.amount"
									]
								}
							]
						}
					]
				}
			};
		}
	}
	lastConfig["rates"]["fields"] =
			[
				{
					"field_name": "key",
					"system": true,
					"mandatory": true
				},
				{
					"field_name": "from",
					"system": true,
					"mandatory": true,
					"type": "date"
				},
				{
					"field_name": "to",
					"system": true,
					"mandatory": true,
					"type": "date"
				},
				{
					"field_name": "description",
					"system": true,
					"mandatory": true
				},
				{
					"field_name": "rates",
					"system": true,
					"mandatory": true
				},
				{
					"select_list": true,
					"display": true,
					"editable": true,
					"system": false,
					"field_name": "tariff_category",
					"default_value": "retail",
					"show_in_list": true,
					"title": "Tariff category",
					"mandatory": true,
					"changeable_props": [
						"select_options"
					],
					"select_options": "retail"
				},
				{
					"editable": true,
					"display": true,
					"title": "Prefix",
					"field_name": "params.prefix",
					"searchable": true,
					"default_value": [],
					"multiple": true
				},
				{
					"system": true,
					"display": true,
					"editable": true,
					"field_name": "invoice_label",
					"default_value": "",
					"show_in_list": true,
					"title": "Invoice label"
				},
				{
					"field_name": "params.operator",
					"title": "Operator",
					"editable": true,
					"display": true,
					"default_value": []
				},
				{
					"field_name": "params.product",
					"title": "Product",
					"editable": true,
					"display": true,
					"default_value": []
				},
				{
					"field_name": "params.path",
					"title": "Path",
					"editable": true,
					"display": true,
					"multiple": true
				},
				{
					"field_name": "params.poin",
					"title": "Point of interconnect",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.type",
					"title": "Parameter type",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.direction",
					"title": "Call Direction",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.scenario",
					"title": "Rating Scenario",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.component",
					"title": "Rating component",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.cash_flow",
					"title": "Cash Flow",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.tier_derivation",
					"title": "Tier Derivation",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.tier",
					"title": "Tier",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.incoming_operator",
					"title": "Incoming Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.outgoing_operator",
					"title": "Outgoing Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.incoming_product",
					"title": "Incoming Product",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.outgoing_product",
					"title": "Outgoing Product",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.anaa",
					"title": "Anum NAA",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.bnaa",
					"title": "Bnum NAA",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.product_group",
					"title": "Product Group",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.additional_charge",
					"title": "Additional Charge",
					"editable": true,
					"description": "This field is used to record the price of calls with one-time charge"
				},
				{
					"field_name": "params.settlement_operator",
					"title": "Settlement Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.virtual_operator",
					"title": "Virtual Operator",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.user_summarisation",
					"title": "User Summarisation",
					"editable": true,
					"display": true
				},
				{
					"field_name": "gl_account",
					"title": "GL Account",
					"editable": true,
					"display": true
				},
				{
					"field_name": "object_id",
					"title": "Accounting Object Id",
					"editable": true,
					"display": true
				},
				{
					"field_name": "gl_account_description",
					"title": "GL Account Description",
					"editable": true,
					"display": true
				},
				{
					"field_name": "mtn_ind",
					"title": "Mtn/Ind",
					"editable": true,
					"display": true
				},
				{
					"field_name": "prod_serv",
					"title": "Prod/Serv",
					"editable": true,
					"display": true
				},
				{
					"field_name": "params.naa",
					"title": "Network Address Aggregation",
					"editable": true,
					"display": true,
					"searchable": true
				},
				{
					"field_name": "params.naa_parent",
					"title": "Network Address Aggregation Parent",
					"editable": true,
					"display": true,
					"searchable": true
				}
			];
});

//EPICIC-83: Unify "tier title" is directed to the wrong path
//EPICIC-86: Add 'cf.anaa' to unified fields
//EPICIC-87: Add 'cf.settlement_operator' to unified fields
lastConfig = runOnce(lastConfig, 'EPICIC-83', function () {
	for (var i = 0; i < lastConfig.file_types.length; i++) {
		if (lastConfig.file_types[i].file_type === "ICT") {
			const index = lastConfig["file_types"][i]["unify"]["unification_fields"]["fields"][0]["update"][0]["data"].indexOf("cf.tier_title");
			if (index > -1) {
				lastConfig["file_types"][i]["unify"]["unification_fields"]["fields"][0]["update"][0]["data"].splice(index, 1);
			}
			lastConfig["file_types"][i]["unify"]["unification_fields"]["fields"][0]["update"][0]["data"].push('cf.anaa');
			lastConfig["file_types"][i]["unify"]["unification_fields"]["fields"][0]["update"][0]["data"].push('cf.settlement_operator');
		}
	}
});

//EPICIC-120: Customer first & last name not exist in unify line
lastConfig = runOnce(lastConfig, 'EPICIC-120', function () {
	for (var i = 0; i < lastConfig.file_types.length; i++) {
		if (lastConfig.file_types[i].file_type === "ICT") {
			lastConfig["file_types"][i]["unify"]["unification_fields"]["fields"][0]["update"][0]["data"].push('firstname');
			lastConfig["file_types"][i]["unify"]["unification_fields"]["fields"][0]["update"][0]["data"].push('lastname');
		}
	}
});

//EPICIC-66: user_summ/event_start_time position error in export generator
lastConfig = runOnce(lastConfig, 'EPICIC-66', function () {
	for (var i = 0; i < lastConfig.export_generators.length; i++) {
		if (lastConfig.export_generators[i].name === "DATA_WAREHOUSE") {
			lastConfig["export_generators"][i]["generator"]["data_structure"]["ICT"][0]["name"] = "EVENT_START_TIME";
			lastConfig["export_generators"][i]["generator"]["data_structure"]["ICT"][0]["linked_entity"]["field_name"] = "uf.EVENT_START_TIME";
			lastConfig["export_generators"][i]["generator"]["data_structure"]["ICT"][1]["name"] = "USER_SUMMARISATION";
			lastConfig["export_generators"][i]["generator"]["data_structure"]["ICT"][1]["linked_entity"]["field_name"] = "uf.USER_SUMMARISATION";
		}
	}
});

//EPICIC-29 - cach db queries
lastConfig = runOnce(lastConfig, 'EPICIC-29', function () {
	for (var i = 0; i < lastConfig.file_types.length; i++) {
		if (lastConfig.file_types[i].file_type === "ICT") {
			var rateMappingObj = lastConfig["file_types"][i]["rate_calculators"]["retail"];
			var newRateMapping = {};
			var dbQueriesArray = ["parameter_operator", "parameter_scenario", "parameter_component", "incoming_call", "outgoing_call",
				"transit_incoming_call", "transit_outgoing_call", "incoming_sms", "outgoing_sms", "parameter_tier_aba", "parameter_tier_pb_anaa"];
			Object.keys(rateMappingObj).forEach(key => {
				var prioritiesObj = {
					priorities: []
				};
				for (var i = 0; i < rateMappingObj[key].length; i++) {
					var filterObj = {
						filters: []
					};
					filterObj.filters = rateMappingObj[key][i];
					if (dbQueriesArray.includes(key)) {
						filterObj["cache_db_queries"] = true;
					}
					prioritiesObj.priorities.push(filterObj);
				}
				newRateMapping[key] = prioritiesObj;
			});
			lastConfig["file_types"][i]["rate_calculators"]["retail"] = {};
			lastConfig["file_types"][i]["rate_calculators"]["retail"] = newRateMapping;
		}
	}
});

//EPICIC-98: Add rate_price CF array
lastConfig = runOnce(lastConfig, 'EPICIC-98', function () {
	for (var i = 0; i < lastConfig.file_types.length; i++) {
		if (lastConfig.file_types[i].file_type === "ICT") {
			lastConfig["file_types"][i]["processor"]["calculated_fields"].push(
					{
							"target_field": "rate_price",
							"line_keys": [
								{
									"key": "ANUM"
								},
								{
									"key": "ANUM"
								}
							],
							"operator": "$eq",
							"type": "condition",
							"must_met": true,
							"projection": {
								"on_true": {
									"key": "hard_coded",
									"value": ""
								}
							}
						});
		}
	}
});

//EPICIC-104: Add "force_header" + "force_footer" to ic "exporter" configuration
lastConfig = runOnce(lastConfig, 'EPICIC-104', function () {
	for (var i = 0; i < lastConfig.export_generators.length; i++) {
		lastConfig.export_generators[i]['generator']["force_header"] = true;
		lastConfig.export_generators[i]['generator']["force_footer"] = true;
	}
});

//EPICIC-137: adding  'foreign.account.vat_code' to grouping
lastConfig = runOnce(lastConfig, 'EPICIC-137', function () {
  if (!lastConfig['billrun']['grouping']['fields']['foreign.account.vat_code']) {
    lastConfig['billrun']['grouping']['fields'].push('foreign.account.vat_code');
}
});

//EpicIC-56 - Set "billable" flag for active operators
lastConfig = runOnce(lastConfig, 'EPICIC-56', function () {
    billableOperatorLabels = ["MTT", "SPINT", "CABLE", "AGI", "PTL", "OTE", "CYTA", "BICS", "MT", "NCC"];
    db.subscribers.updateMany({type: "account", operator: {$in: billableOperatorLabels}}, {$set: {billable: true}});
    db.subscribers.updateMany({type: "account", operator: {$nin: billableOperatorLabels}}, {$set: {billable: false}});
});

lastConfig = runOnce(lastConfig, 'EPICIC-145', function () {
    for (var i = 0; i < lastConfig.file_types.length; i++) {
        if (lastConfig.file_types[i].file_type === "ICT") {
            for (var j = 0; j < lastConfig.file_types[i].unify.unification_fields.fields[0].update.length; j++) {
                if (lastConfig.file_types[i].unify.unification_fields.fields[0].update[j].operation === "$inc") {
                    lastConfig.file_types[i].unify.unification_fields.fields[0].update[j].data.push("cusagev");
                }
            }
        }
    }
});

lastConfig = runOnce(lastConfig, 'EPICIC-147', function () {
    var dates = [{"from": ISODate("2022-02-01T00:00:00+0200"), "to": ISODate("2022-03-01T00:00:00+0200")},
        {"from": ISODate("2022-01-01T00:00:00+0200"), "to": ISODate("2022-02-01T00:00:00+0200")}];
    dates.forEach(period => {
        var valid_archive_lines = db.archive.find({urt: {$gte: period.from, $lt: period.to}, 'cf.cusagev':{$exists: false}}).noCursorTimeout().hint({urt_1: 1});
        var false_field = [false, null];
        valid_archive_lines.forEach(line => {
            var cusagev = line.usagev;
            if (line.is_split_row === true && false_field.includes(line.split_during_mediation)) {
                cusagev = 0;
            }
            line.cusagev = cusagev;
            printjson("set cusagev as " + cusagev + " for line " + line.stamp);
            db.archive.save(line);
            db.lines.update({stamp: line.u_s}, {$inc: {'cf.cusagev': line.cf.cusagev}});
        });
    });
});

db.config.insert(lastConfig);

//EPICIC-61 - set vat_code for inactive operators
var inactiveCustomers = db.subscribers.distinct("aid", {plan: "TEST"});
db.subscribers.updateMany({type: "account", aid: {$in: inactiveCustomers}}, {$set: {vat_code: "VATLOS"}});

//Initial plans
db.plans.save({
	"_id" : ObjectId("5ffb45eae5f981402f45fcc2"),
	"from" : ISODate("2019-01-11T00:00:00Z"),
	"name" : "TEST",
	"price" : [
		{
			"price" : 0,
			"from" : 0,
			"to" : "UNLIMITED"
		}
	],
	"description" : "test",
	"recurrence" : {
		"periodicity" : "month"
	},
	"upfront" : false,
	"connection_type" : "postpaid",
	"rates" : [ ],
	"tax" : [
		{
			"type" : "vat",
			"taxation" : "global"
		}
	],
	"prorated_start" : true,
	"prorated_end" : true,
	"prorated_termination" : true,
	"to" : ISODate("2170-01-10T18:22:34Z"),
	"creation_time" : ISODate("2019-01-11T00:00:00Z")
});
db.plans.save({
	"_id" : ObjectId("603d44ec5b11a7194d4b0d12"),
	"from" : ISODate("2020-01-01T00:00:00Z"),
	"name" : "PLAN",
	"price" : [
		{
			"price" : 0,
			"from" : 0,
			"to" : "UNLIMITED"
		}
	],
	"description" : "Plan",
	"recurrence" : {
		"periodicity" : "month"
	},
	"upfront" : false,
	"connection_type" : "postpaid",
	"rates" : [ ],
	"tax" : [
		{
			"type" : "vat",
			"taxation" : "global"
		}
	],
	"prorated_start" : true,
	"prorated_end" : true,
	"prorated_termination" : true,
	"to" : ISODate("2170-03-01T19:47:56Z"),
	"creation_time" : ISODate("2020-01-01T00:00:00Z")
});