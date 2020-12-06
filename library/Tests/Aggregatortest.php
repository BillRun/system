<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * 
 * @package  calculator
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Aggregator extends UnitTestCase {

	use Tests_SetUp;

	protected $fails;
	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $servicesCol;
	protected $discountsCol;
	protected $subscribersCol;
	protected $balancesCol;
	protected $billrunCol;
	protected $BillrunObj;
	protected $returnBillrun;
	public $ids;
	public $message;
	public $label = 'aggregate';
	public $defaultOptions = array(
		"type" => "customer",
		"stamp" => "201806",
		"page" => 0,
		"size" => 100,
		'fetchonly' => true,
		'generate_pdf' => 0,
		"force_accounts" => array()
	);
	public $LatestResults;
	public $sumBillruns;
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span><br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span><br>';
	protected $tests = array(
		/* check if the pagination work */
		array('test' => array('test_number' => 1, 'aid' => 0, 'function' => array('pagination',), 'options' => array("page" => 3000, "size" => 3000, "stamp" => "201805")),
			'expected' => array(),
			'postRun' => array()),
		/* test 1 Account with single subscriber with a plan (aid:3,sid:4,plan_a) */
		array('test' => array('test_number' => 2, "aid" => 3, 'function' => array('basicCompare', 'checkInvoiceId', 'invoice_exist', 'lineExists', 'passthrough'), 'invoice_path' => '201805_3_101.pdf', 'options' => array('generate_pdf' => 1, "stamp" => "201805", "force_accounts" => array(3))),
			'expected' => array('billrun' => array('invoice_id' => 101, 'billrun_key' => '201805', 'aid' => 3),
				'line' => array('types' => array('flat', 'credit'), 'final_charge' => (-10))),
			'postRun' => array()),
		/* check What is  the behavior when Force_accounts contains duplicate  accounts */
		array('test' => array('test_number' => 3, "aid" => 3, 'function' => array('basicCompare', 'lineExists', 'duplicateAccounts', 'passthrough'), 'options' => array("stamp" => "201805", "force_accounts" => array(3))),
			'expected' => array('billrun' => array('invoice_id' => 101, 'billrun_key' => '201805', 'aid' => 3),
				'line' => array('types' => array('flat', 'credit'), 'final_charge' => (-10))),
			'postRun' => array()),
		/* Account with single subscriber with a plan + service(aid:5,sid:6,plan_a,service) */
		array('test' => array('test_number' => 4, "aid" => 5, 'function' => array('basicCompare', 'linesVSbillrun', 'rounded', 'passthrough'), 'options' => array("force_accounts" => array(5))),
			'expected' => array('billrun' => array('invoice_id' => 102, 'billrun_key' => '201806', 'aid' => 5))),
		/* Account with single subscriber with plan + many services    (aid:7,sid:8,plan_a,service_a+b) */
		array('test' => array('test_number' => 5, "aid" => 7, 'function' => array('basicCompare', 'linesVSbillrun', 'rounded', 'passthrough'), 'options' => array("stamp" => "201805", "force_accounts" => array(7))),
			'expected' => array('billrun' => array('invoice_id' => 103, 'billrun_key' => '201805', 'aid' => 7))),
		/* Account with single subscriber with plan + service + usages (aid:9,sid:10,plan_a,service_a,usaget call) */
		array('test' => array('test_number' => 6, "aid" => 9, 'function' => array('basicCompare', 'linesVSbillrun', 'rounded', 'passthrough'), 'options' => array("force_accounts" => array(9))),
			'expected' => array('billrun' => array('invoice_id' => 104, 'billrun_key' => '201806', 'aid' => 9))),
		/* Account with two subscribes with another plans(aid:11 ,sids :12 plan_a,14 plan_b) */
		array('test' => array('test_number' => 7, "aid" => 11, 'sid' => array(12, 14), 'function' => array('basicCompare', 'sumSids', 'subsPrice', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201805", "force_accounts" => array(11))),
			'expected' => array('billrun' => array('invoice_id' => 105, 'billrun_key' => '201805', 'aid' => 11, 'after_vat' => array("12" => 105.3, "14" => 117)))),
		/* Account with two subscribes 1st from begin the cycle and the 2nd from mid-cycle(for   no prorated plan)(aid:13 ,sids :15,16 plan_b(from 01/15/18 & 15/05/18)) */
		array('test' => array('test_number' => 8, "aid" => 13, 'sid' => array(15, 16), 'function' => array('basicCompare', 'sumSids', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201806", "force_accounts" => array(13))),
			'expected' => array('billrun' => array('invoice_id' => 106, 'billrun_key' => '201806', 'aid' => 13, 'after_vat' => array("15" => 117, "16" => 117), 'total' => 234, 'vatable' => 200, 'vat' => 17),
				'line' => array('types' => array('flat'))),
			'postRun' => array('saveId'),
		),
		/* Move subscriber between account(sid:20 ,first:aid:19,lest aid 21)(from 01/15/18 & 15/05/18) */
		/* part a */
		array('test' => array('test_number' => 9, "aid" => 19, 'sid' => 20, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("force_accounts" => array(19))),
			'expected' => array('billrun' => array('invoice_id' => 107, 'billrun_key' => '201806', 'aid' => 19, 'after_vat' => array("20" => 47.554838711), 'total' => 47.554838711, 'vatable' => 40.64516129032258, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))),
			'postRun' => array('saveId'),
		),
		/* part b */
		array('test' => array('test_number' => 10, "aid" => 21, 'sid' => 20, 'function' => array('checkForeignFileds', 'basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'checkForeignFileds' => ['discount' => ["foreign.discount.description" => 'ttt']], 'options' => array("stamp" => "201806", "force_accounts" => array(21))),
			'expected' => array('billrun' => array('invoice_id' => 108, 'billrun_key' => '201806', 'aid' => 21, 'after_vat' => array("21" => 57.745161289), 'total' => 57.745161289, 'vatable' => 49.354838709677416, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))),
		),
		/* Account with subscriber who closed on mid-cycle(aid:25,sid:26,plan_f end on 15/05/18) */
		array('test' => array('test_number' => 11, "aid" => 25, 'sid' => 26, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201806", "force_accounts" => array(25))),
			'expected' => array('billrun' => array('invoice_id' => 109, 'billrun_key' => '201806', 'aid' => 25, 'after_vat' => array("26" => 52.83870967741935), 'total' => 52.83870967741935, 'vatable' => 45.16129032258064, 'vat' => 17),
				'line' => array('types' => array('flat'))),
		),
		/* Account with two subscribers 1 closed in mid-cycle(aid 27 ,sids 28 29 ,29 should closed on 15/05/18) */
		array('test' => array('test_number' => 12, "aid" => 27, 'sid' => array(28, 29), 'function' => array('basicCompare', 'sumSids', 'subsPrice', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201806", "force_accounts" => array(27))),
			'expected' => array('billrun' => array('invoice_id' => 110, 'billrun_key' => '201806', 'aid' => 27, 'after_vat' => array("28" => 117, "29" => 52.8387096774193), 'total' => 169.838709677, 'vatable' => 145.1612903225806, 'vat' => 17),
				'line' => array('types' => array('flat'))),
			'postRun' => array('saveId'),
		),
		/* Subscriber with plan with  service included (aid:30 ,sid:31,plan_d included service_a) */
		/* by default, the included service's cost is not overridden with zero. */
		array('test' => array('test_number' => 13, "aid" => 30, 'sid' => 31, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded', 'passthrough'), 'options' => array("stamp" => "201806", "force_accounts" => array(30))),
			'expected' => array('billrun' => array('invoice_id' => 111, 'billrun_key' => '201806', 'aid' => 30, 'after_vat' => array("31" => 234), 'total' => 234, 'vatable' => 200, 'vat' => 17),
				'line' => array('types' => array('flat', 'service'))),
			'postRun' => array('saveId'),
		),
		/* Move subscriber between accounts(sid:33 first aid 32(01/5) second aid 34()15/5) */
		/* part a */
		array('test' => array('test_number' => 14, "aid" => 32, 'sid' => 33, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("force_accounts" => array(32))),
			'expected' => array('billrun' => array('invoice_id' => 112, 'billrun_key' => '201806', 'aid' => 32, 'after_vat' => array("33" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
				'line' => array('types' => array('flat'))),
		),
		/* part b */
		array('test' => array('test_number' => 15, "aid" => 34, 'sid' => 33, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201806", "force_accounts" => array(34))),
			'expected' => array('billrun' => array('invoice_id' => 113, 'billrun_key' => '201806', 'aid' => 34, 'after_vat' => array("33" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
				'line' => array('types' => array('flat'))),
			'postRun' => array('saveId'),
		),
		/* Subscriber with a service for : month / year / days / week ,
		  For each  run 3 cycles :1st before, 2nd cycle with the service, 3rd cycle after ,
		  (aid 35 day : 36 , year : 37, month :38,week:39)
		 */
		array('test' => array('test_number' => 16, "aid" => 35, 'sid' => array(36, 37, 38, 39), 'function' => array('basicCompare', 'sumSids', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201806", "force_accounts" => array(35))),
			'expected' => array('billrun' => array('invoice_id' => 114, 'billrun_key' => '201806', 'aid' => 35, 'after_vat' => array("36" => 128.7, "37" => 234, "38" => 128.7, "39" => 128.7), 'total' => 620.1, 'vatable' => 530, 'vat' => 17),
				'line' => array('types' => array('flat', 'service'))),
			'postRun' => array('saveId'),
		),
		array('test' => array('test_number' => 17, "aid" => 35, 'sid' => array(36, 37, 38, 39), 'function' => array('basicCompare', 'sumSids', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201807", "force_accounts" => array(35))),
			'expected' => array('billrun' => array('invoice_id' => 115, 'billrun_key' => '201807', 'aid' => 35, 'after_vat' => array("36" => 117, "37" => 117, "38" => 117, "39" => 117), 'total' => 468, 'vatable' => 400, 'vat' => 17),
				'line' => array('types' => array('flat'))),
		),
		/* Subscriber with service included with group for product X + service custom for 1 month from 15/5 to next 15/6 with same group:
		  A: usages before 15/5
		  B: usages between 15/5 to 30/5
		  C: usages  between 01/6 to 15/6
		  D: usages after 15/6
		  (aid :40 ,sid : 41 , plan_e included service call group call ,service custom for 1 month : custom_call between 15/5 - 15/6)
		 */
		array('test' => array('test_number' => 18, "aid" => 40, 'sid' => 41, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201806", "force_accounts" => array(40))),
			'expected' => array('billrun' => array('invoice_id' => 116, 'billrun_key' => '201806', 'aid' => 40, 'after_vat' => array("41" => 351), 'total' => 351, 'vatable' => 300, 'vat' => 17),
				'line' => array('types' => array('flat', 'service'))),
			'postRun' => array('saveId'),
		),
		/* Subscriber with service for few days in the cycle(aid:42 ,sid 43 ,plan_b , service_a,from 10/5) */
		array('test' => array('test_number' => 19, "aid" => 42, 'sid' => 43, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201806", "force_accounts" => array(42))),
			'expected' => array('billrun' => array('invoice_id' => 117, 'billrun_key' => '201806', 'aid' => 42, 'after_vat' => array("43" => 234), 'total' => 234, 'vatable' => 200, 'vat' => 17),
				'line' => array('types' => array('flat', 'service'))),
			'postRun' => array('saveId'),
		),
		/* no account exists */
		array(
			'test' => array('test_number' => 20, "aid" => 88, 'sid' => 89, 'function' => array('billrunNotCreated'), 'options' => array("stamp" => "201806", "force_accounts" => array(88))),
			'expected' => array('billrun' => array('invoice_id' => 102, 'billrun_key' => '201806', 'aid' => 89),
				'line' => array()),
		),
		/* force_accounts shouldn't recreate a confirmed invoice (check by the billrun _id?)(aid:50 ,sid:51)
		 * part 1
		 *  */
//         array(
//             'preRun' => array('changeConfig'),
//             'test' => array('test_number' => 24, "aid" => 60, 'sid' => 61, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'overrideConfig' => array('key' => 'billrun.charging_day.v', 'value' => 5), 'options' => array("stamp" => "201810", "force_accounts" => array(60))),
//             'expected' => array('billrun' => array('invoice_id' => 125, 'billrun_key' => '201810', 'aid' => 60, 'after_vat' => array("61" => 3.7741935483870965), 'total' => 3.7741935483870965, 'vatable' => 3.225806451612903, 'vat' => 17),
//                 'line' => array('types' => array('flat'))),
//         ),
//         array(
//             'preRun' => array('changeConfig', 'loadConfig'),
//             'test' => array('test_number' => 25, "aid" => 3, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'overrideConfig' => array('key' => 'billrun.charging_day.v', 'value' => 1), 'options' => array("stamp" => "201805", "force_accounts" => array(3))),
//             'expected' => array('billrun' => array('invoice_id' => 101, 'billrun_key' => '201805', 'aid' => 3, 'after_vat' => array("4" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
//                 'line' => array('types' => array('flat', 'credit'), 'final_charge' => (-10))),
//         ),
		/*   Subscriber with some units (aid:62,sid:63 ,service:iphonex) */
		array(
			'test' => array('test_number' => 28, "aid" => 62, 'sid' => 63, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201807", "force_accounts" => array(62))),
			'expected' => array('billrun' => array('invoice_id' => 125, 'billrun_key' => '201807', 'aid' => 62, 'after_vat' => array("63" => 2457), 'total' => 2457, 'vatable' => 2100, 'vat' => 17),
				'line' => array('types' => array('flat', 'service'))),
		),
		array(
			'test' => array('test_number' => 29, "aid" => 62, 'sid' => 63, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201808", "force_accounts" => array(62))),
			'expected' => array('billrun' => array('invoice_id' => 126, 'billrun_key' => '201808', 'aid' => 62, 'after_vat' => array("63" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
				'line' => array('types' => array('flat'))),
		),
		/* Check charge of a subscriber that reopened the same plan in the same cycle */
		array(
			'test' => array('test_number' => 30, "aid" => 64, 'sid' => 65, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201807", "force_accounts" => array(64))),
			'expected' => array('billrun' => array('invoice_id' => 127, 'billrun_key' => '201807', 'aid' => 64, 'after_vat' => array("65" => 89.7), 'total' => 89.7, 'vatable' => 76.666666667, 'vat' => 17),
				'line' => array('types' => array('flat'))),
		),
//		/* force_accounts overrides invoice ids when forcing 10 accounts at once */
		array(/* 13, 21, 27, 30, 34, 35, 40, 50, 52,58 */
			'test' => array('test_number' => 31, "aid" => 0, 'sid' => 0, 'function' => array('overrides_invoice'), 'options' => array("stamp" => "201806", "force_accounts" => array(52, 27, 30, 13, 19, 40, 35, 50, 42, 34))),
			'expected' => array(),
		),
		/* cdr non vatable and cradit not vatable */
		array(
			'test' => array('test_number' => 32, "aid" => 1, 'sid' => 2, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201807", "force_accounts" => array(1))),
			'expected' => array('billrun' => array('invoice_id' => 128, 'billrun_key' => '201807', 'aid' => 1, 'after_vat' => array("2" => 307), 'total' => 307, 'vatable' => 100, 'vat' => 17),
				'line' => array('types' => array('flat', 'non', 'credit', 'service'))),
		),
		//attributes
		/* Take last account_name for billrun */
		array(
			'test' => array('test_number' => 33, "aid" => 66, 'sid' => 67, 'function' => array('takeLastRevision'), 'options' => array("stamp" => "201810", "force_accounts" => array(66))),
			'expected' => array('billrun' => array('firstname' => 'yossiB'),)
		),
		/* 	vat 0 
		 * Currently vat change is not supported
		 *  */
////		array(
////			'preRun' => array('changeConfig',),
////			'test' => array('test_number' => 17, "aid" => 48, 'sid' => 49, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'overrideConfig' => array('key' => 'taxation.vat.v', 'value' => 0), 'options' => array("stamp" => "201809", "force_accounts" => array(48))),
////			'expected' => array('billrun' => array('invoice_id' => 123, 'billrun_key' => '201809', 'aid' => 48, 'after_vat' => array("49" => 100), 'total' => 100, 'vatable' => 100, 'vat' => 0),
////				'line' => array(
//			"discount_subject": {
//				"plan": {
//					"PLAN_A": 0.1
//				}'types' => array('flat')),
////			)),'expected_invoice'
		//BRCD-1708
		array(
			'test' => array('test_number' => 34, "aid" => 70, 'sid' => 71, 'function' => array('totalsPrice'), 'options' => array("stamp" => "201901", "force_accounts" => array(70))),
			'expected' => array('billrun' => array('billrun_key' => '201901', 'aid' => 70, 'after_vat' => array("71" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),)
		),
		//BRCD-1725
		array('test' => array('test_number' => 35, "aid" => 73, 'sid' => 74, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201901", "force_accounts" => array(73))),
			'expected' => array('billrun' => array('invoice_id' => 131, 'billrun_key' => '201901', 'aid' => 73, 'after_vat' => array("74" => 117))),
			'line' => array('types' => array('flat')),
			'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1725'
		),
		/* (not included in plan) service limited to X cycles - verify no charge / line since the X+1 cycle  + check BRCD-1730 */
		array('test' => array('test_number' => 36, "aid" => 75, 'sid' => 76, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201807", "force_accounts" => array(75))),
			'expected' => array('billrun' => array('invoice_id' => 132, 'billrun_key' => '201807', 'aid' => 75, 'after_vat' => array("76" => 234), 'total' => 234, 'vatable' => 200, 'vat' => 17),
				'line' => array('types' => array('flat', 'service'))),
			'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1725'
		),
		array('test' => array('test_number' => 37, "aid" => 75, 'sid' => 76, 'function' => array('planExist', 'basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201808", "force_accounts" => array(75))),
			'expected' => array('billrun' => array('invoice_id' => 133, 'billrun_key' => '201808', 'aid' => 75, 'after_vat' => array("76" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
				'line' => array('types' => array('flat',))),
			'postRun' => array('saveId'),
			'jiraLink' => array('https://billrun.atlassian.net/browse/BRCD-1725',
				'https://billrun.atlassian.net/browse/BRCD-1730'
			)
		),
		array('test' => array('test_number' => 38, "aid" => 79, 'sid' => 78, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201903", "force_accounts" => array(79))),
			'expected' => array('billrun' => array('invoice_id' => 134, 'billrun_key' => '201903', 'aid' => 79, 'after_vat' => array("78" => 117))),
			'line' => array('types' => array('flat', 'service')),
			'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1758'
		),
		//check prorated service - subscribers start in 05/03/19 with a prorated service need to pay for only 27 days
		array('test' => array('test_number' => 39, "aid" => 80, 'sid' => 81, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201904", "force_accounts" => array(80))),
			'expected' => array('billrun' => array('invoice_id' => 135, 'billrun_key' => '201904', 'aid' => 80, 'after_vat' => array("81" => 101.903225))),
			'line' => array('types' => array('flat', 'service')),
		),
		//check prorated service - subscribers end in 05/03/19 with a prorated service need to pay for only 5 days
		array('test' => array('test_number' => 40, "aid" => 82, 'sid' => 83, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "201904", "force_accounts" => array(82))),
			'expected' => array('billrun' => array('invoice_id' => 136, 'billrun_key' => '201904', 'aid' => 82, 'after_vat' => array("83" => 18.870967742))),
			'line' => array('types' => array('flat', 'service')),
		),
		//subscriber with duplicate service with diffrent invoice_id
		array('test' => array('test_number' => 41, "aid" => 26, 'function' => array('basicCompare', 'linesVSbillrun', 'lineExists', 'rounded', 'passthrough', 'totalsPrice'), 'options' => array("stamp" => "202003",
					"force_accounts" => array(26))),
			'expected' => array('billrun' => array('invoice_id' => 137, 'billrun_key' => '202003', 'aid' => 26, 'after_vat' => array("100" => 245.7), 'total' => 245.7, 'vatable' => 210, 'vat' => 17)),
			'line' => array('types' => array('flat', 'service')),
			'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1493",
		),
		/*
		 * exemple :plan  TTT means:
		  "prorated_start" : True,
		  "prorated_end" : True,
		  "prorated_termination" : True,

		 * 200-203=TTF,300-303=FFF,400-403=TFF,500-503=FTT,600-603=TTT,700-703=FFT
		 * 200/300 etc = 0,201/301 etc = 1 ...
		 * aid = sid+1000
		 * sids end with :0 = full cycle ,1 = from mid month to infinity, 2 = from + to mid cycle, 3 reopen
		 * 0 */
		array('test' => array('test_number' => 42, "aid" => 1200, 'sid' => 200, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1200))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1200, 'after_vat' => array("200" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 43, "aid" => 1300, 'sid' => 300, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1300))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1300, 'after_vat' => array("300" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 44, "aid" => 1400, 'sid' => 400, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1400))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1400, 'after_vat' => array("400" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 45, "aid" => 1500, 'sid' => 500, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1500))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1500, 'after_vat' => array("500" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 46, "aid" => 1600, 'sid' => 600, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1600))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1600, 'after_vat' => array("600" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 47, "aid" => 1700, 'sid' => 700, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1700))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1700, 'after_vat' => array("700" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		/* 1 */
		array('test' => array('test_number' => 48, "aid" => 1201, 'sid' => 201, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1201))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1201, 'after_vat' => array("201" => 74.72903225805), 'total' => 74.72903225805, 'vatable' => 63.8709677424, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 49, "aid" => 1301, 'sid' => 301, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1301))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1301, 'after_vat' => array("301" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 50, "aid" => 1401, 'sid' => 401, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1401))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1401, 'after_vat' => array("401" => 74.72903225805), 'total' => 74.72903225805, 'vatable' => 63.8709677424, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 51, "aid" => 1501, 'sid' => 501, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1501))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1501, 'after_vat' => array("501" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 52, "aid" => 1601, 'sid' => 601, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1601))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1601, 'after_vat' => array("601" => 74.72903225805), 'total' => 74.72903225805, 'vatable' => 63.8709677424, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 53, "aid" => 1701, 'sid' => 701, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1701))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1701, 'after_vat' => array("701" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		/* 2 */
		array('test' => array('test_number' => 54, "aid" => 1202, 'sid' => 202, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1202))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1202, 'after_vat' => array("202" => 74.729033), 'total' => 74.729033, 'vatable' => 63.8709668, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 55, "aid" => 1302, 'sid' => 302, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1302))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1302, 'after_vat' => array("302" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 56, "aid" => 1402, 'sid' => 402, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1402))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1402, 'after_vat' => array("402" => 75.106451612903), 'total' => 75.106451612903, 'vatable' => 64.19354838709, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		//start timezone GMT+2 ent timezone GMT +3
		array('test' => array('test_number' => 57, "aid" => 1502, 'sid' => 502, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1502))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1502, 'after_vat' => array("502" => 98.50645), 'total' => 98.50645, 'vatable' => 84.193548, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 58, "aid" => 1602, 'sid' => 602, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1602))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1602, 'after_vat' => array("602" => 37.36451703), 'total' => 37.36451703, 'vatable' => 31.935483864, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 59, "aid" => 1702, 'sid' => 702, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1702))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1702, 'after_vat' => array("702" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		/* 3 */
		array('test' => array('test_number' => 60, "aid" => 1203, 'sid' => 203, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1203))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1203, 'after_vat' => array("203" => 40.76129119), 'total' => 40.76129119, 'vatable' => 34.83870926, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 61, "aid" => 1303, 'sid' => 303, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1303))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1303, 'after_vat' => array("303" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 62, "aid" => 1403, 'sid' => 403, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1403))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1403, 'after_vat' => array("403" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 63, "aid" => 1503, 'sid' => 503, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1503))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1503, 'after_vat' => array("503" => 75.1064516), 'total' => 75.1064516, 'vatable' => 64.1935483, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 64, "aid" => 1603, 'sid' => 603, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1603))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1603, 'after_vat' => array("603" => 40.76129119), 'total' => 40.76129119, 'vatable' => 34.83870926, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		array('test' => array('test_number' => 65, "aid" => 1703, 'sid' => 703, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202004", "force_accounts" => array(1703))),
			'expected' => array('billrun' => array('billrun_key' => '202004', 'aid' => 1703, 'after_vat' => array("703" => 105.3), 'total' => 105.3, 'vatable' => 90, 'vat' => 17),
				'line' => array('types' => array('flat', 'credit'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-1913",
		),
		//check that the subscriber isn't charge about one more day in case he subscribr between 1/7/2020 - 30/07/2020
		array('test' => array('test_number' => 66, "aid" => 187501, 'sid' => 187500, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202008", "force_accounts" => array(187501))),
			'expected' => array('billrun' => array('billrun_key' => '202008', 'aid' => 187501, 'after_vat' => array("187500" => 113.22580645161288), 'total' => 113.22580645161288, 'vatable' => 96.77419354838709, 'vat' => 17),
				'line' => array('types' => array('flat'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-2742",
		),
		array('test' => array('test_number' => 68, "aid" => 1770, 'sid' => 1771, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202005", "force_accounts" => array(1770))),
			'expected' => array('billrun' => array('billrun_key' => '202005', 'aid' => 1770, 'after_vat' => array("1771" => 200), 'total' => 200, 'vatable' => 200, 'vat' => 0)),
			'line' => array('types' => array('flat', 'service')), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-2492"
		),
		array(
			'preRun' => ('expected_invoice'),
			'test' => array('test_number' => 67,),
			'expected' => array(),
		),
		/* run full cycle */
		array(
			'preRun' => ('changeConfig'),
			'test' => array('test_number' => 67, 'aid' => 0, 'function' => array('fullCycle'), 'overrideConfig' => array('key' => 'billrun.charging_day.v', 'value' => 1), 'options' => array("stamp" => "201806", "page" => 0, "size" => 10000000,)),
			'expected' => array(),
		)
	);

	public function __construct($label = false) {
		parent::__construct("test Aggregatore");
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->servicesCol = Billrun_Factory::db()->servicesCollection();
		$this->discountsCol = Billrun_Factory::db()->discountsCollection();
		$this->subscribersCol = Billrun_Factory::db()->subscribersCollection();
		$this->balancesCol = Billrun_Factory::db()->discountsCollection();
		$this->billrunCol = Billrun_Factory::db()->billrunCollection();
		$this->construct(basename(__FILE__, '.php'), ['bills', 'billing_cycle', 'billrun', 'counters', 'discounts', 'taxes']);
		$this->setColletions();
		$this->loadDbConfig();
	}

	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}

	/**
	 * 
	 * @param array $row current test case
	 */
	public function aggregator($row) {
		$options = array_merge($this->defaultOptions, $row['test']['options']);
		$aggregator = Billrun_Aggregator::getInstance($options);
		$aggregator->load();
		$aggregator->aggregate();
	}

	/**
	 * 
	 * @param $query
	 * @return billrun objects by query or all if query is null
	 */
	public function getBillruns($query = null) {
		return $this->billrunCol->query($query)->cursor();
	}

	/**
	 * the function is runing all the test cases  
	 * print the test result
	 * and restore the original data 
	 */
	public function TestPerform() {

		foreach ($this->tests as $key => $row) {

			$aid = $row['test']['aid'];
			$this->message .= "<span id={$row['test']['test_number']}>test number : " . $row['test']['test_number'] . '</span><br>';
			// run fenctions before the test begin 
			if (isset($row['preRun']) && !empty($row['preRun'])) {
				$preRun = $row['preRun'];
				if (!is_array($preRun)) {
					$preRun = array($row['preRun']);
				}
				foreach ($preRun as $pre) {
					$this->$pre($key, $row);
				}
			}
			// run aggregator
			if (array_key_exists('aid', $row['test'])) {
				$returnBillrun = $this->runT($row);
			}
			//run tests functios 
			if (isset($row['test']['function'])) {
				$function = $row['test']['function'];
				if (!is_array($function)) {
					$function = array($row['test']['function']);
				}
				foreach ($function as $func) {
					$testFail = $this->assertTrue($this->$func($key, $returnBillrun, $row));
					if (!$testFail) {
						$this->fails .= "|---|<a href='#{$row['test']['test_number']}'>{$row['test']['test_number']}</a>";
					}
				}
			}
			$this->saveLatestResults($returnBillrun, $row);
			$post = (isset($row['postRun']) && !empty($row['postRun'])) ? $row['postRun'] : null;

			// run functions after the test run 
			if (!is_array($post) && isset($post)) {
				$post = array($row['postRun']);
			}
			if (!is_null($post)) {
				foreach ($post as $func) {
					$this->$func($returnBillrun, $row);
				}
			}
			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
		}
		if ($this->fails) {
			$this->message .= $this->fails;
		}
		print_r($this->message);
		 $this->restoreColletions();
	}

	/**
	 * run aggregation on current test case and return its billrun object/s
	 * @param array $row current test case 
	 * @return  Mongodloid_Entity|array $entityAfter billrun object/s 
	 */
	protected function runT($row) {
		$id = isset($row['test']['aid']) ? $row['test']['aid'] : 0;
		$billrun = (isset($row['test']['options']['stamp'])) ? $row['test']['options']['stamp'] : $this->defaultOptions['stamp'];
		$this->aggregator($row);
		$query = array('aid' => $id, "billrun_key" => $billrun);
		$entityAfter = $this->getBillruns($query)->current();
		return ($entityAfter);
	}

	/**
	 * 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case 
	 * @return boolean true if the test is pass and false if the tast is fail 
	 */
	protected function basicCompare($key, $returnBillrun, $row) {
		$passed = TRUE;
		$billrun_key = $row['expected']['billrun']['billrun_key'];
		$aid = $row['expected']['billrun']['aid'];
		$retun_billrun_key = isset($returnBillrun['billrun_key']) ? $returnBillrun['billrun_key'] : false;
		$retun_aid = isset($returnBillrun['aid']) ? $returnBillrun['aid'] : false;
		$jiraLink = isset($row['jiraLink']) ? (array) $row['jiraLink'] : '';
		foreach ($jiraLink as $link) {
			$this->message .= '<br><a target="_blank" href=' . "'" . $link . "'>issus in jira :" . $link . "</a>";
		}
		$this->message .= '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . '<b> Expected: </b><br> ' . '— aid : ' . $aid . '<br> — billrun_key: ' . $billrun_key;
		$this->message .= '<br><b> Result: </b> <br>';
		if (!empty($retun_billrun_key) && $retun_billrun_key == $billrun_key) {
			$this->message .= 'billrun_key :' . $retun_billrun_key . $this->pass;
		} else {
			$passed = false;
			$this->message .= 'billrun_key :' . $retun_billrun_key . $this->fail;
		}
		if (!empty($retun_aid) && $retun_aid == $aid) {
			$this->message .= 'aid :' . $retun_aid . $this->pass;
		} else {
			$passed = false;
			$this->message .= 'aid :' . $retun_aid . $this->fail;
		}
		return $passed;
	}

	public function checkInvoiceId($key, $returnBillrun, $row) {
		$passed = TRUE;
		$invoice_id = $row['expected']['billrun']['invoice_id'] ? $row['expected']['billrun']['invoice_id'] : null;
		$retun_invoice_id = $returnBillrun['invoice_id'] ? $returnBillrun['invoice_id'] : false;
		if (isset($invoice_id)) {

			if (!empty($retun_invoice_id) && $retun_invoice_id == $invoice_id) {
				$this->message .= 'invoice_id :' . $retun_invoice_id . $this->pass;
			} else {
				$passed = false;
				'<br> — invoice_id: ' . $invoice_id .
					$this->message .= 'invoice_id expected to be : ' . $invoice_id . ' result is ' . $retun_invoice_id . $this->fail;
			}
		}
	}

	/**
	 * check if all subscribers was calculeted
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function sumSids($key, $returnBillrun, $row) {
		$this->message .= "<b> sum sid's :</b> <br>";
		if (count($row['test']['sid']) == count($returnBillrun['subs']) - 1) {
			$this->message .= "subs equle to sum of sid's" . $this->pass;
			return true;
		} else {
			$this->message .= "subs isn't equle to sum of sid's" . $this->fail;
			return FALSE;
		}
	}

	/**
	 *  check the price before and after vat
	 * 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function totalsPrice($key, $returnBillrun, $row) {
		$passed = TRUE;
		$this->message .= "<b> total Price :</b> <br>";
		if (Billrun_Util::isEqual($returnBillrun['totals']['after_vat'], $row['expected']['billrun']['total'], 0.00001)) {
			$this->message .= "total after vat is : " . $returnBillrun['totals']['after_vat'] . $this->pass;
		} else {
			$this->message .= "expected total after vat is : {$row['expected']['billrun']['total']} <b>result is </b>: {$returnBillrun['totals']['after_vat']}" . $this->fail;
			$passed = FALSE;
		}
		$vatable = (isset($row['expected']['billrun']['vatable']) ) ? $row['expected']['billrun']['vatable'] : null;
		if ($vatable <> 0) {
			$vat = $this->calcVat($returnBillrun['totals']['before_vat'], $returnBillrun['totals']['after_vat'], $vatable);
			if (Billrun_Util::isEqual($vat, $row['expected']['billrun']['vat'], 0.00001)) {
				$this->message .= "total befor vat is : " . $returnBillrun['totals']['before_vat'] . $this->pass;
			} else {
				$this->message .= "expected total befor vat is : {$row['expected']['billrun'] ['vatable']} <b>result is </b>:  {$returnBillrun['totals']['before_vat']}" . $this->fail;
				$passed = FALSE; /* Percentage of tax */
			}
			$this->message .= "Percentage of tax :$vat %<br>";
		}
		return $passed;
	}

	/* return the percent of the vat */

	/**
	 * 
	 * @param $beforVat
	 * @param $aftetrVat
	 * @param $vatable
	 * @return vat
	 */
	public function calcVat($beforVat, $aftetrVat, $vatable = null) {
		$i = $aftetrVat - $beforVat;
		if (!empty($vatable)) {
			return ($i / $vatable) * 100;
		} else {
			return ($i / $beforVat) * 100;
		}
	}

	/* save Latest 3 Results  */

	/**
	 * 
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation is the billrun object of current test after aggregation
	 * @param array $row current test case
	 */
	public function saveLatestResults($returnBillrun, $row) {
		$lest = array($returnBillrun, $row);
		if (!empty($this->LatestResults[0])) {
			if (!empty($this->LatestResults[1])) {
				$this->LatestResults[2] = $this->LatestResults[1];
				$this->LatestResults[1] = $this->LatestResults[0];
			} else {
				$this->LatestResults[1] = $this->LatestResults[0];
			}
		}
		$this->LatestResults[0] = $lest;
	}

	/**
	 * 
	 * @param array $row current test case current test case
	 * @return array $alllines return all lines  of aid in specific billrun_key 
	 */
	public function getLines($row) {
		$stamp = (isset($row['test']['options']['stamp'])) ? $row['test']['options']['stamp'] : $this->defaultOptions['stamp'];
		$query = array('billrun' => $stamp, 'aid' => $row['test']['aid']);
		$allLines = [];
		$linesCollection = Billrun_Factory::db()->linesCollection();
		$lines = $linesCollection->query($query)->cursor();
		foreach ($lines as $line) {
			$allLines[] = $line->getRawData();
		}
		return $allLines;
	}
        	/**
	 *  check if  Foreign Fileds create correctly
	 * 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function checkForeignFileds($key, $returnBillrun, $row) {
		$passed = TRUE;
		$this->message .= "<b> Foreign Fileds :</b> <br>";
		$entitys = $row['test']['checkForeignFileds'];
		$lines = $this->getLines($row);
		foreach ($row['test']['checkForeignFileds'] as $key => $val) {
			$entitys[] = $key;
			if ($key == 'discount') {//TODO  Add compatibility to all usage types
				$line = array_filter($lines, function ($line) {
					if ($line['usaget'] == 'discount') {
						return $line;
					}
				});
				foreach ($val as $path => $value) {
					foreach ($line as $l) {
						if ($lineValue = Billrun_Util::getIn($l, $path)) {
							if ($lineValue == $value) {
								$this->message .= "Foreign Fileds exists  ,</br> path : ". $path. "</br>value : " .$value .$this->pass;
							}
						} else {
							$this->message .= "Foreign Fileds not created ,</br> path : ". $path. "</br>value : " .$value .$this->fail;
							$passed = FALSE; 
						}
					}
				}
			}
		}
		return $passed;
	}

	/**
	 * check if all the lines was created 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function lineExists($key, $returnBillrun, $row) {
		$passed = true;
		$this->message .= "<b> create lines: </b> <br>";
		$types = $row['expected']['line']['types'];
		$lines = $this->getLines($row);
		$returnTypes = [];
		foreach ($lines as $line) {
			$returnTypes[] = $line['type'];
		}
		$diff = array_diff($types, $returnTypes);
		if (!empty($diff)) {
			$passed = FALSE;
			$this->message .= "these lines aren't created : ";
			foreach ($diff as $dif) {
				$this->message .= $dif . '<br>';
			}
			$this->message .= $this->fail;
		} elseif (empty($diff) && empty($returnTypes) && !empty($row['expected']['line'])) {
			$this->message .= "no lines created" . $this->fail;
			$passed = FALSE;
		} elseif (empty($diff) && empty($returnTypes) && empty($row['expected']['line'])) {
			/* its for function billrunNotCreated */
		} else {
			$this->message .= "all lines created" . $this->pass;
		}
		$this->numOffLines = count($returnTypes);
		return $passed;
	}

	/**
	 * 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean return pass if the billrun was not created
	 */
	public function billrunNotCreated($key, $returnBillrun = null, $row) {
		$passed = true;
		$this->lineExists($key, $returnBillrun = null, $row);
		if ($this->numOffLines > 0) {
			$passed = false;
			$this->message .= "lines was created for account {$row['test']['aid']} But they should not have been formed" . $this->fail;
		} else {
			$this->message .= "lines wasn't created for account {$row['test']['aid']} Because they should not have been created" . $this->pass;
		}
		return $passed;
	}

	/**
	 * change and reload Config 
	 * @param int $key number of the test case
	 * @param array $row current test case
	 */
	public function changeConfig($key, $row) {
		$key = $row['test']['overrideConfig']['key'];
		$value = $row['test']['overrideConfig']['value'];
		$data = $this->loadConfig();
		$this->changeConfigKey($data, $key, $value);
		$this->loadDbConfig();
	}

	/**
	 * check if created duplicate billruns
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function duplicateAccounts($key, $returnBillrun, $row) {
		$this->message .= "<b>duplicate billruns: </b> <br>";
		$passed = true;
		$query = array(array('aid' => $row['test']['aid'], "billrun_key" => $row['test']['options']['stamp']));
		$sumBllruns = $this->getBillruns($query)->count();
		if ($sumBllruns > 1) {
			$this->message .= "created duplicate billruns" . $this->fail;
			$passed = false;
		} else {
			$this->message .= "no duplicate billruns" . $this->pass;
		}
		return $passed;
	}

	/**
	 * confirm specific invoice
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation
	 * @param array $row current test case
	 */
	public function confirm($returnBillrun, $row) {
		$options['type'] = (string) 'billrunToBill';
		$options['stamp'] = (string) $row['test']['options']['stamp'];
		$options['invoices'] = (string) $returnBillrun['invoice_id'];
		$generator = Billrun_Generator::getInstance($options);
		$generator->load();
		$generator->generate();
	}

	/**
	 * check after_vat per sid 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function subsPrice($key, $returnBillrun, $row) {
		$passed = true;
		$this->message .= "<b> price per sid :</b> <br>";
		$invalidSubs = array();
		foreach ($returnBillrun['subs'] as $sub) {
			if (!Billrun_Util::isEqual($sub['totals']['after_vat'], $row['expected']['billrun']['after_vat'][$sub['sid']], 0.000001)) {
				$passed = false;
				$this->message .= "sid {$sub['sid']} has worng price ,<b>result</b> : {$sub['totals']['after_vat']} <b>expected</b> :{$row['expected']['billrun']['after_vat'][$sub['sid']]} " . $this->fail;
			}
		}
		if ($passed) {
			$this->message .= "all sids price are wel" . $this->pass;
		}
		return $passed;
	}

	/**
	 * General check for all tests - sum of account lines equals billrun object total
	 *  (aprice = before_vat, final_charge - after_vat)
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function linesVSbillrun($key, $returnBillrun, $row) {
		$this->message .= "<b> lines vs billrun :</b> <br>";
		$passed = true;
		$lines = $this->getLines($row);
		$final_charge = 0;
		$aprice = 0;
		$aftreVat = $returnBillrun['totals']["after_vat"];
		$beforeVat = $returnBillrun['totals']["before_vat"];

		foreach ($lines as $line) {
			if (isset($line['aprice'])) {
				$aprice += $line['aprice'];
			}
			if (isset($line['final_charge'])) {
				$final_charge += $line['final_charge'];
			}
		}

		if (Billrun_Util::isEqual($final_charge, $aftreVat, 0.000001)) {
			$this->message .= 'sum of "' . 'final_charge" equal to total.after_vat ' . $this->pass;
		} else {
			$passed = false;
			$this->message .= 'sum of "' . 'final_charge" <b>is not equal</b> to total.after_vat ' . $this->fail;
		}

		if (Billrun_Util::isEqual($aprice, $beforeVat, 0.000001)) {
			$this->message .= 'sum of "' . 'aprice" equal to total.before_vat ' . $this->pass;
		} else {
			$passed = false;
			$this->message .= 'sum of "' . 'aprice" <b>is not equal</b> to total.before_vat ' . $this->fail;
		}
		return $passed;
	}

	/**
	 * 'totals.after_vat_rounded' is rounding of 'totals.after_vat
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function rounded($key, $returnBillrun, $row) {
		$this->message .= "<b> rounding :</b> <br>";
		$passed = true;
		if (round($returnBillrun['totals']['after_vat_rounded'], 2) == round($returnBillrun['totals']['after_vat'], 2)) {
			$this->message .= "'totals.after_vat_rounded' is rounding of 'totals.after_vat' :" . $this->pass;
		} else {
			$this->message .= "'totals.after_vat_rounded' is<b>not</b>rounding of 'totals.after_vat' :</b>" . $this->fail;
			$passed = false;
		}
		return $passed;
	}

	/**
	 * remove billrun and lines for aid in speciphic cycle
	 * @param int $key number of the test case
	 * @param array $row current test case
	 */
	public function removeBillrun($key, $row) {
		$stamp = $row['test']['options']['stamp'];
		$account[] = $row['test']['aid'];
		Billrun_Aggregator_Customer::removeBeforeAggregate($stamp, $account);
	}

	/**
	 * check that billrun not run full cycle by checking if aid 54 is run
	 * @param int $key  
	 * @param array $row 
	 * 
	 */
	public function billrunExists($key, $row) {
		$aid = $row['test']['fake_aid'];
		$stamp = $row['test']['fake_stamp'];
		$query = array('aid' => $aid, "billrun_key" => $stamp);
		$billrun = $this->getBillruns($query)->count();
		if ($sumBillruns > 0) {
			$this->assertTrue(false);
			$this->message .= '<b style="color:red;">aggregate run full cycle</b>' . $this->fail;
		}
	}

	/**
	 * run full cycle number of the test case
	 * @param int $key
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function fullCycle($key, $returnBillrun, $row) {
		$passed = true;
		$aid = $row['test']['aid'];
		$stamp = $row['test']['options']['stamp'];
		$query = array('aid' => $aid, "billrun_key" => $stamp);
		$billrun = $this->getBillruns($query)->count();
		if (count($billrun) > 0) {
			$this->message .= '<b>aggregate run full cycle</b>' . $this->pass;
		} else {
			$passed = false;
			$this->message .= '<b>aggregate not run full cycle</b>' . $this->fail;
		}
		return $passed;
	}

	/**
	 * check the pagination
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function pagination($key, $returnBillrun, $row) {
		$passed = true;
		$billrun = $this->getBillruns()->count();
		if ($billrun > 0) {
			$passed = false;
			$this->message .= '<b style="color:red;">pagination fail</b>' . $this->fail;
		} else {
			$this->message .= '<b style="color:green;">pagination work well</b>' . $this->pass;
		}
		return $passed;
	}

	/**
	 * set charge_included_service to false
	 */
	public function charge_included_service($key, $row) {
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/charge_included_service.ini');
	}

	/**
	 *  set charge_included_service to true
	 */
	public function charge_not_included_service($key, $row) {
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/charge_not_included_service.ini');
	}

	/**
	 * check if invoice was created
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function invoice_exist($key, $returnBillrun, $row) {
		$this->message .= "<b> invoice exist :</b> <br>";
		$passed = true;
		$path = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('invoice_export.export', 'files/invoices/'));
		$path .= $row['test']['options']['stamp'] . '/pdf/' . $row['test']['invoice_path'];
		if (!file_exists($path)) {
			$passed = false;
			$this->message .= 'the invoice is not found' . $this->fail;
		} else {
			$this->message .= 'the invoice created' . $this->pass;
		}
		return $passed;
	}

	/**
	 * Check override mode using passthrough_fields 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function passthrough($key, $returnBillrun, $row) {
		$passed = true;
		$accounts = Billrun_Factory::account();
		$this->message .= "<b> passthrough_fields :</b> <br>";
		$account = $accounts->loadAccountForQuery((array('aid' => $row['test']['aid'])));
		$account = $accounts->getCustomerData();
		$address = $account['address'];
		if ($returnBillrun['attributes']['address'] === $address) {
			$this->message .= "passthrough work well" . $this->pass;
		} else {
			$this->message .= "passthrough fill" . $this->fail;
			$passed = false;
		}
		return $passed;
	}

	/**
	 *  save invoice_id 
	 *  @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 *  @param array $row current test case
	 */
	public function saveId($returnBillrun, $row) {
		if (!empty($returnBillrun)) {
			$this->ids[$returnBillrun['aid']] = $returnBillrun['invoice_id'];
		}
	}

	/**
	 * chack if reaggregation is overrides_invoice_id
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function overrides_invoice($key, $returnBillrun, $row) {
		$this->message .= "<b> overrides_invoice_id :</b> <br>";
		$passed = true;
		$fail = 0;
		$accounts = $row['test']['options']['force_accounts'];
		$query = array("aid" => array('$in' => $accounts), "billrun_key" => $row['test']['options']['stamp']);
		$allbillruns = $this->getBillruns($query);
		foreach ($allbillruns as $billrunse) {
			$returnBillruns[] = $billrunse->getRawData();
		}
		if (count($returnBillruns) > 10) {
			$passed = false;
			$this->message .= "aggregator wasn't overrides invoice id" . $this->fail;
		} elseif (count($returnBillruns) < 10) {
			$passed = false;
			$this->message .= "aggregator delete and not created  invoice " . $this->fail;
		} else {
			foreach ($returnBillruns as $bill) {
				if (isset($this->ids[$bill['aid']]) && $this->ids[$bill['aid']] !== $bill['invoice_id']) {
					$fail++;
				}
			}
			if ($fail) {
				$passed = false;
				$this->message .= "force account with 10 accounts cause to worng  override invoices id" . $this->fail;
			} else {
				$this->message .= "force account with 10 accounts work well and override the invoices id" . $this->pass;
			}
		}
		return $passed;
	}

	/**
	 * check if exepted invoice are created billrun object
	 * @param int $key number of the test case
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function expected_invoice($key, $row) {
		$this->message .= "<b> expected_invoice :</b> <br>";
		$passed = true;
		$billrunsBefore = $this->getBillruns()->count();
		$options = array(
			'type' => (string) 'expectedinvoice',
			'aid' => (string) 3,
			'stamp' => (string) '201808'
		);
		$generator = Billrun_Generator::getInstance($options);
		$generator->load();
		$generator->generate();
		$billrunsAfter = $this->getBillruns()->count();
		if ($billrunsAfter > $billrunsBefore) {
			$passed = false;
			$this->message .= "exepted invoice created billrun object" . $this->fail;
		} else {
			$this->message .= "exepted invoice wasn't created billrun object" . $this->pass;
		}
		return $passed;
	}

	/**
	 * When an account has multiple revisions in a specific billing cycle,
	 *  take the last one when generating the billrun object
	  (check subs.attributes.account_name field)
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function takeLastRevision($key, $returnBillrun, $row) {
		$this->message .= "<b> Take last account_name for billrun with many revisions  at a cycle:</b> <br>";
		$passed = true;
		$query = array('aid' => 66, "billrun_key" => '201810');
		$lastRvision = $this->getBillruns($query);
		foreach ($lastRvision as $last) {
			$lastR[] = $last->getRawData();
		}
		if ($lastR[0]['attributes']['firstname'] === $row['expected']['billrun']['firstname']) {
			$this->message .= "The latest revision of the subscriber was taken" . $this->pass;
		} else {
			$passed = false;
			$this->message .= "The version taken is not the last" . $this->fail;
		}
		return $passed;
	}

	/**
	 * check if 'plan' filed under sub in billrun object exists
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function planExist($key, $returnBillrun, $row) {
		$passed = true;
		$this->message .= "<br><b> plan filed  :</b> <br>";
		$sids = (array) $row['test']['sid'];
		foreach ($sids as $sid) {
			foreach ($returnBillrun['subs'] as $sub) {
				if ($sid == $sub['sid']) {
					if (!array_key_exists('plan', $sub)) {
						$this->message .= "plan filed does NOT exist in billrun object" . $this->fail;
						$passed = false;
					} else {
						$this->message .= "plan filed exists in billrun object" . $this->pass;
					}
				}
			}
		}

		return $passed;
	}

}
