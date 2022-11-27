const Templates = {
  Asterisk_CDR: {
    "file_type" : "Asterisk_CDR",
  "parser" : {
      "type" : "separator",
      "separator" : "|",
      "structure" : [
  {"name": "accountcode"},
  {"name": "src"},
  {"name": "dst"},
  {"name": "dcontext"},
  {"name": "clid"},
  {"name": "channel"},
  {"name": "dstchannel"},
  {"name": "lastapp"},
  {"name": "lastdata"},
  {"name": "start"},
  {"name": "answer"},
  {"name": "end"},
  {"name": "duration"},
  {"name": "billsec"},
  {"name": "disposition"},
  {"name": "amaflags"},
  {"name": "userfield"},
  {"name": "uniqueid"}
      ],
      "custom_keys" : [
	"accountcode",
	"src",
	"dst",
	"dcontext",
	"clid",
	"channel",
	"dstchannel",
	"lastapp",
	"lastdata",
	"start",
	"answer",
	"end",
	"duration",
	"billsec",
	"disposition",
	"amaflags",
	"userfield",
	"uniqueid"
      ],
      "line_types" : {
	"H" : "/^none$/",
	"D" : "//",
	"T" : "/^none$/"
      }
    },
    "processor" : {
      "type" : "Usage",
      "date_field" : "answer",
      "default_volume_type" : "field",
      "default_volume_src" : ["duration"],
      "default_usaget" : "call",
      "orphan_files_time" : "6 hours"
    },
    "customer_identification_fields" : {
      "call": [
        {
          "target_key" : "sid",
          "clear_regex" : "//",
          "src_key" : "src"
        }
      ]
    },
    "rate_calculators" : {
      "retail": {
        "call" : [[
  	{
  	  "type" : "longestPrefix",
  	  "rate_key" : "params.prefix",
  	  "line_key" : "dst"
  	}
  ]]
      }
    },
    "pricing": {
      "call": {
      }
    }
  },
  UK_Standard_CDR_v3: {
    "file_type" : "UK_Standard_CDR_v3",
    "parser" : {
      "type" : "separator",
      "separator" : ",",
      "structure" : [
  {"name": "call_type"},
  {"name": "call_cause"},
  {"name": "customer_identifier"},
  {"name": "telephone_number_dialled"},
  {"name": "call_date"},
  {"name": "call_time"},
  {"name": "duration"},
  {"name": "bytes_transmitted"},
  {"name": "bytes_received"},
  {"name": "description"},
  {"name": "chargecode"},
  {"name": "time_band"},
  {"name": "salesprice"},
  {"name": "salesprice__pre_bundle_"},
  {"name": "extension"},
  {"name": "ddi"},
  {"name": "grouping_id"},
  {"name": "call_class"},
  {"name": "carrier"},
  {"name": "recording"},
  {"name": "vat"},
  {"name": "country_of_origin"},
  {"name": "network"},
  {"name": "retail_tariff_code"},
  {"name": "remote_network"},
  {"name": "apn"},
  {"name": "diverted_number"},
  {"name": "ring_time"},
  {"name": "recordid"},
  {"name": "currency"},
  {"name": "presentation_number"},
  {"name": "network_access_reference"},
  {"name": "ngcs_access_charge"},
  {"name": "ngcs_service_charge"},
  {"name": "total_bytes_transferred"},
  {"name": "user_id"},
  {"name": "onward_billing_reference"},
  {"name": "contract_name"},
  {"name": "bundle_name"},
  {"name": "bundle_allowance"},
  {"name": "discount_reference"},
  {"name": "routing_code"}
      ],
      "custom_keys" : [
	"call_type",
	"call_cause",
	"customer_identifier",
	"telephone_number_dialled",
	"call_date",
	"call_time",
	"duration",
	"bytes_transmitted",
	"bytes_received",
	"description",
	"chargecode",
	"time_band",
	"salesprice",
	"salesprice__pre_bundle_",
	"extension",
	"ddi",
	"grouping_id",
	"call_class",
	"carrier",
	"recording",
	"vat",
	"country_of_origin",
	"network",
	"retail_tariff_code",
	"remote_network",
	"apn",
	"diverted_number",
	"ring_time",
	"recordid",
	"currency",
	"presentation_number",
	"network_access_reference",
	"ngcs_access_charge",
	"ngcs_service_charge",
	"total_bytes_transferred",
	"user_id",
	"onward_billing_reference",
	"contract_name",
	"bundle_name",
	"bundle_allowance",
	"discount_reference",
	"routing_code"
      ],
      "line_types" : {
	"H" : "/^none$/",
	"D" : "//",
	"T" : "/^none$/"
      }
    },
    "processor" : {
      "type" : "Usage",
      "date_field" : "call_date",
      "usaget_mapping" : [
	{
	  "src_field" : "call_type",
	  "pattern" : "/^G$/",
	  "usaget" : "GPRS_Data",
    "volume_type": "field",
    "volume_src": ["duration"]
	}
      ],
      "orphan_files_time" : "6 hours"
    },
    "customer_identification_fields" : {
      "GPRS_Data": [
        {
          "target_key" : "sid",
          "clear_regex" : "//",
          "src_key" : "customer_identifier"
        }
      ]
    },
    "rate_calculators" : {
      "retail": {
        "GPRS_Data" : [[
  	{
  	  "type" : "match",
  	  "rate_key" : "key",
  	  "line_key" : "apn"
  	}
      ]]
      }
    },
    "pricing": {
      "GPRS_Data": {
      }
    }
  },
  FreeSWITCH_CDR: {
    "file_type" : "FreeSWITCH_CDR",
    "parser" : {
      "type" : "separator",
      "separator" : ",",
      "structure" : [
  {"name": "caller_id_name"},
  {"name": "caller_id_number"},
  {"name": "destination_number"},
  {"name": "context"},
  {"name": "start_stamp"},
  {"name": "answer_stamp"},
  {"name": "end_stamp"},
  {"name": "duration"},
  {"name": "billsec"},
  {"name": "hangup_cause"},
  {"name": "uuid"},
  {"name": "bleg_uuid"},
  {"name": "accountcode"},
  {"name": "read_codec"},
  {"name": "write_codec"}
      ],
      "custom_keys" : [
	"caller_id_name",
	"caller_id_number",
	"destination_number",
	"context",
	"start_stamp",
	"answer_stamp",
	"end_stamp",
	"duration",
	"billsec",
	"hangup_cause",
	"uuid",
	"bleg_uuid",
	"accountcode",
	"read_codec",
	"write_codec"
      ],
      "line_types" : {
	"H" : "/^none$/",
	"D" : "//",
	"T" : "/^none$/"
      }
    },
    "processor" : {
      "type" : "Usage",
      "date_field" : "answer_stamp",
      "default_volume_type" : "field",
      "default_volume_src" : ["billsec"],
      "default_usaget" : "call",
      "orphan_files_time" : "6 hours"
    },
    "customer_identification_fields" : {
      "call": [
        {
          "target_key" : "sid",
          "clear_regex" : "//",
        	"src_key" : "caller_id_number"
        }
      ]
    },
    "rate_calculators" : {
      "retail": {
        "call" : [[
  	{
  	  "type" : "longestPrefix",
  	  "rate_key" : "params.prefix",
  	  "line_key" : "caller_id_number"
  	}
      ]]
      }
    },
    "pricing": {
      "call": {
      }
    }
  },
  Cisco_CDR: {
    "file_type" : "Cisco_CDR",
    "parser" : {
      "type" : "separator",
      "separator" : ",",
      "structure" : [
  {"name": "cdrrecordtype"},
  {"name": "globalcallid_callmanagerid"},
  {"name": "globalcallid_callid"},
  {"name": "origlegcallidentifier"},
  {"name": "datetimeorigination"},
  {"name": "orignodeid"},
  {"name": "origspan"},
  {"name": "origipaddr"},
  {"name": "callingpartynumber"},
  {"name": "callingpartyunicodeloginuserid"},
  {"name": "origcause_location"},
  {"name": "origcause_value"},
  {"name": "origprecedencelevel"},
  {"name": "origmediatransportaddress_ip"},
  {"name": "origmediatransportaddress_port"},
  {"name": "origmediacap_payloadcapability"},
  {"name": "origmediacap_maxframesperpacket"},
  {"name": "origmediacap_g723bitrate"},
  {"name": "origvideocap_codec"},
  {"name": "origvideocap_bandwidth"},
  {"name": "origvideocap_resolution"},
  {"name": "origvideotransportaddress_ip"},
  {"name": "origvideotransportaddress_port"},
  {"name": "origrsvpaudiostat"},
  {"name": "origrsvpvideostat"},
  {"name": "destlegcallidentifier"},
  {"name": "destnodeid"},
  {"name": "destspan"},
  {"name": "destipaddr"},
  {"name": "originalcalledpartynumber"},
  {"name": "finalcalledpartynumber"},
  {"name": "finalcalledpartyunicodeloginuserid"},
  {"name": "destcause_location"},
  {"name": "destcause_value"},
  {"name": "destprecedencelevel"},
  {"name": "destmediatransportaddress_ip"},
  {"name": "destmediatransportaddress_port"},
  {"name": "destmediacap_payloadcapability"},
  {"name": "destmediacap_maxframesperpacket"},
  {"name": "destmediacap_g723bitrate"},
  {"name": "destvideocap_codec"},
  {"name": "destvideocap_bandwidth"},
  {"name": "destvideocap_resolution"},
  {"name": "destvideotransportaddress_ip"},
  {"name": "destvideotransportaddress_port"},
  {"name": "destrsvpaudiostat"},
  {"name": "destrsvpvideostat"},
  {"name": "datetimeconnect"},
  {"name": "datetimedisconnect"},
  {"name": "lastredirectdn"},
  {"name": "pkid"},
  {"name": "originalcalledpartynumberpartition"},
  {"name": "callingpartynumberpartition"},
  {"name": "finalcalledpartynumberpartition"},
  {"name": "lastredirectdnpartition"},
  {"name": "duration"},
  {"name": "origdevicename"},
  {"name": "destdevicename"},
  {"name": "origcallterminationonbehalfof"},
  {"name": "destcallterminationonbehalfof"},
  {"name": "origcalledpartyredirectonbehalfof"},
  {"name": "lastredirectredirectonbehalfof"},
  {"name": "origcalledpartyredirectreason"},
  {"name": "lastredirectredirectreason"},
  {"name": "destconversationid"},
  {"name": "globalcallid_clusterid"},
  {"name": "joinonbehalfof"},
  {"name": "comment"},
  {"name": "authcodedescription"},
  {"name": "authorizationlevel"},
  {"name": "clientmattercode"},
  {"name": "origdtmfmethod"},
  {"name": "destdtmfmethod"},
  {"name": "callsecuredstatus"},
  {"name": "origconversationid"},
  {"name": "origmediacap_bandwidth"},
  {"name": "destmediacap_bandwidth"},
  {"name": "authorizationcodevalue"},
  {"name": "outpulsedcallingpartynumber"},
  {"name": "outpulsedcalledpartynumber"},
  {"name": "origipv4v6addr"},
  {"name": "destipv4v6addr"},
  {"name": "origvideocap_codec_channel2"},
  {"name": "origvideocap_bandwidth_channel2"},
  {"name": "origvideocap_resolution_channel2"},
  {"name": "origvideotransportaddress_ip_channel2"},
  {"name": "origvideotransportaddress_port_channel2"},
  {"name": "origvideochannel_role_channel2"},
  {"name": "destvideocap_codec_channel2"},
  {"name": "destvideocap_bandwidth_channel2"},
  {"name": "destvideocap_resolution_channel2"},
  {"name": "destvideotransportaddress_ip_channel2"},
  {"name": "destvideotransportaddress_port_channel2"},
  {"name": "destvideochannel_role_channel2"},
  {"name": "incomingprotocolid"},
  {"name": "incomingprotocolcallref"},
  {"name": "outgoingprotocolid"},
  {"name": "outgoingprotocolcallref"},
  {"name": "currentroutingreason"},
  {"name": "origroutingreason"},
  {"name": "lastredirectingroutingreason"},
  {"name": "huntpilotdn"},
  {"name": "huntpilotpartition"},
  {"name": "calledpartypatternusage"}
      ],
      "custom_keys" : [
	"cdrrecordtype",
	"globalcallid_callmanagerid",
	"globalcallid_callid",
	"origlegcallidentifier",
	"datetimeorigination",
	"orignodeid",
	"origspan",
	"origipaddr",
	"callingpartynumber",
	"callingpartyunicodeloginuserid",
	"origcause_location",
	"origcause_value",
	"origprecedencelevel",
	"origmediatransportaddress_ip",
	"origmediatransportaddress_port",
	"origmediacap_payloadcapability",
	"origmediacap_maxframesperpacket",
	"origmediacap_g723bitrate",
	"origvideocap_codec",
	"origvideocap_bandwidth",
	"origvideocap_resolution",
	"origvideotransportaddress_ip",
	"origvideotransportaddress_port",
	"origrsvpaudiostat",
	"origrsvpvideostat",
	"destlegcallidentifier",
	"destnodeid",
	"destspan",
	"destipaddr",
	"originalcalledpartynumber",
	"finalcalledpartynumber",
	"finalcalledpartyunicodeloginuserid",
	"destcause_location",
	"destcause_value",
	"destprecedencelevel",
	"destmediatransportaddress_ip",
	"destmediatransportaddress_port",
	"destmediacap_payloadcapability",
	"destmediacap_maxframesperpacket",
	"destmediacap_g723bitrate",
	"destvideocap_codec",
	"destvideocap_bandwidth",
	"destvideocap_resolution",
	"destvideotransportaddress_ip",
	"destvideotransportaddress_port",
	"destrsvpaudiostat",
	"destrsvpvideostat",
	"datetimeconnect",
	"datetimedisconnect",
	"lastredirectdn",
	"pkid",
	"originalcalledpartynumberpartition",
	"callingpartynumberpartition",
	"finalcalledpartynumberpartition",
	"lastredirectdnpartition",
	"duration",
	"origdevicename",
	"destdevicename",
	"origcallterminationonbehalfof",
	"destcallterminationonbehalfof",
	"origcalledpartyredirectonbehalfof",
	"lastredirectredirectonbehalfof",
	"origcalledpartyredirectreason",
	"lastredirectredirectreason",
	"destconversationid",
	"globalcallid_clusterid",
	"joinonbehalfof",
	"comment",
	"authcodedescription",
	"authorizationlevel",
	"clientmattercode",
	"origdtmfmethod",
	"destdtmfmethod",
	"callsecuredstatus",
	"origconversationid",
	"origmediacap_bandwidth",
	"destmediacap_bandwidth",
	"authorizationcodevalue",
	"outpulsedcallingpartynumber",
	"outpulsedcalledpartynumber",
	"origipv4v6addr",
	"destipv4v6addr",
	"origvideocap_codec_channel2",
	"origvideocap_bandwidth_channel2",
	"origvideocap_resolution_channel2",
	"origvideotransportaddress_ip_channel2",
	"origvideotransportaddress_port_channel2",
	"origvideochannel_role_channel2",
	"destvideocap_codec_channel2",
	"destvideocap_bandwidth_channel2",
	"destvideocap_resolution_channel2",
	"destvideotransportaddress_ip_channel2",
	"destvideotransportaddress_port_channel2",
	"destvideochannel_role_channel2",
	"incomingprotocolid",
	"incomingprotocolcallref",
	"outgoingprotocolid",
	"outgoingprotocolcallref",
	"currentroutingreason",
	"origroutingreason",
	"lastredirectingroutingreason",
	"huntpilotdn",
	"huntpilotpartition",
	"calledpartypatternusage"
      ],
      "line_types" : {
	"H" : "/^none$/",
	"D" : "//",
	"T" : "/^none$/"
      }
    },
    "processor" : {
      "type" : "Usage",
      "date_field" : "datetimeconnect",
      "usaget_mapping" : [
	{
	  "src_field" : "calledpartypatternusage",
	  "pattern" : "/^NA$/",
	  "usaget" : "NA",
    "volume_type": "field",
    "volume_src": ["duration"]
	}
      ],
      "orphan_files_time" : "6 hours"
    },
    "customer_identification_fields" : {
      "NA": [
        {
          "target_key" : "sid",
          "clear_regex" : "//",
        	"src_key" : "callingpartynumber"
        }
      ]
    },
    "rate_calculators" : {
      "retail": {
        "NA" : [[
  	{
  	  "type" : "longestPrefix",
  	  "rate_key" : "params.prefix",
  	  "line_key" : "originalcalledpartynumber"
  	}
      ]]
      }
    },
    "pricing": {
      "retail": {
      }
    }
  },
	Voip_Ms_CDR: {
      "file_type": "Voip_Ms_CDR",
      "parser": {
        "type": "separator",
        "separator": ",",
        "structure": [
          {"name": "Date"},
          {"name": "CallerID"},
          {"name": "Originator"},
          {"name": "Destination"},
          {"name": "CallType"},
          {"name": "Duration"},
          {"name": "PricePerMinute"},
          {"name": "FinalCharge"}
        ],
        "csv_has_header": false,
        "csv_has_footer": false,
        "custom_keys": [
          "Date",
          "CallerID",
          "Originator",
          "Destination",
          "CallType",
          "Duration",
          "PricePerMinute",
          "FinalCharge"
        ],
        "line_types": {
          "H": "/^none$/",
          "D": "//",
          "T": "/^none$/"
        }
      },
      "processor": {
        "type": "Usage",
        "date_field": "Date",
        "usaget_mapping": [
          {
            "src_field": "CallType",
            "pattern": "/^Inbound*/",
            "usaget": "inbound_call",
            "unit": "seconds",
            "volume_type": "field",
            "volume_src": ["Duration"]
          },
          {
            "src_field": "CallType",
            "pattern": "/^(?!Inbound).*$/",
            "usaget": "outbound_call",
            "unit": "seconds",
            "volume_type": "field",
            "volume_src": ["Duration"]
          }
        ],
        "date_format": "m/d/Y H:i",
        "orphan_files_time": "6 hours"
      },
      "customer_identification_fields": {
        "inbound_call": [
          {
            "target_key": "phone",
            "src_key": "Destination",
            "conditions": [
              {
                "field": "usaget",
                "regex": "/.*/"
              }
            ],
            "clear_regex": "//"
          }
        ],
        "outbound_call": [
          {
            "target_key": "phone",
            "src_key": "Originator",
            "conditions": [
              {
                "field": "usaget",
                "regex": "/.*/"
              }
            ],
            "clear_regex": "/.*<|\\D/"
          }
        ]
      },
      "rate_calculators": {
        "retail": {
          "inbound_call": [
            [
              {
                "type": "longestPrefix",
                "rate_key": "prefix",
                "line_key": "computed"
              }
            ]
          ],
          "outbound_call": [
            [
              {
                "type": "longestPrefix",
                "rate_key": "params.prefix",
                "line_key": "Destination"
              }
            ]
          ]
        }
      },
      "pricing": {
        "inbound_call": {
        },
        "outbound_call": {
        }
      }
  },
  Bandwidth_CDR: {
      "file_type": "Bandwidth_CDR",
      "parser": {
        "type": "separator",
        "separator": "|",
        "structure": [
          {"name": "AccountNumber"},
          {"name": "CallStartDateTime"},
          {"name": "CallEndDateTime"},
          {"name": "CallType"},
          {"name": "CallSource"},
          {"name": "CallDestination"},
          {"name": "Duration"},
          {"name": "PerMinRate"},
          {"name": "Amount"},
          {"name": "TierType"},
          {"name": "SourceCountry"},
          {"name": "SourceState"},
          {"name": "SourceLATA"},
          {"name": "SourceRateCenter"},
          {"name": "DestinationCountry"},
          {"name": "DestinationState"},
          {"name": "DestinationLATA"},
          {"name": "DesinationRateCenter"},
          {"name": "CallID"},
          {"name": "BdrID"},
          {"name": "SourceIP"},
          {"name": "DestinationIP"},
          {"name": "RateAttempts"},
          {"name": "LRN"},
          {"name": "LocationID"},
          {"name": "SubAccountID"},
          {"name": "LocationName"},
          {"name": "SubAccountName____"}
        ],
        "csv_has_header": true,
        "csv_has_footer": false,
        "custom_keys": [
          "AccountNumber",
          "CallStartDateTime",
          "CallEndDateTime",
          "CallType",
          "CallSource",
          "CallDestination",
          "Duration",
          "PerMinRate",
          "Amount",
          "TierType",
          "SourceCountry",
          "SourceState",
          "SourceLATA",
          "SourceRateCenter",
          "DestinationCountry",
          "DestinationState",
          "DestinationLATA",
          "DesinationRateCenter",
          "CallID",
          "BdrID",
          "SourceIP",
          "DestinationIP",
          "RateAttempts",
          "LRN",
          "LocationID",
          "SubAccountID",
          "LocationName",
          "SubAccountName____"
        ],
        "line_types": {
          "H": "/^none$/",
          "D": "//",
          "T": "/^none$/"
        }
      },
      "processor": {
        "type": "Usage",
        "date_field": "CallStartDateTime",
        "usaget_mapping": [
          {
            "src_field": "CallType",
            "pattern": "WVO",
            "usaget": "inbound_call",
            "unit": "seconds",
            "volume_type": "field",
            "volume_src": ["Duration"]
          },
          {
            "src_field": "CallType",
            "pattern": "/^(?!WVO).*$/",
            "usaget": "outbound_call",
            "unit": "seconds",
            "volume_type": "field",
            "volume_src": ["Duration"]
          }
        ],
        "date_format": "m/d/Y H:i:s A",
        "orphan_files_time": "6 hours"
      },
      "customer_identification_fields": {
        "inbound_call": [
          {
            "target_key": "phone",
            "src_key": "CallDestination",
            "conditions": [
              {
                "field": "usaget",
                "regex": "/.*/"
              }
            ],
            "clear_regex": "//"
          }
        ],
        "outbound_call": [
          {
            "target_key": "phone",
            "src_key": "CallSource",
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
          "inbound_call": [
            [
              {
                "type": "longestPrefix",
                "rate_key": "params.prefix",
                "line_key": "CallSource"
              }
            ]
          ],
          "outbound_call": [
            [
              {
                "type": "longestPrefix",
                "rate_key": "params.prefix",
                "line_key": "CallDestination"
              }
            ]
          ]
        }
      },
      "pricing": {
        "inbound_call": {
          "aprice_field": "Amount"
        },
        "outbound_call": {
          "aprice_field": "Amount"
        }
      }
  }
};

export default Templates;
