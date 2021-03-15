var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

//Add plugin
if(!lastConfig['plugins'].includes("epicCyIcPlugin")) {
    lastConfig.plugins.push("epicCyIcPlugin");
}

//Activity types
lastConfig["usage_types"] = [
		{
			"usage_type" : "incoming_call",
			"label" : "incoming_call",
			"property_type" : "time",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"usage_type" : "outgoing_call",
			"label" : "outgoing_call",
			"property_type" : "time",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : "",
			"usage_type" : "parameter_poin",
			"label" : "parameter_poin"
		},
		{
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : "",
			"usage_type" : "parameter_product",
			"label" : "parameter_product"
		},
		{
			"usage_type" : "parameter_operator",
			"label" : "parameter_operator",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"usage_type" : "parameter_scenario",
			"label" : "parameter_scenario",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"usage_type" : "parameter_anaa",
			"label" : "parameter_anaa",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"usage_type" : "parameter_bnaa",
			"label" : "parameter_bnaa",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : "",
			"usage_type" : "parameter_component",
			"label" : "parameter_component"
		},
		{
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : "",
			"usage_type" : "parameter_tier_cb",
			"label" : "parameter_tier_cb"
		},
		{
			"usage_type" : "parameter_tier_aba",
			"label" : "parameter_tier_aba",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : "",
			"usage_type" : "parameter_tier_pb",
			"label" : "parameter_tier_pb"
		},
		{
			"usage_type" : "parameter_tier_pb_anaa",
			"label" : "parameter_tier_pb_anaa",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"property_type" : "time",
			"invoice_uom" : "",
			"input_uom" : "",
			"usage_type" : "transit_incoming_call",
			"label" : "transit_incoming_call"
		},
		{
			"usage_type" : "transit_outgoing_call",
			"label" : "transit_outgoing_call",
			"property_type" : "time",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"usage_type" : "incoming_sms",
			"label" : "incoming_sms",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		},
		{
			"usage_type" : "outgoing_sms",
			"label" : "outgoing_sms",
			"property_type" : "counter",
			"invoice_uom" : "",
			"input_uom" : ""
		}
	],


//Input processor
lastConfig["file_types"][0] = 
		{
			"file_type" : "ICT",
			"parser" : {
				"type" : "fixed",
				"line_types" : {
					"H" : "/^none$/",
					"D" : "//",
					"T" : "/^none$/"
				},
				"separator" : "",
				"structure" : [
					{
						"name" : "RECORD_SEQUENCE_NUMBER",
						"checked" : true,
						"width" : "40"
					},
					{
						"name" : "RECORD_TYPE",
						"checked" : true,
						"width" : "2"
					},
					{
						"name" : "INCOMING_NODE",
						"checked" : true,
						"width" : "20"
					},
					{
						"name" : "OUTGOING_NODE",
						"checked" : true,
						"width" : "20"
					},
					{
						"name" : "INCOMING_PATH",
						"checked" : true,
						"width" : "20"
					},
					{
						"name" : "OUTGOING_PATH",
						"checked" : true,
						"width" : "20"
					},
					{
						"name" : "ANUM",
						"checked" : true,
						"width" : "50"
					},
					{
						"name" : "BNUM",
						"checked" : true,
						"width" : "50"
					},
					{
						"name" : "EVENT_START_DATE",
						"checked" : true,
						"width" : "8"
					},
					{
						"name" : "EVENT_START_TIME",
						"checked" : true,
						"width" : "6"
					},
					{
						"name" : "EVENT_DURATION",
						"checked" : true,
						"width" : "10"
					},
					{
						"name" : "DATA_VOLUME",
						"checked" : true,
						"width" : "25"
					},
					{
						"name" : "DATA_UNIT",
						"checked" : true,
						"width" : "8"
					},
					{
						"name" : "DATA_VOLUME_2",
						"checked" : true,
						"width" : "25"
					},
					{
						"name" : "DATA_UNIT_2",
						"checked" : true,
						"width" : "8"
					},
					{
						"name" : "DATA_VOLUME_3",
						"checked" : true,
						"width" : "25"
					},
					{
						"name" : "DATA_UNIT_3",
						"checked" : true,
						"width" : "8"
					},
					{
						"name" : "USER_SUMMARISATION",
						"checked" : true,
						"width" : "20"
					},
					{
						"name" : "USER_DATA",
						"checked" : true,
						"width" : "20"
					},
					{
						"name" : "USER_DATA2",
						"checked" : true,
						"width" : "80"
					},
					{
						"name" : "USER_DATA3",
						"checked" : true,
						"width" : "80"
					},
					{
						"name" : "REPAIR_INDICATOR",
						"checked" : true,
						"width" : "1"
					},
					{
						"name" : "REASON_FOR_CLEARDOWN",
						"checked" : true,
						"width" : "4"
					}
				],
				"csv_has_header" : false,
				"csv_has_footer" : false,
				"custom_keys" : [
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
			"processor" : {
				"type" : "Usage",
				"date_field" : "EVENT_START_DATE",
				"usaget_mapping" : [
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_operator",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_product",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_anaa",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_bnaa",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_scenario",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_component",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_tier_cb",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_tier_aba",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_tier_pb",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "parameter_tier_pb_anaa",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "OUTGOING_PATH",
						"conditions" : [
							{
								"src_field" : "INCOMING_PATH",
								"pattern" : "^(?!\\s*$).+",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "OUTGOING_PATH",
								"pattern" : "^(?!\\s*$).+",
								"op" : "$regex",
								"op_label" : "Regex"
							}
						],
						"pattern" : "^(?!\\s*$).+",
						"usaget" : "transit_incoming_call",
						"unit" : "seconds",
						"volume_type" : "field",
						"volume_src" : [
							"EVENT_DURATION"
						]
					},
					{
						"src_field" : "DATA_UNIT",
						"conditions" : [
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$eq",
								"op_label" : "Equals"
							},
							{
								"src_field" : "DATA_UNIT",
								"pattern" : "a",
								"op" : "$ne",
								"op_label" : "Not Equals"
							}
						],
						"pattern" : "a",
						"usaget" : "transit_outgoing_call",
						"unit" : "seconds",
						"volume_type" : "field",
						"volume_src" : [
							"EVENT_DURATION"
						]
					},
					{
						"src_field" : "OUTGOING_PATH",
						"conditions" : [
							{
								"src_field" : "BNUM",
								"pattern" : "^S",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "INCOMING_PATH",
								"pattern" : "^$",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "OUTGOING_PATH",
								"pattern" : "^(?!\\s*$).+",
								"op" : "$regex",
								"op_label" : "Regex"
							}
						],
						"pattern" : "^(?!\\s*$).+",
						"usaget" : "outgoing_sms",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "OUTGOING_PATH",
						"conditions" : [
							{
								"src_field" : "BNUM",
								"pattern" : "^S",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "INCOMING_PATH",
								"pattern" : "^(?!\\s*$).+",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "OUTGOING_PATH",
								"pattern" : "^$",
								"op" : "$regex",
								"op_label" : "Regex"
							}
						],
						"pattern" : "^$",
						"usaget" : "incoming_sms",
						"unit" : "counter",
						"volume_type" : "value",
						"volume_src" : 1
					},
					{
						"src_field" : "OUTGOING_PATH",
						"conditions" : [
							{
								"src_field" : "BNUM",
								"pattern" : "^[0-9]",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "INCOMING_PATH",
								"pattern" : "^(?!\\s*$).+",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "OUTGOING_PATH",
								"pattern" : "^$",
								"op" : "$regex",
								"op_label" : "Regex"
							}
						],
						"pattern" : "^$",
						"usaget" : "incoming_call",
						"unit" : "seconds",
						"volume_type" : "field",
						"volume_src" : [
							"EVENT_DURATION"
						]
					},
					{
						"src_field" : "OUTGOING_PATH",
						"conditions" : [
							{
								"src_field" : "BNUM",
								"pattern" : "^[0-9]",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "INCOMING_PATH",
								"pattern" : "^$",
								"op" : "$regex",
								"op_label" : "Regex"
							},
							{
								"src_field" : "OUTGOING_PATH",
								"pattern" : "^(?!\\s*$).+",
								"op" : "$regex",
								"op_label" : "Regex"
							}
						],
						"pattern" : "^(?!\\s*$).+",
						"usaget" : "outgoing_call",
						"unit" : "seconds",
						"volume_type" : "field",
						"volume_src" : [
							"EVENT_DURATION"
						]
					}
				],
				"time_field" : "EVENT_START_TIME",
				"date_format" : "Ymd",
				"time_format" : "His",
				"calculated_fields" : [
					{
						"target_field" : "call_direction"
					},
					{
						"target_field" : "incoming_operator"
					},
					{
						"target_field" : "outgoing_operator"
					},
					{
						"target_field" : "operator"
					},
					{
						"target_field" : "anaa"
					},
					{
						"target_field" : "bnaa"
					},
					{
						"target_field" : "product"
					},
					{
						"target_field" : "scenario"
					},
					{
						"target_field" : "component"
					},
					{
						"target_field" : "cash_flow"
					},
					{
						"target_field" : "poin"
					},
					{
						"target_field" : "tier"
					}
				],
				"orphan_files_time" : "6 hours"
			},
			"customer_identification_fields" : {
				"incoming_sms" : [
					{
						"target_key" : "operator_path",
						"src_key" : "INCOMING_PATH",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_bnaa" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_anaa" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"transit_outgoing_call" : [
					{
						"target_key" : "operator_path",
						"src_key" : "OUTGOING_PATH",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_tier_cb" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"outgoing_sms" : [
					{
						"target_key" : "operator_path",
						"src_key" : "OUTGOING_PATH",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_scenario" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_component" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"transit_incoming_call" : [
					{
						"target_key" : "operator_path",
						"src_key" : "INCOMING_PATH",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"outgoing_call" : [
					{
						"target_key" : "operator_path",
						"src_key" : "OUTGOING_PATH",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_tier_pb_anaa" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"incoming_call" : [
					{
						"target_key" : "operator_path",
						"src_key" : "INCOMING_PATH",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_tier_aba" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_product" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_tier_pb" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				],
				"parameter_operator" : [
					{
						"target_key" : "sid",
						"src_key" : "REASON_FOR_CLEARDOWN",
						"conditions" : [
							{
								"field" : "usaget",
								"regex" : "/.*/"
							}
						],
						"clear_regex" : "//"
					}
				]
			},
			"rate_calculators" : {
				"retail" : {
					"incoming_sms" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						]
					],
					"parameter_bnaa" : [
						[
							{
								"type" : "longestPrefix",
								"rate_key" : "params.prefix",
								"line_key" : "BNUM"
							}
						]
					],
					"parameter_anaa" : [
						[
							{
								"type" : "longestPrefix",
								"rate_key" : "params.prefix",
								"line_key" : "ANUM"
							}
						]
					],
					"transit_outgoing_call" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						]
					],
					"parameter_tier_cb" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "tier"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "operator",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.cash_flow",
								"line_key" : "cash_flow"
							},
							{
								"type" : "longestPrefix",
								"rate_key" : "params.prefix",
								"line_key" : "BNUM"
							}
						]
					],
					"outgoing_sms" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						]
					],
					"parameter_scenario" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/T(I|O)/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "T"
										},
										"on_false" : {
											"key" : "call_direction",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "anaa"
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "bnaa"
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "INCOMING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "incoming_operator",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "OUTGOING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "outgoing_operator",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "INCOMING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "OUTGOING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/T(I|O)/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "T"
										},
										"on_false" : {
											"key" : "call_direction",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "condition_result",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "bnaa"
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "INCOMING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "incoming_operator",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "OUTGOING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "outgoing_operator",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "INCOMING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "OUTGOING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/T(I|O)/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "T"
										},
										"on_false" : {
											"key" : "call_direction",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "BNUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "INCOMING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "incoming_operator",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "OUTGOING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "outgoing_operator",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "INCOMING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "OUTGOING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/T(I|O)/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "T"
										},
										"on_false" : {
											"key" : "call_direction",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "condition_result",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "INCOMING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_product",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "OUTGOING_PATH"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : {
											"key" : "product",
											"regex" : "",
											"value" : ""
										}
									}
								}
							}
						]
					],
					"parameter_component" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/I$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "anaa",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/O$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "bnaa",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/I$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "incoming_operator",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/O$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "outgoing_operator",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.scenario",
								"line_key" : "scenario"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/I$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "anaa",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/O$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "bnaa",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.scenario",
								"line_key" : "scenario"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.scenario",
								"line_key" : "scenario"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/I$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "incoming_operator",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/O$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "outgoing_operator",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										}
									}
								}
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.scenario",
								"line_key" : "scenario"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.incoming_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.outgoing_operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							}
						]
					],
					"transit_incoming_call" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						]
					],
					"outgoing_call" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						]
					],
					"parameter_tier_pb_anaa" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "anaa"
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "bnaa"
							},
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.poin",
								"line_key" : "poin"
							}
						]
					],
					"incoming_call" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "ANUM"
										}
									],
									"operator" : "$exists",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "hard_coded",
											"regex" : "",
											"value" : "*"
										},
										"on_false" : [ ]
									}
								}
							},
							{
								"type" : "match",
								"rate_key" : "params.product",
								"line_key" : "product"
							},
							{
								"type" : "match",
								"rate_key" : "params.component",
								"line_key" : "component"
							},
							{
								"type" : "match",
								"rate_key" : "params.direction",
								"line_key" : "call_direction"
							},
							{
								"type" : "match",
								"rate_key" : "params.tier",
								"line_key" : "tier"
							}
						]
					],
					"parameter_tier_aba" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.anaa",
								"line_key" : "anaa"
							},
							{
								"type" : "match",
								"rate_key" : "params.bnaa",
								"line_key" : "bnaa"
							},
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							}
						]
					],
					"parameter_product" : [
						[
							{
								"type" : "longestPrefix",
								"rate_key" : "params.prefix",
								"line_key" : "BNUM"
							}
						]
					],
					"parameter_tier_pb" : [
						[
							{
								"type" : "longestPrefix",
								"rate_key" : "params.prefix",
								"line_key" : "BNUM"
							},
							{
								"type" : "match",
								"rate_key" : "params.operator",
								"line_key" : "operator"
							},
							{
								"type" : "match",
								"rate_key" : "params.poin",
								"line_key" : "poin"
							}
						]
					],
					"parameter_operator" : [
						[
							{
								"type" : "match",
								"rate_key" : "params.path",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/^I$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "INCOMING_PATH",
											"regex" : "",
											"value" : "operator"
										},
										"on_false" : {
											"key" : "condition_result",
											"regex" : "",
											"value" : ""
										}
									}
								}
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.path",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "call_direction"
										},
										{
											"key" : "/^O$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : true,
									"projection" : {
										"on_true" : {
											"key" : "OUTGOING_PATH",
											"regex" : "",
											"value" : "operator"
										},
										"on_false" : {
											"key" : "condition_result",
											"regex" : "",
											"value" : ""
										}
									}
								}
							}
						],
						[
							{
								"type" : "match",
								"rate_key" : "params.path",
								"line_key" : "computed",
								"computed" : {
									"line_keys" : [
										{
											"key" : "incoming_operator"
										},
										{
											"key" : "/^$/"
										}
									],
									"operator" : "$regex",
									"type" : "condition",
									"must_met" : false,
									"projection" : {
										"on_true" : {
											"key" : "INCOMING_PATH",
											"regex" : "",
											"value" : ""
										},
										"on_false" : {
											"key" : "OUTGOING_PATH",
											"regex" : "",
											"value" : ""
										}
									}
								}
							}
						]
					]
				}
			},
			"pricing" : {
				"incoming_sms" : [ ],
				"parameter_bnaa" : [ ],
				"parameter_anaa" : [ ],
				"transit_outgoing_call" : [ ],
				"parameter_tier_cb" : [ ],
				"outgoing_sms" : [ ],
				"parameter_scenario" : [ ],
				"parameter_component" : [ ],
				"transit_incoming_call" : [ ],
				"outgoing_call" : [ ],
				"parameter_tier_pb_anaa" : [ ],
				"incoming_call" : [ ],
				"parameter_tier_aba" : [ ],
				"parameter_product" : [ ],
				"parameter_tier_pb" : [ ],
				"parameter_operator" : [ ]
			},
			"receiver" : {
				"type" : "ftp",
				"connections" : [
					{
						"receiver_type" : "ssh",
						"passive" : false,
						"delete_received" : false,
						"name" : "",
						"host" : "",
						"user" : "",
						"password" : "",
						"remote_directory" : ""
					}
				],
				"limit" : 3
			},
			"unify" : [ ],
			"filters" : [ ],
			"enabled" : true
		},

//Subscriber custom fields

lastConfig["subscribers"]["subscriber"]["fields"] =
[
				{
					"field_name" : "sid",
					"generated" : true,
					"system" : true,
					"unique" : true,
					"editable" : false,
					"display" : false,
					"mandatory" : true
				},
				{
					"field_name" : "aid",
					"mandatory" : true,
					"system" : true,
					"editable" : false,
					"display" : false
				},
				{
					"field_name" : "firstname",
					"system" : true,
					"mandatory" : true,
					"title" : "First name",
					"editable" : true,
					"display" : true
				},
				{
					"field_name" : "lastname",
					"system" : true,
					"mandatory" : true,
					"title" : "Last name",
					"editable" : true,
					"display" : true
				},
				{
					"field_name" : "plan",
					"system" : true,
					"mandatory" : true
				},
				{
					"field_name" : "plan_activation",
					"system" : true,
					"mandatory" : false
				},
				{
					"field_name" : "address",
					"system" : true,
					"mandatory" : true,
					"title" : "Address",
					"editable" : true,
					"display" : true
				},
				{
					"field_name" : "country",
					"system" : true,
					"title" : "Country",
					"editable" : true,
					"display" : true
				},
				{
					"field_name" : "services",
					"system" : true,
					"mandatory" : false
				},
				{
					"field_name" : "operator_path",
					"title" : "Paths",
					"editable" : true,
					"display" : true,
					"multiple" : true
				}
			];
//Porduct custom fields
		
lastConfig["rates"]["fields"] =
[
			{
				"field_name" : "key",
				"system" : true,
				"mandatory" : true
			},
			{
				"field_name" : "from",
				"system" : true,
				"mandatory" : true,
				"type" : "date"
			},
			{
				"field_name" : "to",
				"system" : true,
				"mandatory" : true,
				"type" : "date"
			},
			{
				"field_name" : "description",
				"system" : true,
				"mandatory" : true
			},
			{
				"field_name" : "rates",
				"system" : true,
				"mandatory" : true
			},
			{
				"select_list" : true,
				"display" : true,
				"editable" : true,
				"system" : false,
				"field_name" : "tariff_category",
				"default_value" : "retail",
				"show_in_list" : true,
				"title" : "Tariff category",
				"mandatory" : true,
				"changeable_props" : [
					"select_options"
				],
				"select_options" : "retail"
			},
			{
				"editable" : true,
				"display" : true,
				"title" : "Prefix",
				"field_name" : "params.prefix",
				"searchable" : true,
				"default_value" : [ ],
				"multiple" : true
			},
			{
				"system" : true,
				"display" : true,
				"editable" : true,
				"field_name" : "invoice_label",
				"default_value" : "",
				"show_in_list" : true,
				"title" : "Invoice label"
			},
			{
				"field_name" : "params.operator",
				"title" : "Operator",
				"editable" : true,
				"display" : true,
				"default_value" : [ ]
			},
			{
				"field_name" : "params.product",
				"title" : "Product",
				"editable" : true,
				"display" : true,
				"default_value" : [ ]
			},
			{
				"field_name" : "params.path",
				"title" : "Path",
				"editable" : true,
				"display" : true,
				"multiple" : true
			},
			{
				"field_name" : "params.poin",
				"title" : "Point of interconnect",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.type",
				"title" : "Parameter type",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.direction",
				"title" : "Call Direction",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.scenario",
				"title" : "Rating Scenario",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.component",
				"title" : "Rating component",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.cash_flow",
				"title" : "Cash Flow",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.tier_derivation",
				"title" : "Tier Derivation",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.tier",
				"title" : "Tier",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.incoming_operator",
				"title" : "Incoming Operator",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.outgoing_operator",
				"title" : "Outgoing Operator",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.incoming_product",
				"title" : "Incoming Product",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.outgoing_product",
				"title" : "Outgoing Product",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.anaa",
				"title" : "Anum NAA",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.bnaa",
				"title" : "Bnum NAA",
				"editable" : true,
				"display" : true
			},
			{
				"field_name" : "params.product_group",
				"title" : "Product Group",
				"editable" : true,
				"display" : true
			}
		];
		
		//foreign fields
		lastConfig["lines"]["fields"] =
[
			{
				"field_name" : "foreign.activation_date",
				"foreign" : {
					"entity" : "service",
					"field" : "start",
					"translate" : {
						"type" : "unixTimeToString",
						"format" : "Y-m-d H:i:s"
					}
				}
			},
			{
				"field_name" : "foreign.service.description",
				"foreign" : {
					"entity" : "service",
					"field" : "invoice_description"
				}
			},
			{
				"field_name" : "foreign.account.vat_code",
				"title" : "Vat Code",
				"foreign" : {
					"entity" : "account",
					"field" : "vat_code"
				},
				"conditions" : [ ]
			}
		];
		
		//taxes
		lastConfig["taxation"] = 
		{
		"tax_type" : "usage",
		"default" : {
			"key" : ""
		},
		"mapping" : {
			"vat" : {
				"priorities" : [
					{
						"filters" : [
							{
								"line_key" : "foreign.account.vat_code",
								"type" : "match",
								"entity_key" : "params.vat_code"
							}
						],
						"cache_db_queries" : true
					}
				],
				"default_fallback" : true
			}
		},
		"vat" : 0,
		"vat_label" : "Vat"
	},

db.config.insert(lastConfig);


//add taxes and modify default tax
db.taxes.save({
	"_id" : ObjectId("5ff83b60306dbaffe2c3a88c"),
	"from" : ISODate("2018-12-31T21:00:00Z"),
	"key" : "DEFAULT_VAT",
	"description" : "VATL19",
	"rate" : 0.17,
	"params" : {
		"vat_code" : "VATL19"
	},
	"to" : ISODate("2168-12-31T21:00:00Z"),
	"creation_time" : ISODate("2018-12-31T21:00:00Z"),
	"embed_tax" : false
});
db.taxes.save({
	"_id" : ObjectId("601bb06eeac6fc628f122f12"),
	"from" : ISODate("2010-01-01T00:00:00Z"),
	"key" : "VIESS",
	"description" : "VIESS",
	"rate" : 0,
	"embed_tax" : false,
	"to" : ISODate("2170-02-04T08:29:34Z"),
	"creation_time" : ISODate("2010-01-01T00:00:00Z"),
	"params" : {
		"vat_code" : "VIESS"
	}
});
db.taxes.save({
	"_id" : ObjectId("601bb08c7918b949df330202"),
	"from" : ISODate("2010-01-01T00:00:00Z"),
	"key" : "VATLOS",
	"description" : "VATLOS",
	"rate" : 0,
	"embed_tax" : false,
	"to" : ISODate("2170-02-04T08:30:04Z"),
	"creation_time" : ISODate("2010-01-01T00:00:00Z"),
	"params" : {
		"vat_code" : "VATLOS"
	}
});

db.rates.dropIndex("params.prefix_1");
db.rates.ensureIndex({'params.prefix': 1 }, { unique: false , sparse:false, background: true, name: "params.prefix_new" });
db.rates.ensureIndex({'params.anaa': 1, 'params.bnaa': 1, 'params.incoming_operator': 1, 'params.outgoing_operator': 1}, { unique: false , sparse:true, background: true });
db.rates.ensureIndex({'params.path': 1 }, { unique: false , sparse:true, background: true });
db.rates.ensureIndex({'params.operator': 1, 'params.anaa': 1, 'params.bnaa': 1}, { unique: false , sparse:false, background: true });
db.rates.ensureIndex({'params.component': 1, 'params.operator': 1, 'params.tier': 1}, { unique: false , sparse:true, background: true });