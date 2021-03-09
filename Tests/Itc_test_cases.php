<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Itc_test_cases
 *
 * @author yossi
 */
class Itc_test_cases {

	public function tests() {
		$request = new Yaf_Request_Http;
		$this->test_cases = $request->get('tests');
		$cases = [
			["test_num" => 1,
				"data" =>
				[
					"123456" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "55369610_59675589",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_OTEG_TG1",
							"OUTGOING_PATH" => "",
							"ANUM" => "302377023880",
							"BNUM" => "357199403638",
							"EVENT_START_DATE" => "20200921",
							"EVENT_START_TIME" => "114815",
							"EVENT_DURATION" => "0000000008",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200921120656_48097.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "123456",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [
					['arate_key' => 'RATE_MTT_ICTDC_FIX_TI_MTTNI04', 'aprice' => 0.041333333, "usaget" => "transit_incoming_call", "usagev_unit" => "seconds", "usagev" => 248, "cf.call_direction" => "TI"],
					['arate_key' => 'RATE_CYTA_ICTDC_FIX_TO_CTA_SING', 'aprice' => 0.001818667, "usaget" => "transit_outgoing_call", "usagev_unit" => "seconds", "usagev" => 248, "cf.call_direction" => "TO"]
				]
			],
			["test_num" => 2,
				"data" =>
				[
					"1234567" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "1D61EA358E",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMSC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "CBNT_TG",
							"ANUM" => "6285865095705",
							"BNUM" => "357161613130",
							"EVENT_START_DATE" => "20200913",
							"EVENT_START_TIME" => "070321",
							"EVENT_DURATION" => "0000000014",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "-LI0M202009130101022787",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "123456",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.0049, "usagev_unit" => "seconds",
					'cf.call_direction' => 'O', 'cf.product' => 'CCARE', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 3,
				"data" =>
				[
					"123456" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "1D640AA067",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "KENMSC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "CYTAMOB_PLA_TG",
							"ANUM" => "35799540693",
							"BNUM" => "357123999093692",
							"EVENT_START_DATE" => "20200918",
							"EVENT_START_TIME" => "211623",
							"EVENT_DURATION" => "0000000001",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KI1M202009180100851615",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "123456",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.00035, 'cf.call_direction' => 'O', 'cf.product' => '3DIGIT', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 4,
				"data" =>
				[
					"4" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "1D64161E4C",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "KENMSC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "CYTAMOB_AMA_TG",
							"ANUM" => "19379565004",
							"BNUM" => "35716990096572712",
							"EVENT_START_DATE" => "20200923",
							"EVENT_START_TIME" => "222702",
							"EVENT_DURATION" => "0000000001",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "-KI1M202009230100853067",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "4",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.000862, 'cf.call_direction' => 'O', 'cf.product' => 'MOB', "cf.scenario" => "OSDS", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 5,
				"data" =>
				[
					"5" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "1D641767F8",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "KENMSC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "CYTAMOB_PLA_TG",
							"ANUM" => "35796322663",
							"BNUM" => "35716340094017405",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "140458",
							"EVENT_DURATION" => "0000000018",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KI1M202009240100853253",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "5",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.000873, 'cf.call_direction' => 'O', 'cf.product' => 'MOB', "cf.scenario" => "OCMT", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 6,
				"data" =>
				[
					"6" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EE155AAFF6",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "KENMGCF",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "LYK_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "35724665335",
							"BNUM" => "35714800500",
							"EVENT_START_DATE" => "20200917",
							"EVENT_START_TIME" => "110721",
							"EVENT_DURATION" => "0000000008",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1MG_20200917110757_20343.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "6",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.000182, 'cf.call_direction' => 'I', 'cf.product' => '4DIGIT', "cf.scenario" => "ICYBD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 7,
				"data" =>
				[
					"7" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF15E3B0D3",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMGCF",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "KOKK_TG",
							"ANUM" => "35725399563",
							"BNUM" => "35716339090901895",
							"EVENT_START_DATE" => "20200921",
							"EVENT_START_TIME" => "122419",
							"EVENT_DURATION" => "0000000009",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "FIX_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200921122820_22267.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "7",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.02563, 'cf.call_direction' => 'O', 'cf.product' => '4DIGIT', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTEC"]]
			],
			["test_num" => 8,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF15F25B98",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMGCF",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "PTL_F_MTN_M",
							"ANUM" => "35796823336",
							"BNUM" => "357161311122",
							"EVENT_START_DATE" => "20200922",
							"EVENT_START_TIME" => "183637",
							"EVENT_DURATION" => "0000000005",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200922183739_22537.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.001155, 'cf.call_direction' => 'O', 'cf.product' => 'VMAIL', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 9,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF1602F3F1",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMGCF",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "KOKK_TG",
							"ANUM" => "35799616248",
							"BNUM" => "3571633001800",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "123953",
							"EVENT_DURATION" => "0000000367",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200924124730_22849.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.12845, 'cf.call_direction' => 'O', 'cf.product' => 'SPECIAL', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 10,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF1603AE04",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMGCF",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "PTL_F_MTN_M",
							"ANUM" => "35796004659",
							"BNUM" => "35716135090000920",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "133821",
							"EVENT_DURATION" => "0000000031",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_PREP_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200924134200_22862.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 1.6744, 'cf.call_direction' => 'O', 'cf.product' => 'PREMX', "cf.scenario" => "OPREMX", "cf.cash_flow" => "E", "cf.component" => "ICTEC"]]
			],
			["test_num" => 11,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF1603D9BF",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMGCF",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "PTL_SONUS_NIC",
							"ANUM" => "35796776437",
							"BNUM" => "3571416",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "134916",
							"EVENT_DURATION" => "0000000128",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200924135519_22865.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.02112, 'cf.call_direction' => 'O', 'cf.product' => '14', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 12,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF1603E959",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATMGCF",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "MG_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "35799033722",
							"BNUM" => "35780008803",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "135237",
							"EVENT_DURATION" => "0000000207",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "FIX_BUS",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200924135944_22866.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.04485, 'cf.call_direction' => 'I', 'cf.product' => '800', "cf.scenario" => "IFREE", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 13,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF16036C87",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMGCF",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "KOKK_TG",
							"ANUM" => "35799831075",
							"BNUM" => "35716172222526308",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "131917",
							"EVENT_DURATION" => "0000000078",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200924132101_22857.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.022724, 'cf.call_direction' => 'O', 'cf.product' => 'FIX', "cf.scenario" => "OGCOAL", "cf.cash_flow" => "E", "cf.component" => "ICTTC"]]
			],
			["test_num" => 14,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "03EF16038135",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATMGCF",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "KOKK_TG",
							"ANUM" => "35796538583",
							"BNUM" => "357112",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "132617",
							"EVENT_DURATION" => "0000000022",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1MG_20200924132945_22859.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.0005, 'cf.call_direction' => 'O', 'cf.product' => 'EMERG', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 15,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "704cb50c5e0ef8763f5173a223a61b7d",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_INT_TELE_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "35725200200",
							"BNUM" => "35712330",
							"EVENT_START_DATE" => "20200901",
							"EVENT_START_TIME" => "111344",
							"EVENT_DURATION" => "0000000073",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200901113633_47136.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.012045, 'cf.call_direction' => 'I', 'cf.product' => '3DIGIT', "cf.scenario" => "ISD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 16,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "905992_96250815",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_MONACO_TG2",
							"OUTGOING_PATH" => "",
							"ANUM" => "35799472820",
							"BNUM" => "35770009082",
							"EVENT_START_DATE" => "20200915",
							"EVENT_START_TIME" => "123330",
							"EVENT_DURATION" => "0000000964",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200915130904_47811.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.579091, 'cf.call_direction' => 'I', 'cf.product' => '700', "cf.scenario" => "ISD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 17,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "960816_56862079",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_BELGACOM_PRE_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "870771307763",
							"BNUM" => "3571434",
							"EVENT_START_DATE" => "20200919",
							"EVENT_START_TIME" => "085442",
							"EVENT_DURATION" => "0000000159",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200919090817_47995.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.026235, 'cf.call_direction' => 'I', 'cf.product' => '14', "cf.scenario" => "ISD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 18,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "9928446",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "SBC",
							"OUTGOING_NODE" => "SBC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "OUTSIDETELECONNECT",
							"ANUM" => "35796719754",
							"BNUM" => "35716180080040800",
							"EVENT_START_DATE" => "20200909",
							"EVENT_START_TIME" => "101445",
							"EVENT_DURATION" => "0000000202",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_PREP_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LSBC_cdr202009091017a",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "16"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.057536, 'cf.call_direction' => 'O', 'cf.product' => '800', "cf.scenario" => "OFREE", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 19,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "55369610_59675589",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_OTEG_TG1",
							"OUTGOING_PATH" => "",
							"ANUM" => "302377023880",
							"BNUM" => "357199403638",
							"EVENT_START_DATE" => "20200921",
							"EVENT_START_TIME" => "114815",
							"EVENT_DURATION" => "0000000008",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200921120656_48097.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.0004, 'cf.call_direction' => 'I', 'cf.product' => 'EMERG', "cf.scenario" => "ICEMRG", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 20,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "655725257",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "SMSC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "SMS_DIR_28001",
							"ANUM" => "35799700121",
							"BNUM" => "S28001",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "133655",
							"EVENT_DURATION" => "0000000001",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "SMS",
							"USER_DATA2" => "",
							"USER_DATA3" => "35796492920|35799588986|im_smsc_cdr.log.2020092413",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => ""
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.00547, 'cf.call_direction' => 'O', 'cf.product' => 'SMS', "cf.scenario" => "OSMS", "cf.cash_flow" => "E", "cf.component" => "ICTEC"]]
			],
			["test_num" => 21,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "537306763_96443645",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "KENHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_BELGACOM_PRE_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "27152962358",
							"BNUM" => "357122950000",
							"EVENT_START_DATE" => "20200908",
							"EVENT_START_TIME" => "034641",
							"EVENT_DURATION" => "0000000016",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1CC_IBCF-CDR_20200908040501_47079.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.00264, 'cf.call_direction' => 'I', 'cf.product' => 'VMAIL', "cf.scenario" => "IINT", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 22,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "722119889_54954784",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_BELGACOM_PRE_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "61406866114",
							"BNUM" => "3571122335715",
							"EVENT_START_DATE" => "20200908",
							"EVENT_START_TIME" => "112228",
							"EVENT_DURATION" => "0000000042",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200908114117_47472.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.01197, 'cf.call_direction' => 'I', 'cf.product' => 'EMERG', "cf.scenario" => "ISD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 23,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "923345302_79891747",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "KENHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_BELGACOM_PRE_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "16265028097",
							"BNUM" => "35718002278255",
							"EVENT_START_DATE" => "20200920",
							"EVENT_START_TIME" => "161643",
							"EVENT_DURATION" => "0000000010",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1CC_IBCF-CDR_20200920164023_47680.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.00285, 'cf.call_direction' => 'I', 'cf.product' => 'SPECIAL', "cf.scenario" => "ISD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 24,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "939658861_64826034",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_MONACO_TG1",
							"OUTGOING_PATH" => "",
							"ANUM" => "35796100008",
							"BNUM" => "35777778833",
							"EVENT_START_DATE" => "20200922",
							"EVENT_START_TIME" => "200924",
							"EVENT_DURATION" => "0000000113",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200922203821_48162.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.067881, 'cf.call_direction' => 'I', 'cf.product' => '77', "cf.scenario" => "ISD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 25,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "957031174_115549864",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "KENHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_MONACO_TG1",
							"OUTGOING_PATH" => "",
							"ANUM" => "35799015668",
							"BNUM" => "35724747612",
							"EVENT_START_DATE" => "20200923",
							"EVENT_START_TIME" => "083524",
							"EVENT_DURATION" => "0000000074",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1CC_IBCF-CDR_20200923090615_47809.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.000543, 'cf.call_direction' => 'I', 'cf.product' => 'FIX', "cf.scenario" => "ISD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 26,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "973530706_44702733",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "KENHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_BELGACOM_PRE_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "19492708479",
							"BNUM" => "35780092381",
							"EVENT_START_DATE" => "20200903",
							"EVENT_START_TIME" => "103335",
							"EVENT_DURATION" => "0000000444",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1CC_IBCF-CDR_20200903110641_46853.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.0962, 'cf.call_direction' => 'I', 'cf.product' => '800', "cf.scenario" => "IFREE", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 27,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "973955536_101060756",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "KENHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_BELGACOM_PRE_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "35796106069",
							"BNUM" => "35711892",
							"EVENT_START_DATE" => "20200912",
							"EVENT_START_TIME" => "164735",
							"EVENT_DURATION" => "0000000084",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1CC_IBCF-CDR_20200912170622_47297.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.014, 'cf.call_direction' => 'I', 'cf.product' => '118', "cf.scenario" => "IENQ", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 28,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "D25DFA1F-F5AB11EA-8814F786-8FE2DE1E",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_NETCONNECT_TG1",
							"OUTGOING_PATH" => "",
							"ANUM" => "442036701411",
							"BNUM" => "35796967077",
							"EVENT_START_DATE" => "20200914",
							"EVENT_START_TIME" => "132811",
							"EVENT_DURATION" => "0000000001",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "SUB_DIS",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200914133626_47764.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.000125, 'cf.call_direction' => 'I', 'cf.product' => 'IN_ROAM', "cf.scenario" => "IIR", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 29,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "D25DFA1F-F5AB11EA-8814F786-8FE2DE1E",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_NETCONNECT_TG1",
							"OUTGOING_PATH" => "",
							"ANUM" => "442036701411",
							"BNUM" => "35796967077",
							"EVENT_START_DATE" => "20200914",
							"EVENT_START_TIME" => "132811",
							"EVENT_DURATION" => "0000000001",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "SUB_DIS",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200914133626_47764.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.000125, 'cf.call_direction' => 'I', 'cf.product' => 'IN_ROAM', "cf.scenario" => "IIR", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 30,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "FFF7531C-F37011EA-A1EDF642-73C5CDA0",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_NETCONNECT_TG4",
							"OUTGOING_PATH" => "",
							"ANUM" => "36303521764",
							"BNUM" => "35725258177",
							"EVENT_START_DATE" => "20200911",
							"EVENT_START_TIME" => "172214",
							"EVENT_DURATION" => "0000000055",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "FIX_BUS",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200911173807_47628.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.001623, 'cf.call_direction' => 'I', 'cf.product' => 'FIX', "cf.scenario" => "IINT", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 31,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "FFFEE721-F59F11EA-B556F642-73C5CDA0",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_NETCONNECT_TG4",
							"OUTGOING_PATH" => "",
							"ANUM" => "35797793498",
							"BNUM" => "35799332701",
							"EVENT_START_DATE" => "20200914",
							"EVENT_START_TIME" => "120343",
							"EVENT_DURATION" => "0000000213",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200914123616_47762.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.026625, 'cf.call_direction' => 'I', 'cf.product' => 'MOB', "cf.scenario" => "IINT", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 32,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "mafzkzkzf7zo6gmf7tit8fzk7qh85kmt",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_MTT_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "74996480490",
							"BNUM" => "35712163572",
							"EVENT_START_DATE" => "20200922",
							"EVENT_START_TIME" => "005302",
							"EVENT_DURATION" => "0000000001",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "TRANSIT",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200922010032_48123.dat-",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.000167, 'cf.call_direction' => 'I', 'cf.product' => '3DIGIT', "cf.scenario" => "IINT", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 33,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "qi58lfk6ffftq7l8l5qtf6aa66tla8g7",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_MTT_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "78007552771",
							"BNUM" => "35796901638",
							"EVENT_START_DATE" => "20200911",
							"EVENT_START_TIME" => "143820",
							"EVENT_DURATION" => "0000000061",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_BUS",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200911150738_47623.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.060898, 'cf.call_direction' => 'I', 'cf.product' => 'MOB', "cf.scenario" => "ISDS", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 34,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "yzzll77ql65vmygvxgmzvx6vhvwdl5q5",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "KENHUASBC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "ISBC_INT_TELE_TG",
							"ANUM" => "35722345311",
							"BNUM" => "35770079",
							"EVENT_START_DATE" => "20200917",
							"EVENT_START_TIME" => "111718",
							"EVENT_DURATION" => "0000000050",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "FIX_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1CC_IBCF-CDR_20200917113737_47526.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.060417, 'cf.call_direction' => 'O', 'cf.product' => '700', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 35,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "zlg6wgldvyvdgz5w7y5yqvzvfzx6x4f4",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "KENHUASBC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "ISBC_INT_TELE_TG",
							"ANUM" => "35796112810",
							"BNUM" => "35716000390090980",
							"EVENT_START_DATE" => "20200913",
							"EVENT_START_TIME" => "173043",
							"EVENT_DURATION" => "0000000473",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_PREP_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "KS1CC_IBCF-CDR_20200913180711_47347.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 6.51794, 'cf.call_direction' => 'O', 'cf.product' => 'PREMK', "cf.scenario" => "OPREMK", "cf.cash_flow" => "E", "cf.component" => "ICTRS"]]
			],
			["test_num" => 36,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "zt7a7qtz8gfm5tfkktlzamz6qaqmg6ll",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "LATHUASBC",
							"OUTGOING_NODE" => "",
							"INCOMING_PATH" => "ISBC_MTT_TG",
							"OUTGOING_PATH" => "",
							"ANUM" => "74952320822",
							"BNUM" => "35726007244",
							"EVENT_START_DATE" => "20200924",
							"EVENT_START_TIME" => "122001",
							"EVENT_DURATION" => "0000000071",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "SUB_DIS",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200924123612_48242.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.037867, 'cf.call_direction' => 'I', 'cf.product' => 'FIX', "cf.scenario" => "ISDS", "cf.cash_flow" => "R", "cf.component" => "ICTDC"]]
			],
			["test_num" => 37,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "zzz76wgvdzmxg6mhwdwglvz6qydmglyz",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATHUASBC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "ISBC_INT_TELE_TG",
							"ANUM" => "35799463856",
							"BNUM" => "35716005025200200",
							"EVENT_START_DATE" => "20200922",
							"EVENT_START_TIME" => "150314",
							"EVENT_DURATION" => "0000000256",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200922153721_48152.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.001877, 'cf.call_direction' => 'O', 'cf.product' => 'FIX', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 38,
				"data" =>
				[
					"8" => [
						"uf" =>
						[
							"RECORD_SEQUENCE_NUMBER" => "zzzzmlyff7l5q6z55xwhzhqfvvxxl6wv",
							"RECORD_TYPE" => "",
							"INCOMING_NODE" => "",
							"OUTGOING_NODE" => "LATHUASBC",
							"INCOMING_PATH" => "",
							"OUTGOING_PATH" => "ISBC_MONACO_TG2",
							"ANUM" => "35796543468",
							"BNUM" => "306971442933",
							"EVENT_START_DATE" => "20200906",
							"EVENT_START_TIME" => "221754",
							"EVENT_DURATION" => "0000000081",
							"DATA_VOLUME" => "0000000000000000000000000",
							"DATA_UNIT" => "",
							"DATA_VOLUME_2" => "0000000000000000000000000",
							"DATA_UNIT_2" => "",
							"DATA_VOLUME_3" => "0000000000000000000000000",
							"DATA_UNIT_3" => "",
							"USER_SUMMARISATION" => "MOB_POST_RES",
							"USER_DATA" => "",
							"USER_DATA2" => "",
							"USER_DATA3" => "LS1CC_IBCF-CDR_20200906223848_47398.dat",
							"REPAIR_INDICATOR" => "",
							"REASON_FOR_CLEARDOWN" => "0"
						],
						"stamp" => "8",
						"type" => "ICT",
					]
				],
				"file_type" => "ICT", "expected" => [['aprice' => 0.0135, 'cf.call_direction' => 'O', 'cf.product' => 'IVOICE', "cf.scenario" => "OSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"]]
			],
			["test_num" => 39,
				"file_type" => "ICT", 
				"expected" => [
					['aprice' => 0.0155, 'cf.call_direction' => 'TO', 'cf.product' => 'IVOICE', "cf.scenario" => "TSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"],
					['aprice' => 0.0403, 'cf.call_direction' => 'TI', 'cf.product' => 'IVOICE', "cf.scenario" => "TSD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],
				]
			],
			["test_num" => 40,
				"file_type" => "ICT", 
				"expected" => [
					['aprice' => 0.049211333, 'cf.call_direction' => 'TO', 'cf.product' => 'FIX', "cf.scenario" => "TSD_AB", "cf.cash_flow" => "E", "cf.component" => "ICTDC"],
					['aprice' => 0.051733333, 'cf.call_direction' => 'TI', 'cf.product' => 'FIX', "cf.scenario" => "TSD_AB", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],				]
			],
			["test_num" => 41,
				"file_type" => "ICT", 
				"expected" => [
					['aprice' => 0.00517, 'cf.call_direction' => 'TO', 'cf.product' => 'MOB', "cf.scenario" => "TSD_AB", "cf.cash_flow" => "E", "cf.component" => "ICTDC"],
					['aprice' => 0.006, 'cf.call_direction' => 'TI', 'cf.product' => 'MOB', "cf.scenario" => "TSD_AB", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],
				]
			],
			["test_num" => 42,
				"file_type" => "ICT", 
				"expected" => [
					['aprice' => 0.02345, 'cf.call_direction' => 'TO', 'cf.product' => 'SPECIAL', "cf.scenario" => "TSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"],
					['aprice' => 0.00335, 'cf.call_direction' => 'TI', 'cf.product' => 'SPECIAL', "cf.scenario" => "TSD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],
				]
			],
			["test_num" => 43,
				"file_type" => "ICT", 
				"expected" => [
					['aprice' => 0.004533333, 'cf.call_direction' => 'TO', 'cf.product' => 'MOB', "cf.scenario" => "TSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"],
					['aprice' => 0.02, 'cf.call_direction' => 'TI', 'cf.product' => 'MOB', "cf.scenario" => "TSD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],
				]
			],
			["test_num" => 44,
				"file_type" => "ICT", 
				"expected" => [
					['aprice' => 0.001249417, 'cf.call_direction' => 'TO', 'cf.product' => 'EMERG', "cf.scenario" => "TSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"],
					['aprice' => 0.00275, 'cf.call_direction' => 'TI', 'cf.product' => 'EMERG', "cf.scenario" => "TSD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],
				]
			],
			["test_num" => 45,
				"file_type" => "ICT", 
				"expected" => [
					['aprice' => 0.000278667, 'cf.call_direction' => 'TO', 'cf.product' => 'FIX', "cf.scenario" => "TSD", "cf.cash_flow" => "E", "cf.component" => "ICTDC"],
					['aprice' => 0.001266667, 'cf.call_direction' => 'TI', 'cf.product' => 'FIX', "cf.scenario" => "TSD", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],
				]
			],
			//special case where
			["test_num" => 46,
				"file_type" => "ICT", 
				"expected" => [
					['cf.call_direction' => 'TO', 'cf.product' => 'FIX', "cf.scenario" => "TCT"],
					['aprice' => 0.25449, 'cf.call_direction' => 'TI', 'cf.product' => 'FIX', "cf.scenario" => "TCT", "cf.cash_flow" => "R", "cf.component" => "ICTDC"],
				]
			]
		];
		if ($this->test_cases) {
			$this->test_cases = explode(',', $this->test_cases);
			foreach ($cases as $case) {
				if (in_array($case['test_num'], $this->test_cases))
					$newarr[] = $case;
			}
			return $newarr;
		}
		return $cases;
	}

}
