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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Aggregator extends UnitTestCase
{

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
    public function test_cases()
    {


        return array(
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
            		//BRCD-2993 with discount 
            		array('test' => array('test_number' => 69, "aid" => 2000000784, 'sid' => 290, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202101", "force_accounts" => array(2000000784))),
            			'expected' => array('billrun' => array('billrun_key' => '202101', 'aid' => 2000000784, 'after_vat' => array("290" => 29.08097389230968, "0" => 1.9565442), 'total' => 30.339039414890326, 'vatable' => 25.93080291870968, 'vat' => 0)),
            			'line' => array('types' => array('flat', 'service', 'credit')), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-2993"
            		),
            		//BRCD-2993 without discount 
            		array('test' => array('test_number' => 70, "aid" => 2000000785, 'sid' => 291, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202101", "force_accounts" => array(2000000785))),
            			'expected' => array('billrun' => array('billrun_key' => '202101', 'aid' => 2000000785, 'after_vat' => array("291" => 7.586449083870966, "0" => 1.9565442), 'total' => 9.542993283870967, 'vatable' => 7.5594141935483865, 'vat' => 0)),
            			'line' => array('types' => array('flat', 'service', 'credit')), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-2993"
            		),
            		//
            		array('test' => array('test_number' => 71, "aid" => 17975, 'sid' => 17976, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202101", "force_accounts" => array(17975))),
            			'expected' => array('billrun' => array('billrun_key' => '202101', 'aid' => 17975, 'after_vat' => array("17976" => 160.0258064516129), 'total' => 160.0258064516129, 'vatable' => 136.7741935483871, 'vat' => 0)),
            			'line' => array('types' => array('flat', 'credit')), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-2988"
            		),
            		//case for BRCD-2996 (It seems that the 'expandSubRevisions'  will not fully support  multiple termination of services  under the same  revision.)
            		array('test' => array('test_number' => 72, "aid" => 725, 'sid' => 825, 'function' => array('basicCompare', 'subsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202102", "force_accounts" => array(725))),
            			'expected' => array('billrun' => array('billrun_key' => '202102', 'aid' => 725, 'after_vat' => array("825" => 213.61935483870974), 'total' => 213.61935483870974, 'vatable' => 182.5806451612904, 'vat' => 0)),
            			'line' => array('types' => array('flat', 'credit')), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-2996"
            		),
            		array('test' => array('label' => 'test the prorated discounts days is rounded down', 'test_number' => 73, "aid" => 230, 'sid' => 80018, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202012", "force_accounts" => array(230))),
            			'expected' => array('billrun' => array('billrun_key' => '202012', 'aid' => 230, 'after_vat' => array("80018" => 35.778665181058486), 'total' => 35.778665181058486, 'vatable' => 30.58005571030641, 'vat' => 17),
            				'line' => array('types' => array('flat'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-3014",
            		),
            		array('test' => array('label' => 'test the service line created', 'test_number' => 746, "aid" => 399, 'sid' => 499, 'function' => array('basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202103", "force_accounts" => array(399))),
            			'expected' => array('billrun' => array('billrun_key' => '202103', 'aid' => 399, 'after_vat' => array("499" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
            				'line' => array('types' => array('flat'))), 'jiraLink' => "https://billrun.atlassian.net/browse/BRCD-3013",
            		),
            			array('test' => array('label' => 'test the Conditional charge is applied only to one subscriber under the account instead of two', 'test_number' => 75, "aid" => 3082, 'sid' => array(3083, 3084), 'function' => array('basicCompare', 'sumSids', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202106", "force_accounts" => array(3082))),
            				'expected' => array('billrun' => array('billrun_key' => '202106', 'aid' => 3082, 'after_vat' => array("3083" => 175.5, "3084" => 175.5), 'total' => 351, 'vatable' => 300, 'vat' => 17),
            				'line' => array('types' => array('credit')))
                     ),
            		array('test' => array('test_number' => 76, "aid" => 145, 'sid' => 245, 'function' => array('checkForeignFileds', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'checkForeignFileds' => ['plan' => ["foreign.plan.name" => 'PLAN_C'], 'service' => ['foreign.service.name' => 'NOT_TAXABLE'], 'discount' => ['foreign.service.name' => 'NOT_TAXABLE', "foreign.plan.name" => 'PLAN_C']], 'options' => array("stamp" => "202103", "force_accounts" => array(145))),
            			'expected' => array('billrun' => array('invoice_id' => 108, 'billrun_key' => '202103', 'aid' => 145, 'after_vat' => array("245" => 207), 'total' => 207, 'vatable' => 190, 'vat' => 17),
            				'line' => array('types' => array('flat', 'credit'))),
            		),
            	//	Multi day cycle test 
            			//allowPremature true invoicing day + force accounts , only some of the account will run 
            			array('preRun' => ['allowPremature', 'removeBillruns'],
            				'test' => array('test_number' => 73, 'aid' => 1, 'function' => array('testMultiDay'), 'options' => array("stamp" => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 'force_accounts' => [10000, 10027, 10026, 10025], 'invoicing_days' => ["1", "28"])),
            				'expected' => array('billrun_key' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), "accounts" => [10000 => "1", 10027 => "28"]), 'postRun' => ''),
            			//allowPremature true invoicing day without  force accounts , only accounts with same day as the pass invoice day will run 
            			array('preRun' => ['allowPremature', 'removeBillruns'],
                        'test' => array('test_number' => 74, 'aid' => 'abcd', 'function' => array('testMultiDay'),
                        'options' => array("stamp" => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 
                        'invoicing_days' => ["26", "27"])),
                       'expected' => array('billrun_key' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')),
                        "accounts" => [10025=>"26", 10026=>"27"]),
                         'postRun' => ''),
                   //allowPremature true invoicing day  all the days 1-28 , + force accounts with account from all day each account will run in its day 
                   array('preRun' => ['allowPremature', 'removeBillruns'],
                       'test' => array('test_number' => 75, 'aid' => 'abcd', 'function' => array('testMultiDay'), 'options' => array("stamp" => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')),
       
            						'invoicing_days' => ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "14", "15", "16", "17", "18", "19", "20", "21", "22", "23", "24", "25", "26", "27", "28"],
            						'force_accounts' => [10000, 10001, 10002, 10003, 10004, 10005, 10006, 10007, 10008, 10009, 10010, 10011, 10012, 10013, 10014, 10015, 10016, 10017, 10018, 10019, 10020, 10021, 10022, 10023, 10024, 10025, 10026, 10027]
            					)),
            				'expected' => array('billrun_key' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('1 month')), 'accounts' => [
            						10000 => "1", 10001 => "2", 10002 => "3", 10003 => "4", 10004 => "5", 10005 => "6",
            						10006 => "7", 10007 => "8", 10008 => "9", 10009 => "10", 10010 => "11", 10011 => "12",
            						10012 => "13", 10013 => "14", 10014 => "15", 10015 => "16", 10016 => "17", 10017 => "18",
            						10018 => "19", 10019 => "20", 10020 => "21", 10021 => "22", 10022 => "23", 10023 => "24",
            						10024 => "25", 10025 => "26", 10026 => "27", 10027 => "28"]),
            				'line' => array('types' => array('flat', 'credit')),
            				'postRun'=>['multi_day_cycle_false']
            			),
            			//override plan and service price cases   https://billrun.atlassian.net/browse/BRCD-3183
            			/* /*sid 771
            			  override plan price for a subscriber for a whole month -
            			  Expected: the plan price will be the overridden price  -pass */
            			array('test' => array('test_number' => 176, "aid" => 770, 'sid' => 771, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(770))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 770, 'after_vat' => array("771" => 58.5), 'total' => 58.5, 'vatable' => 50, 'vat' => 17),
            					'line' => array('types' => array('flat'))),),
            			/* sid 772
            			  override plan price for a subscriber, the last revision has the override  -
            			  Expected: the plan price will be overridden  for all the month-pass */
            			array('test' => array('test_number' => 177, "aid" => 882, 'sid' => 772, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(882))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 882, 'after_vat' => array("772" => 58.5), 'total' => 58.5, 'vatable' => 50, 'vat' => 17),
            					'line' => array('types' => array('flat'))),),
            			/* sid 773
            			  override plan price for a subscriber, the override is not the last revision   -
            			  Expected: the plan price will not be overridden  -pass */
            			array('test' => array('test_number' => 178, "aid" => 883, 'sid' => 773, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(883))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 883, 'after_vat' => array("773" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
            					'line' => array('types' => array('flat'))),),
            			/* sid 774
            			  override plan price for a subscriber, the subscriber “from” is from mid-month  (prorated plan)  -
            			  Expected: the prorated plan price will be overridden  - pass */
            			array('test' => array('test_number' => 179, "aid" => 884, 'sid' => 774, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(884))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 884, 'after_vat' => array("774" => 60.387096774193544), 'total' => 60.387096774193544, 'vatable' => 51.61290322580645, 'vat' => 17),
            					'line' => array('types' => array('flat'))),),
            			/* sid 775
            			  override plan price for a subscriber, the plan has a discount of about 100%
            			  Expected: the discount will be 100% of the plan price -pass */
            			array('test' => array('test_number' => 180, "aid" => 885, 'sid' => 775, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(885))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 885, 'after_vat' => array("775" => 0), 'total' => 0, 'vatable' => 0, 'vat' => 0),
            					'line' => array('types' => array('flat', 'credit'))),),
            			/* sid 776
            			  override plan price for a subscriber, the plan has a monetary discount of about more than the plan price(after override )
            			  Expected: the discount will be 100% of the plan price and anyway will not be more the plan price-pass */
            			array('test' => array('test_number' => 181, "aid" => 886, 'sid' => 776, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(886))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 886, 'after_vat' => array("776" => 0), 'total' => 0, 'vatable' => 0, 'vat' => 0),
            					'line' => array('types' => array('flat', 'credit'))),),
            			/* sid 777
            			  override another plan price for a subscriber
            			  Expected: the plan price will not be affected by the override - pass */
            			array('test' => array('test_number' => 182, "aid" => 887, 'sid' => 777, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(887))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 887, 'after_vat' => array("777" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
            					'line' => array('types' => array('flat'))),),
            			/* sid 778
            			  override service price,
            			  Expected: the service price will be the overridden price  - pass */
            			array('test' => array('test_number' => 183, "aid" => 888, 'sid' => 778, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(888))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 888, 'after_vat' => array("778" => 5.85), 'total' => 5.85, 'vatable' => 5, 'vat' => 17),
            					'line' => array('types' => array('flat', 'service'))),),
            			/* sid 779
            			  override service price with a condition, the condition is met
            			  Expected: the service price will be the overridden price  - pass */
            			array('test' => array('test_number' => 184, "aid" => 889, 'sid' => 779, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(889))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 889, 'after_vat' => array("779" => 5.85), 'total' => 5.85, 'vatable' => 5, 'vat' => 17),
            					'line' => array('types' => array('flat', 'service'))),),
            			/* sid 780
            			  override service price with a condition, the condition isn’t met
            			  Expected: the service price will not be overridden   - pass */
            			array('test' => array('test_number' => 185, "aid" => 890, 'sid' => 780, 'function' => array('basicCompare', 'lineExists', 'linesVSbillrun', 'rounded'), 'options' => array("stamp" => "202108", "force_accounts" => array(890))),
            				'expected' => array('billrun' => array('billrun_key' => '202108', 'aid' => 890, 'after_vat' => array("780" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
            					'line' => array('types' => array('flat', 'service'))),),
            /*
            start -> https://billrun.atlassian.net/browse/BRCD-3526
             sid 35268
			 plan activation date 1'st of the month - invoice generated on next month (full arrears + upfront) */
            array(
                'test' => array('test_number' => 185, "aid" => 35267, 'sid' => 35268, 'function' => array('totalsPrice', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded','checkPerLine'), 'options' => array("stamp" => "202204", "force_accounts" => array(35267))),
                'expected' => array(
                    'billrun' => array('billrun_key' => '202204', 'aid' => 35267, 'after_vat' => array("35268" => 234), 'total' => 234, 'vatable' => 200, 'vat' => 17),
                    'line' => array('types' => array('flat')),
                    'lines' => [
                        ['query' => array('sid' => 35268,'billrun' => "202204", 'plan' => 'UPFRONT1'), 'aprice' => 100],
                        ['query' => array('sid' => 35268,'billrun' => "202204", 'plan' => 'UPFRONT1'), 'aprice' => 100]
                    ]
                ),
            ),
            /* sid 352610
			plan activation date in mid month - invoice generated on next month (partial arrears + full upfront) */
            array(
                'test' => array('test_number' => 185, "aid" => 35269, 'sid' => 352610, 'function' => array('totalsPrice', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded','checkPerLine'), 'options' => array("stamp" => "202204", "force_accounts" => array(35269))),
                'expected' => array(
                    'billrun' => array('billrun_key' => '202204', 'aid' => 35269, 'after_vat' => array("352610" => 200.03225806451613), 'total' => 200.03225806451613, 'vatable' => 170.96774193548387, 'vat' => 17),
                    'line' => array('types' => array('flat')),
                    'lines' => [
                        ['query' => array('sid' => 352610,'billrun' => "202204", 'plan' => 'UPFRONT1'), 'aprice' => 100],
                        ['query' => array('sid' => 352610,'billrun' => "202204", 'plan' => 'UPFRONT1'), 'aprice' => 70.96774193548387]
                    ]
                ),
            ),
            /* sid 352612
			 plan activation date last day of the month - invoice generated on next month (1 day arrears + full upfront) */
            array(
                'test' => array('test_number' => 185, "aid" => 352611, 'sid' => 352612, 'function' => array('totalsPrice', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded','checkPerLine'), 'options' => array("stamp" => "202204", "force_accounts" => array(352611))),
                'expected' => array(
                    'billrun' => array('billrun_key' => '202204', 'aid' => 352611, 'after_vat' => array("352612" => 120.77419354838709), 'total' => 120.77419354838709, 'vatable' => 113.225806451612903, 'vat' => 17),
                    'line' => array('types' => array('flat')),
                    'lines' => [
                        ['query' => array('sid' => 352612,'billrun' => "202204", 'plan' => 'UPFRONT1'), 'aprice' => 100],
                        ['query' => array('sid' => 352612,'billrun' => "202204", 'plan' => 'UPFRONT1'), 'aprice' => 3.225806451612903]
                    ]
                ),
            ),
            /* sid 352613
			  plan activation date in the past few months - invoice generated on month (no arrears + full upfront)*/
            array(
                'test' => array('test_number' => 185, "aid" => 352613, 'sid' => 780, 'function' => array('totalsPrice', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded','checkPerLine'), 'options' => array("stamp" => "202204", "force_accounts" => array(352613))),
                'expected' => array(
                    'billrun' => array('billrun_key' => '202204', 'aid' => 352613, 'after_vat' => array("352614" => 117), 'total' => 117, 'vatable' => 100, 'vat' => 17),
                    'line' => array('types' => array('flat'))
                ),
            ),
            /* sid 352616
			 Plan change in 1'st day of the month - invoice generated on next month (refund old plan & charge new plan for arrears + new plan upfront) */
            array(
                'test' => array('test_number' => 185, "aid" => 352615, 'sid' => 352616, 'function' => array('totalsPrice', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded','checkPerLine'), 'options' => array("stamp" => "202204", "force_accounts" => array(352615))),
                'expected' => array(
                    'billrun' => array('billrun_key' => '202204', 'aid' => 352615, 'after_vat' => array("352616" => 347.2258064516129), 'total' => 347.2258064516129, 'vatable' => 296.77419354838713, 'vat' => 17),
                    'line' => array('types' => array('flat')),
                    'lines' => [
                        ['query' => array('sid' => 352616,'billrun' => '202203', 'plan' => 'UPFRONT1'), 'aprice' => -96.77419354838709],
                        ['query' => array('sid' => 352616,'billrun' => "202204", 'plan' => 'UPFRONT2'), 'aprice' => 193.5483870967742],
                        ['query' => array('sid' => 352616,'billrun' => "202204", 'plan' => 'UPFRONT2'), 'aprice' => 200]
                    ]
                ),
            ),
            /* sid 352618
			  Plan change in mid month - invoice generated on next month (refund old plan & charge new plan for arrears + new plan upfront)*/
            array(
                'test' => array('test_number' => 185, "aid" => 352617, 'sid' => 352618, 'function' => array('totalsPrice', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded','checkPerLine'), 'options' => array("stamp" => "202204", "force_accounts" => array(352617))),
                'expected' => array(
                    'billrun' => array('billrun_key' => '202204', 'aid' => 352617, 'after_vat' => array("352618" => 317.0322580645161), 'total' => 317.0322580645161, 'vatable' => 270.9677419354839, 'vat' => 17),
                    'line' => array('types' => array('flat')),
                    'lines' => [
                        ['query' => array('sid' => 352618,'billrun' => '202203', 'plan' => 'UPFRONT1'), 'aprice' => -70.96774193548387],
                        ['query' => array('sid' => 352618,'billrun' => "202204", 'plan' => 'UPFRONT2'), 'aprice' => 141.93548387096774],
                        ['query' => array('sid' => 352618,'billrun' => "202204", 'plan' => 'UPFRONT2'), 'aprice' => 200]
                    ]
                ),
            ),
            /* sid 352620
			  Plan change in the last day of the month - invoice generated on next month (refund old plan & charge new plan for arrears + new plan upfront) */
            array(
                'test' => array('test_number' => 185, "aid" => 352619, 'sid' => 352620, 'function' => array('totalsPrice', 'basicCompare', 'lineExists', 'linesVSbillrun', 'rounded','checkPerLine'), 'options' => array("stamp" => "202204", "force_accounts" => array(352619))),
                'expected' => array(
                    'billrun' => array('billrun_key' => '202204', 'aid' => 352619, 'after_vat' => array("352620" => 237.77419354838707), 'total' => 237.77419354838707, 'vatable' => 203.2258064516129, 'vat' => 17),
                    'line' => array('types' => array('flat')), 
                    'lines' => [
                        ['query' => array('sid' => 352620,'billrun' => '202203', 'plan' => 'UPFRONT1'), 'aprice' => -3.225806451612903],
                        ['query' => array('sid' => 352620,'billrun' => "202204", 'plan' => 'UPFRONT2'), 'aprice' => 200],
                        ['query' => array('sid' => 352620,'billrun' => "202204", 'plan' => 'UPFRONT2'), 'aprice' => 6.4516129032258]
                    ]
                ),
            ),
            //end -> https://billrun.atlassian.net/browse/BRCD-3526
            /*
            duplicate tests for brcd 3439
            */
           
              
                [
                    "test" => [
                        "test_number" => 33439,
                        "aid" => 33439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "duplicateAccounts",
                            "passthrough"
                        ],
                        "options" => [
                            "stamp" => "201805",
                            "force_accounts" => [
                                33439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 101,
                            "billrun_key" => "201805",
                            "aid" => 33439
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ],
                            "final_charge" => -10
                        ]
                    ],
                    "postRun" => [
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 43439,
                        "aid" => 53439,
                        "function" => [
                            "basicCompare",
                            "linesVSbillrun",
                            "rounded",
                            "passthrough"
                        ],
                        "options" => [
                            "force_accounts" => [
                                53439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 102,
                            "billrun_key" => "201806",
                            "aid" => 53439
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 53439,
                        "aid" => 73439,
                        "function" => [
                            "basicCompare",
                            "linesVSbillrun",
                            "rounded",
                            "passthrough"
                        ],
                        "options" => [
                            "stamp" => "201805",
                            "force_accounts" => [
                                73439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 103,
                            "billrun_key" => "201805",
                            "aid" => 73439
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 63439,
                        "aid" => 93439,
                        "function" => [
                            "basicCompare",
                            "linesVSbillrun",
                            "rounded",
                            "passthrough"
                        ],
                        "options" => [
                            "force_accounts" => [
                                93439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 104,
                            "billrun_key" => "201806",
                            "aid" => 93439
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 73439,
                        "aid" => 113439,
                        "sid" => [
                            12,
                            14
                        ],
                        "function" => [
                            "basicCompare",
                            "sumSids",
                            "subsPrice",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201805",
                            "force_accounts" => [
                                113439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 105,
                            "billrun_key" => "201805",
                            "aid" => 113439,
                            "after_vat" => [
                                "123439" => 105.3,
                                "143439" => 117
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 83439,
                        "aid" => 133439,
                        "sid" => [
                            15,
                            16
                        ],
                        "function" => [
                            "basicCompare",
                            "sumSids",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                133439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 106,
                            "billrun_key" => "201806",
                            "aid" => 133439,
                            "after_vat" => [
                                "153439" => 117,
                                "163439" => 117
                            ],
                            "total" => 234,
                            "vatable" => 200,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 93439,
                        "aid" => 193439,
                        "sid" => 203439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "force_accounts" => [
                                193439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 107,
                            "billrun_key" => "201806",
                            "aid" => 193439,
                            "after_vat" => [
                                "203439" => 47.554838711
                            ],
                            "total" => 47.554838711,
                            "vatable" => 40.64516129032258,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 103439,
                        "aid" => 213439,
                        "sid" => 203439,
                        "function" => [
                            "checkForeignFileds",
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "checkForeignFileds" => [
                            "discount" => [
                                "foreign.discount.description" => "ttt"
                            ]
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                213439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 108,
                            "billrun_key" => "201806",
                            "aid" => 213439,
                            "after_vat" => [
                                "213439" => 57.745161289
                            ],
                            "total" => 57.745161289,
                            "vatable" => 49.354838709677416,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 113439,
                        "aid" => 253439,
                        "sid" => 263439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                253439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 109,
                            "billrun_key" => "201806",
                            "aid" => 253439,
                            "after_vat" => [
                                "263439" => 52.83870967741935
                            ],
                            "total" => 52.83870967741935,
                            "vatable" => 45.16129032258064,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 123439,
                        "aid" => 273439,
                        "sid" => [
                            28,
                            29
                        ],
                        "function" => [
                            "basicCompare",
                            "sumSids",
                            "subsPrice",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                273439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 110,
                            "billrun_key" => "201806",
                            "aid" => 273439,
                            "after_vat" => [
                                "283439" => 117,
                                "293439" => 52.8387096774193
                            ],
                            "total" => 169.838709677,
                            "vatable" => 145.1612903225806,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 133439,
                        "aid" => 303439,
                        "sid" => 313439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded",
                            "passthrough"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                303439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 111,
                            "billrun_key" => "201806",
                            "aid" => 303439,
                            "after_vat" => [
                                "313439" => 234
                            ],
                            "total" => 234,
                            "vatable" => 200,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 143439,
                        "aid" => 323439,
                        "sid" => 333439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "force_accounts" => [
                                323439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 112,
                            "billrun_key" => "201806",
                            "aid" => 323439,
                            "after_vat" => [
                                "333439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 153439,
                        "aid" => 343439,
                        "sid" => 333439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                343439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 113,
                            "billrun_key" => "201806",
                            "aid" => 343439,
                            "after_vat" => [
                                "333439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 163439,
                        "aid" => 353439,
                        "sid" => [
                            36,
                            37,
                            38,
                            39
                        ],
                        "function" => [
                            "basicCompare",
                            "sumSids",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                353439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 114,
                            "billrun_key" => "201806",
                            "aid" => 353439,
                            "after_vat" => [
                                "363439" => 128.7,
                                "373439" => 234,
                                "383439" => 128.7,
                                "393439" => 128.7
                            ],
                            "total" => 620.1,
                            "vatable" => 530,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 173439,
                        "aid" => 353439,
                        "sid" => [
                            36,
                            37,
                            38,
                            39
                        ],
                        "function" => [
                            "basicCompare",
                            "sumSids",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201807",
                            "force_accounts" => [
                                353439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 115,
                            "billrun_key" => "201807",
                            "aid" => 353439,
                            "after_vat" => [
                                "363439" => 117,
                                "373439" => 117,
                                "383439" => 117,
                                "393439" => 117
                            ],
                            "total" => 468,
                            "vatable" => 400,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 183439,
                        "aid" => 403439,
                        "sid" => 413439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                403439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 116,
                            "billrun_key" => "201806",
                            "aid" => 403439,
                            "after_vat" => [
                                "413439" => 351
                            ],
                            "total" => 351,
                            "vatable" => 300,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 193439,
                        "aid" => 423439,
                        "sid" => 433439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201806",
                            "force_accounts" => [
                                423439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 117,
                            "billrun_key" => "201806",
                            "aid" => 423439,
                            "after_vat" => [
                                "433439" => 234
                            ],
                            "total" => 234,
                            "vatable" => 200,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 283439,
                        "aid" => 623439,
                        "sid" => 633439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201807",
                            "force_accounts" => [
                                623439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 125,
                            "billrun_key" => "201807",
                            "aid" => 623439,
                            "after_vat" => [
                                "633439" => 2457
                            ],
                            "total" => 2457,
                            "vatable" => 2100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 293439,
                        "aid" => 623439,
                        "sid" => 633439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201808",
                            "force_accounts" => [
                                623439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 126,
                            "billrun_key" => "201808",
                            "aid" => 623439,
                            "after_vat" => [
                                "633439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 303439,
                        "aid" => 643439,
                        "sid" => 653439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201807",
                            "force_accounts" => [
                                643439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 127,
                            "billrun_key" => "201807",
                            "aid" => 643439,
                            "after_vat" => [
                                "653439" => 89.7
                            ],
                            "total" => 89.7,
                            "vatable" => 76.666666667,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 323439,
                        "aid" => 13439,
                        "sid" => 23439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201807",
                            "force_accounts" => [
                                13439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 128,
                            "billrun_key" => "201807",
                            "aid" => 13439,
                            "after_vat" => [
                                "23439" => 307
                            ],
                            "total" => 307,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "non",
                                "credit",
                                "service"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 333439,
                        "aid" => 663439,
                        "sid" => 673439,
                        "function" => [
                            "takeLastRevision"
                        ],
                        "options" => [
                            "stamp" => "201810",
                            "force_accounts" => [
                                663439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "firstname" => "yossiB"
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 343439,
                        "aid" => 703439,
                        "sid" => 713439,
                        "function" => [
                            "totalsPrice"
                        ],
                        "options" => [
                            "stamp" => "201901",
                            "force_accounts" => [
                                703439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "201901",
                            "aid" => 703439,
                            "after_vat" => [
                                "713439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 353439,
                        "aid" => 733439,
                        "sid" => 743439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201901",
                            "force_accounts" => [
                                733439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 131,
                            "billrun_key" => "201901",
                            "aid" => 733439,
                            "after_vat" => [
                                "743439" => 117
                            ]
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1725",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 363439,
                        "aid" => 753439,
                        "sid" => 763439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201807",
                            "force_accounts" => [
                                753439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 132,
                            "billrun_key" => "201807",
                            "aid" => 753439,
                            "after_vat" => [
                                "763439" => 234
                            ],
                            "total" => 234,
                            "vatable" => 200,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1725",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 373439,
                        "aid" => 753439,
                        "sid" => 763439,
                        "function" => [
                            "planExist",
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201808",
                            "force_accounts" => [
                                753439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 133,
                            "billrun_key" => "201808",
                            "aid" => 753439,
                            "after_vat" => [
                                "763439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "postRun" => [
                        "saveId"
                    ],
                    "jiraLink" => [
                        "https://billrun.atlassian.net/browse/BRCD-1725",
                        "https://billrun.atlassian.net/browse/BRCD-1730"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 383439,
                        "aid" => 793439,
                        "sid" => 783439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201903",
                            "force_accounts" => [
                                793439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 134,
                            "billrun_key" => "201903",
                            "aid" => 793439,
                            "after_vat" => [
                                "783439" => 117
                            ]
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "service"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1758",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 393439,
                        "aid" => 803439,
                        "sid" => 813439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201904",
                            "force_accounts" => [
                                803439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 135,
                            "billrun_key" => "201904",
                            "aid" => 803439,
                            "after_vat" => [
                                "813439" => 101.903225
                            ]
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "service"
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 403439,
                        "aid" => 823439,
                        "sid" => 833439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "201904",
                            "force_accounts" => [
                                823439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 136,
                            "billrun_key" => "201904",
                            "aid" => 823439,
                            "after_vat" => [
                                "833439" => 18.870967742
                            ]
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "service"
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 413439,
                        "aid" => 263439,
                        "function" => [
                            "basicCompare",
                            "linesVSbillrun",
                            "lineExists",
                            "rounded",
                            "passthrough",
                            "totalsPrice"
                        ],
                        "options" => [
                            "stamp" => "202003",
                            "force_accounts" => [
                                263439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "invoice_id" => 137,
                            "billrun_key" => "202003",
                            "aid" => 263439,
                            "after_vat" => [
                                "1003439" => 245.7
                            ],
                            "total" => 245.7,
                            "vatable" => 210,
                            "vat" => 17
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "service"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1493",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 423439,
                        "aid" => 12003439,
                        "sid" => 2003439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                12003439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 12003439,
                            "after_vat" => [
                                "2003439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 433439,
                        "aid" => 13003439,
                        "sid" => 3003439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                13003439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 13003439,
                            "after_vat" => [
                                "3003439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 443439,
                        "aid" => 14003439,
                        "sid" => 4003439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                14003439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 14003439,
                            "after_vat" => [
                                "4003439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 453439,
                        "aid" => 15003439,
                        "sid" => 5003439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                15003439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 15003439,
                            "after_vat" => [
                                "5003439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 463439,
                        "aid" => 16003439,
                        "sid" => 6003439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                16003439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 16003439,
                            "after_vat" => [
                                "6003439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 473439,
                        "aid" => 17003439,
                        "sid" => 7003439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                17003439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 17003439,
                            "after_vat" => [
                                "7003439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 483439,
                        "aid" => 12013439,
                        "sid" => 2013439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                12013439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 12013439,
                            "after_vat" => [
                                "2013439" => 74.72903225805
                            ],
                            "total" => 74.72903225805,
                            "vatable" => 63.8709677424,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 493439,
                        "aid" => 13013439,
                        "sid" => 3013439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                13013439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 13013439,
                            "after_vat" => [
                                "3013439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 503439,
                        "aid" => 14013439,
                        "sid" => 4013439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                14013439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 14013439,
                            "after_vat" => [
                                "4013439" => 74.72903225805
                            ],
                            "total" => 74.72903225805,
                            "vatable" => 63.8709677424,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 513439,
                        "aid" => 15013439,
                        "sid" => 5013439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                15013439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 15013439,
                            "after_vat" => [
                                "5013439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 523439,
                        "aid" => 16013439,
                        "sid" => 6013439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                16013439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 16013439,
                            "after_vat" => [
                                "6013439" => 74.72903225805
                            ],
                            "total" => 74.72903225805,
                            "vatable" => 63.8709677424,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 533439,
                        "aid" => 17013439,
                        "sid" => 7013439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                17013439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 17013439,
                            "after_vat" => [
                                "7013439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 543439,
                        "aid" => 12023439,
                        "sid" => 2023439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                12023439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 12023439,
                            "after_vat" => [
                                "2023439" => 74.729033
                            ],
                            "total" => 74.729033,
                            "vatable" => 63.8709668,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 553439,
                        "aid" => 13023439,
                        "sid" => 3023439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                13023439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 13023439,
                            "after_vat" => [
                                "3023439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 563439,
                        "aid" => 14023439,
                        "sid" => 4023439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                14023439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 14023439,
                            "after_vat" => [
                                "4023439" => 75.106451612903
                            ],
                            "total" => 75.106451612903,
                            "vatable" => 64.19354838709,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 573439,
                        "aid" => 15023439,
                        "sid" => 5023439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                15023439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 15023439,
                            "after_vat" => [
                                "5023439" => 98.50645
                            ],
                            "total" => 98.50645,
                            "vatable" => 84.193548,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 583439,
                        "aid" => 16023439,
                        "sid" => 6023439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                16023439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 16023439,
                            "after_vat" => [
                                "6023439" => 37.36451703
                            ],
                            "total" => 37.36451703,
                            "vatable" => 31.935483864,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 593439,
                        "aid" => 17023439,
                        "sid" => 7023439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                17023439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 17023439,
                            "after_vat" => [
                                "7023439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 603439,
                        "aid" => 12033439,
                        "sid" => 2033439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                12033439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 12033439,
                            "after_vat" => [
                                "2033439" => 40.76129119
                            ],
                            "total" => 40.76129119,
                            "vatable" => 34.83870926,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 613439,
                        "aid" => 13033439,
                        "sid" => 3033439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                13033439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 13033439,
                            "after_vat" => [
                                "3033439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 623439,
                        "aid" => 14033439,
                        "sid" => 4033439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                14033439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 14033439,
                            "after_vat" => [
                                "4033439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 633439,
                        "aid" => 15033439,
                        "sid" => 5033439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                15033439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 15033439,
                            "after_vat" => [
                                "5033439" => 75.1064516
                            ],
                            "total" => 75.1064516,
                            "vatable" => 64.1935483,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 643439,
                        "aid" => 16033439,
                        "sid" => 6033439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                16033439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 16033439,
                            "after_vat" => [
                                "6033439" => 40.76129119
                            ],
                            "total" => 40.76129119,
                            "vatable" => 34.83870926,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 653439,
                        "aid" => 17033439,
                        "sid" => 7033439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202004",
                            "force_accounts" => [
                                17033439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202004",
                            "aid" => 17033439,
                            "after_vat" => [
                                "7033439" => 105.3
                            ],
                            "total" => 105.3,
                            "vatable" => 90,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-1913",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 663439,
                        "aid" => 1875013439,
                        "sid" => 1875003439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202008",
                            "force_accounts" => [
                                1875013439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202008",
                            "aid" => 1875013439,
                            "after_vat" => [
                                "1875003439" => 113.22580645161288
                            ],
                            "total" => 113.22580645161288,
                            "vatable" => 96.77419354838709,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-2742",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 683439,
                        "aid" => 17703439,
                        "sid" => 17713439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202005",
                            "force_accounts" => [
                                17703439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202005",
                            "aid" => 17703439,
                            "after_vat" => [
                                "17713439" => 200
                            ],
                            "total" => 200,
                            "vatable" => 200,
                            "vat" => 0
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "service"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-2492",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 693439,
                        "aid" => 20000007843439,
                        "sid" => 2903439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202101",
                            "force_accounts" => [
                                20000007843439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202101",
                            "aid" => 20000007843439,
                            "after_vat" => [
                                "3439" => 1.9565442,
                                "2903439" => 29.08097389230968
                            ],
                            "total" => 30.339039414890326,
                            "vatable" => 25.93080291870968,
                            "vat" => 0
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "service",
                            "credit"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-2993",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 703439,
                        "aid" => 20000007853439,
                        "sid" => 2913439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202101",
                            "force_accounts" => [
                                20000007853439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202101",
                            "aid" => 20000007853439,
                            "after_vat" => [
                                "3439" => 1.9565442,
                                "2913439" => 7.586449083870966
                            ],
                            "total" => 9.542993283870967,
                            "vatable" => 7.5594141935483865,
                            "vat" => 0
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "service",
                            "credit"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-2993",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 713439,
                        "aid" => 179753439,
                        "sid" => 179763439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202101",
                            "force_accounts" => [
                                179753439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202101",
                            "aid" => 179753439,
                            "after_vat" => [
                                "179763439" => 160.0258064516129
                            ],
                            "total" => 160.0258064516129,
                            "vatable" => 136.7741935483871,
                            "vat" => 0
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "credit"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-2988",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 723439,
                        "aid" => 7253439,
                        "sid" => 8253439,
                        "function" => [
                            "basicCompare",
                            "subsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202102",
                            "force_accounts" => [
                                7253439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202102",
                            "aid" => 7253439,
                            "after_vat" => [
                                "8253439" => 213.61935483870974
                            ],
                            "total" => 213.61935483870974,
                            "vatable" => 182.5806451612904,
                            "vat" => 0
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "credit"
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-2996",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "label" => "test the prorated discounts days is rounded down",
                        "test_number" => 733439,
                        "aid" => 2303439,
                        "sid" => 800183439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202012",
                            "force_accounts" => [
                                2303439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202012",
                            "aid" => 2303439,
                            "after_vat" => [
                                "800183439" => 35.778665181058486
                            ],
                            "total" => 35.778665181058486,
                            "vatable" => 30.58005571030641,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-3014",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "label" => "test the service line created",
                        "test_number" => 743439,
                        "aid" => 3993439,
                        "sid" => 4993439,
                        "function" => [
                            "basicCompare",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202103",
                            "force_accounts" => [
                                3993439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202103",
                            "aid" => 3993439,
                            "after_vat" => [
                                "4993439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "jiraLink" => "https://billrun.atlassian.net/browse/BRCD-3013",
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "label" => "test the Conditional charge is applied only to one subscriber under the account instead of two",
                        "test_number" => 753439,
                        "aid" => 30823439,
                        "sid" => [
                            3083,
                            3084
                        ],
                        "function" => [
                            "basicCompare",
                            "sumSids",
                            "totalsPrice",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202106",
                            "force_accounts" => [
                                30823439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202106",
                            "aid" => 30823439,
                            "after_vat" => [
                                "30833439" => 175.5,
                                "30843439" => 175.5
                            ],
                            "total" => 351,
                            "vatable" => 300,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "credit"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "preRun" => [
                        "allowPremature",
                        "removeBillruns"
                    ],
                    "test" => [
                        "test_number" => 733439,
                        "aid" => 13439,
                        "function" => [
                            "testMultiDay"
                        ],
                        "options" => [
                            "stamp" => "202205",
                            "force_accounts" => [
                                100003439,
                                100273439,
                                100263439,
                                100253439
                            ],
                            "invoicing_days" => [
                                "1",
                                "28"
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun_key" => "202205",
                        "accounts" => [
                            "100003439" => "1",
                            "100273439" => "28"
                        ]
                    ],
                    "postRun" => "",
                    "duplicate" => true
                ],
                [
                    "preRun" => [
                        "allowPremature",
                        "removeBillruns"
                    ],
                    "test" => [
                        "test_number" => 743439,
                        "aid" => 13439,
                        "function" => [
                            "testMultiDay"
                        ],
                        "options" => [
                            "stamp" => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')),
                            "invoicing_days" => [
                                "26",
                                "27"
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun_key" => "202205",
                        "accounts" => [
                            "100253439" => "26",
                            "100263439" => "27"
                        ]
                    ],
                    "postRun" => "",
                    "duplicate" => true
                ],
                [
                    "preRun" => [
                        "allowPremature",
                        "removeBillruns"
                    ],
                    "test" => [
                        "test_number" => 753439,
                        "aid" => "NaN",
                        "function" => [
                            "testMultiDayNotallowPremature"
                        ],
                        "options" => [
                            'options' => array("stamp" => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month'))),
                            "invoicing_days" => [
                                "1",
                                "2",
                                "3",
                                "4",
                                "5",
                                "6",
                                "7",
                                "8",
                                "9",
                                "10",
                                "11",
                                "12",
                                "13",
                                "14",
                                "15",
                                "16",
                                "17",
                                "18",
                                "19",
                                "20",
                                "21",
                                "22",
                                "23",
                                "24",
                                "25",
                                "26",
                                "27",
                                "28"
                            ],
                            "force_accounts" => [
                                100003439,
                                100013439,
                                100023439,
                                100033439,
                                100043439,
                                100053439,
                                100063439,
                                100073439,
                                100083439,
                                100093439,
                                100103439,
                                100113439,
                                100123439,
                                100133439,
                                100143439,
                                100153439,
                                100163439,
                                100173439,
                                100183439,
                                100193439,
                                100203439,
                                100213439,
                                100223439,
                                100233439,
                                100243439,
                                100253439,
                                100263439,
                                100273439
                        ]
                    ]
                ],
                    "expected" => [
                        "billrun_key" => "202207",
                        "accounts" => [
                            "100003439" => "1",
                            "100013439" => "2",
                            "100023439" => "3",
                            "100033439" => "4",
                            "100043439" => "5",
                            "100053439" => "6",
                            "100063439" => "7",
                            "100073439" => "8",
                            "100083439" => "9",
                            "100093439" => "10",
                            "100103439" => "11",
                            "100113439" => "12",
                            "100123439" => "13",
                            "100133439" => "14",
                            "100143439" => "15",
                            "100153439" => "16",
                            "100163439" => "17",
                            "100173439" => "18",
                            "100183439" => "19",
                            "100193439" => "20",
                            "100203439" => "21",
                            "100213439" => "22",
                            "100223439" => "23",
                            "100233439" => "24",
                            "100243439" => "25",
                            "100253439" => "26",
                            "100263439" => "27",
                            "100273439" => "28"
                        ]
                    ],
                    "line" => [
                        "types" => [
                            "flat",
                            "credit"
                        ]
                    ],
                    "postRun" => [
                        "multi_day_cycle_false"
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1763439,
                        "aid" => 7703439,
                        "sid" => 7713439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                7703439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 7703439,
                            "after_vat" => [
                                "7713439" => 58.5
                            ],
                            "total" => 58.5,
                            "vatable" => 50,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1773439,
                        "aid" => 8823439,
                        "sid" => 7723439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8823439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8823439,
                            "after_vat" => [
                                "7723439" => 58.5
                            ],
                            "total" => 58.5,
                            "vatable" => 50,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1783439,
                        "aid" => 8833439,
                        "sid" => 7733439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8833439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8833439,
                            "after_vat" => [
                                "7733439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1793439,
                        "aid" => 8843439,
                        "sid" => 7743439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8843439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8843439,
                            "after_vat" => [
                                "7743439" => 60.387096774193544
                            ],
                            "total" => 60.387096774193544,
                            "vatable" => 51.61290322580645,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1803439,
                        "aid" => 8853439,
                        "sid" => 7753439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8853439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8853439,
                            "after_vat" => [
                                "7753439" => 0
                            ],
                            "total" => 0,
                            "vatable" => 0,
                            "vat" => 0
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1813439,
                        "aid" => 8863439,
                        "sid" => 7763439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8863439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8863439,
                            "after_vat" => [
                                "7763439" => 0
                            ],
                            "total" => 0,
                            "vatable" => 0,
                            "vat" => 0
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "credit"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1823439,
                        "aid" => 8873439,
                        "sid" => 7773439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8873439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8873439,
                            "after_vat" => [
                                "7773439" => 117
                            ],
                            "total" => 117,
                            "vatable" => 100,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1833439,
                        "aid" => 8883439,
                        "sid" => 7783439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8883439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8883439,
                            "after_vat" => [
                                "7783439" => 5.85
                            ],
                            "total" => 5.85,
                            "vatable" => 5,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 1843439,
                        "aid" => 8893439,
                        "sid" => 7793439,
                        "function" => [
                            "basicCompare",
                            "lineExists",
                            "linesVSbillrun",
                            "rounded"
                        ],
                        "options" => [
                            "stamp" => "202108",
                            "force_accounts" => [
                                8893439
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [
                            "billrun_key" => "202108",
                            "aid" => 8893439,
                            "after_vat" => [
                                "7793439" => 5.85
                            ],
                            "total" => 5.85,
                            "vatable" => 5,
                            "vat" => 17
                        ],
                        "line" => [
                            "types" => [
                                "flat",
                                "service"
                            ]
                        ]
                    ],
                    "duplicate" => true
                ],
                [
                    "test" => [
                        "test_number" => 13595,
                        "aid" => 13595,
                        "sid" => 63595,
                        "function" => [
                            "billrunNotCreated"
                        ],
                        "options" => [
                            "stamp" => "202202",
                            "force_accounts" => [
                                13595
                            ]
                        ]
                    ],
                    "expected" => [
                        "billrun" => [],
                        "lines" => []   
                    ],
                    "duplicate" => false
                ],
            
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
    }


    public function __construct($label = false)
    {
        parent::__construct("test Aggregatore");
        $this->ratesCol = Billrun_Factory::db()->ratesCollection();
        $this->plansCol = Billrun_Factory::db()->plansCollection();
        $this->linesCol = Billrun_Factory::db()->linesCollection();
        $this->servicesCol = Billrun_Factory::db()->servicesCollection();
        $this->discountsCol = Billrun_Factory::db()->discountsCollection();
        $this->subscribersCol = Billrun_Factory::db()->subscribersCollection();
        $this->balancesCol = Billrun_Factory::db()->discountsCollection();
        $this->billingCyclr = Billrun_Factory::db()->billing_cycleCollection();
        $this->billrunCol = Billrun_Factory::db()->billrunCollection();
        $this->construct(basename(__FILE__, '.php'), ['bills', 'billing_cycle', 'billrun', 'counters', 'discounts', 'taxes']);
        $this->setColletions();
        $this->loadDbConfig();
    }

    public function loadDbConfig()
    {
        Billrun_Config::getInstance()->loadDbConfig();
    }

    /**
     * 
     * @param array $row current test case
     */
    public function aggregator($row)
    {
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
    public function getBillruns($query = null)
    {
        return $this->billrunCol->query($query)->cursor();
    }

    /**
     * the function is runing all the test cases  
     * print the test result
     * and restore the original data 
     */
    public function TestPerform(){

        $this->tests = $this->test_cases();
        // execute test cases pass by tests or all if it empty
        $request = new Yaf_Request_Http;
        $this->test_cases_to_run = $request->get('tests');
        if ($this->test_cases_to_run) {
            $this->test_cases_to_run = explode(',', $this->test_cases_to_run);
            foreach ($this->tests as $case) {
                if (in_array($case['test']['test_number'], $this->test_cases_to_run)){
                    $this->cases[] = $case;
                }
             }
             $this->tests =  $this->cases;
            }
       
        foreach ($this->tests as $key => $row) {

            $aid = $row['test']['aid'];
            $this->message .= "<span id={$row['test']['test_number']}>test number : " . $row['test']['test_number'] . '</span><br>';
            if (isset($row['test']['label'])) {
                $this->message .= '<br>test label :  ' . $row['test']['label'];
            }
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
    protected function runT($row)
    {
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
    protected function basicCompare($key, $returnBillrun, $row)
    {
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
    public function checkInvoiceId($key, $returnBillrun, $row)
    {
        $passed = TRUE;
        $invoice_id = $row['expected']['billrun']['invoice_id'] ? $row['expected']['billrun']['invoice_id'] : null;
        $retun_invoice_id = $returnBillrun['invoice_id'] ? $returnBillrun['invoice_id'] : false;
        if (isset($invoice_id)) {

            if (!empty($retun_invoice_id) && $retun_invoice_id == $invoice_id) {
                $this->message .= 'invoice_id :' . $retun_invoice_id . $this->pass;
            } else {
                $passed = false;
                $this->message .= 'invoice_id :' . $retun_invoice_id . $this->fail;
            }
        } else {
            if (!empty($retun_invoice_id) && $retun_invoice_id == $this->LatestResults[0][0]['invoice_id'] + 1) {
                $this->message .= 'invoice_id :' . $retun_invoice_id . $this->pass;
            } else {
                $passed = false;
                $this->message .= 'invoice_id :' . $retun_invoice_id . $this->fail;
            }
        }

        return $passed;
    }

    /**
     * check if all subscribers was calculeted
     * @param int $key number of the test case
     * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
     * @param array $row current test case current test case
     * @return boolean true if the test is pass and false if the tast is fail
     */
    public function sumSids($key, $returnBillrun, $row)
    {
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
    public function totalsPrice($key, $returnBillrun, $row)
    {
        $passed = TRUE;
        $this->message .= "<b> total Price :</b> <br>";
        if (Billrun_Util::isEqual($returnBillrun['totals']['after_vat'], $row['expected']['billrun']['total'], 0.00001)) {
            $this->message .= "total after vat is : " . $returnBillrun['totals']['after_vat'] . $this->pass;
        } else {
            $this->message .= "expected total after vat is : {$row['expected']['billrun']['total']} <b>result is </b>: {$returnBillrun['totals']['after_vat']}" . $this->fail;
            $passed = FALSE;
        }
        $vatable = (isset($row['expected']['billrun']['vatable'])) ? $row['expected']['billrun']['vatable'] : null;
        if ($vatable <> 0) {
            $vat = $this->calcVat($returnBillrun['totals']['before_vat'], $returnBillrun['totals']['after_vat'], $vatable);
            if (Billrun_Util::isEqual($vat, $row['expected']['billrun']['vat'], 0.00001)) {
                $this->message .= "total befor vat is : " . $returnBillrun['totals']['before_vat'] . $this->pass;
            } else {
                $this->message .= "expected total befor vat is : {$row['expected']['billrun']['vatable']} <b>result is </b>:  {$returnBillrun['totals']['before_vat']}" . $this->fail;
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
    public function calcVat($beforVat, $aftetrVat, $vatable = null)
    {
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
    public function saveLatestResults($returnBillrun, $row)
    {
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
    public function getLines($row)
    {
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
     * check if all the lines was created 
     * @param int $key number of the test case
     * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
     * @param array $row current test case
     * @return boolean true if the test is pass and false if the tast is fail
     */
    public function lineExists($key, $returnBillrun, $row)
    {
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
    public function billrunNotCreated($key, $returnBillrun = null, $row)
    {
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
    public function changeConfig($key, $row)
    {
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
    public function duplicateAccounts($key, $returnBillrun, $row)
    {
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
    public function confirm($returnBillrun, $row)
    {
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
    public function subsPrice($key, $returnBillrun, $row)
    {
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
    public function linesVSbillrun($key, $returnBillrun, $row)
    {
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
    public function rounded($key, $returnBillrun, $row)
    {
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
    public function removeBillrun($key, $row)
    {
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
    public function billrunExists($key, $row)
    {
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
    public function fullCycle($key, $returnBillrun, $row)
    {
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
    public function pagination($key, $returnBillrun, $row)
    {
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
    public function charge_included_service($key, $row)
    {
        Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/charge_included_service.ini');
    }

    /**
     *  set charge_included_service to true
     */
    public function charge_not_included_service($key, $row)
    {
        Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/charge_not_included_service.ini');
    }

    /**
     * check if invoice was created
     * @param int $key number of the test case
     * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
     * @param array $row current test case
     * @return boolean true if the test is pass and false if the tast is fail
     */
    public function invoice_exist($key, $returnBillrun, $row)
    {
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
    public function passthrough($key, $returnBillrun, $row)
    {
        $passed = true;
        $accounts = Billrun_Factory::account();
        $this->message .= "<b> passthrough_fields :</b> <br>";
        $account = $accounts->loadAccountForQuery((array('aid' => $row['test']['aid'])));
        $account = $accounts->getCustomerData();
        $address = $account['address'];
        if ($returnBillrun['attributes']['address'] === $address) {
            $this->message .= "passthrough work well" . $this->pass;
        } else {
            $this->message .= "passthrough fail" . $this->fail;
            $passed = false;
        }
        return $passed;
    }

    /**
     *  save invoice_id 
     *  @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
     *  @param array $row current test case
     */
    public function saveId($returnBillrun, $row)
    {
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
    public function overrides_invoice($key, $returnBillrun, $row)
    {
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
    public function expected_invoice($key, $row)
    {
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
    public function takeLastRevision($key, $returnBillrun, $row)
    {
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
    public function planExist($key, $returnBillrun, $row)
    {
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

    /**
     *  check if  Foreign Fileds create correctly
     * 
     * @param int $key number of the test case
     * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
     * @param array $row current test case current test case
     * @return boolean true if the test is pass and false if the tast is fail
     */
    public function checkForeignFileds($key, $returnBillrun, $row)
    {
        $passed = TRUE;
        $this->message .= "<b> Foreign Fileds :</b> <br>";
        $entitys = $row['test']['checkForeignFileds'];
        $lines = $this->getLines($row);
        foreach ($lines as $line) {
            if ($line['usaget'] == 'discount') {
                $lines_['discount'][] = $line;
            }
            if ($line['type'] == 'service') {
                $lines_['service'][] = $line;
            }
            if ($line['type'] == 'flat') {
                $lines_['plan'][] = $line;
            }
        }
        $billruns = $this->getBillruns();
        $billruns_ = [];
        foreach ($billruns as $bill) {
            $billruns_[] = $bill->getRawData();
        }

        foreach ($row['test']['checkForeignFileds'] as $key => $val) {
            foreach ($val as $path => $value) {
                for ($i = 0; $i <= count($lines_[$key]); $i++) {
                    if ($lineValue = Billrun_Util::getIn($lines_[$key][$i], $path)) {
                        if ($lineValue == $value) {
                            $this->message .= "Foreign Fileds exists  line type $key ,</br> path : " . $path . "</br>value : " . $value . $this->pass;
                            continue 2;
                        }
                    }
                    if (!$find) {
                        $this->message .= "billrun not create for aid $aid " . $this->fail;
                        $this->assertTrue(0);
                    }
                }
            }
        }

     
        return $passed;
    }

    public function multi_day_cycle_false()
    {
        Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/multi_day_cycle_false.ini');
    }
    public function allowPremature($param)
    {
        Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/allow_premature_run.ini');
    }
    public function notallowPremature($param)
    {
        Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/not_allow_premature_run.ini');
    }

    public function testMultiDay($key, $returnBillrun, $row){
        $passed = true;
        $aids = [];
        foreach ($row['expected']['accounts'] as $aid => $day) {
            $aids[] = $aid;
            $aid_and_days[$aid] = $day;
        }

        $billruns = $this->getBillruns();
        $billruns_ = [];
        foreach ($billruns as $bill) {
            $billruns_[] = $bill->getRawData();
        }

     //Checks that all the  billruns  that should have been created were created
        $find = false;
        foreach ($aids as $aid) {
            $find = false;
            foreach ($billruns_ as $bills) {
                if ($bills['aid'] == $aid) {
                    $this->message .= "billrun created for aid $aid  " . $this->pass;
                    $find = true;
                    continue 2;
                }
            }
            if (!$find) {
                $this->message .= "billrun not created for aid $aid " . $this->fail;
                $this->assertTrue(0);
            }
        }

        //Checks that no  billruns have been created that should not be created
        if (count($billruns_) > count($aids)) {

            $wrongBillrun = array_filter($billruns_, function (array $bill) use ($aids) {
                return !in_array($bill['aid'], $aids);
            });

            foreach ($wrongBillrun as $wrong => $bill) {
                $this->message .= "billrun  create for aid {$bill['aid']} and was not meant to be formed " . $this->fail;
                $this->assertTrue(0);
            }
        }

        //Checking that invoicing day is correct
        foreach ($billruns_ as $bill) {
            foreach ($aid_and_days as $aid => $day) {
                if ($bill['aid'] == $aid) {
                    if ($bill['invoicing_day'] == $day) {
                        $this->message .= "billrun  invoicing_day for aid $aid is correct ,day : $day" . $this->pass;
                        continue 2;
                    } else {
                        $this->message .= "billrun  invoicing_day for aid $aid is not correct ,expected day is  : $day , actual result is{$bill['invoicing_day']} " . $this->fail;
                        $this->assertTrue(0);
                    }
                }
            }
        }
    }

    public function removeBillruns()
    {
        $this->billingCyclr->remove(['billrun_key' => ['$ne' => 'abc']]);
        $this->billrunCol->remove(['billrun_key' => ['$ne' => 'abc']]);
    }

    public function testMultiDayNotallowPremature($key, $returnBillrun, $row){
        $now = date('d');
        $billruns = $this->getBillruns();
        $billruns_ = [];
        $aid_and_days = $row['expected']['accounts'];
        foreach ($billruns as $bill) {
            $billruns_[] = $bill->getRawData();
        }

        foreach ($billruns_ as $bill) {

            if ($bill['invoicing_day'] == $aid_and_days[$bill['aid']]) {
                $this->message .= "billrun  invoicing_day for aid $aid is correct ,day : {$aid_and_days[$bill['aid']]}" . $this->pass;
            } else {
                $this->message .= "billrun  invoicing_day for aid $aid is not correct ,expected day is  :  {$aid_and_days[$bill['aid']]} , actual result is{$bill['invoicing_day']} " . $this->fail;
                $this->assertTrue(0);
            }
            if ($bill['invoicing_day'] <= $now) {
                $this->message .= "notallowPrematurun  is corrcet now its  $now  and  invoicing day  is{$aid_and_days[$bill['aid']]} aid $aid " . $this->pass;
            } else {
                $this->message .= "notallowPrematurun  is not  corrcet now its  $now  and  invoicing day  is {$aid_and_days[$bill['aid']]}  aid $aid " . $this->fail;
                $this->assertTrue(0);
            }
            $this->message .= '<br>****************************************************************<br>';
        }
    }

    public function cleanAfterAggregate($key, $row){
        $stamp = $row['test']['options']['stamp'];
        $account[] = $row['test']['aid'];
        Billrun_Aggregator_Customer::removeBeforeAggregate($stamp, $account);
    }

    public function checkPerLine($key, $returnBillrun, $row){
        $this->message .="<b>checkPerLine</b></br>";
        foreach ($row['expected']['lines'] as $line) {
            $cursor = Billrun_Factory::db()->linesCollection()->query($line['query'])->cursor()->limit(100000);
            foreach ($cursor as $rowData) {
                $lines[] = $rowData->getRawData();
            }
         
            if (is_null($lines)) {
                $this->message .= "no create line for this query : " . json_encode($line['query']) . $this->fail;
                $this->assertTrue(0);
            }
            if (!is_null($lines) && !empty($lines) && $lines[0]['aprice'] != $line['aprice']) {
                $this->message .= "expected aprice is : " . $line['aprice'] . "actually aprice is: " . $lines[0]['arice'] . $this->fail;
                $this->assertTrue(0);
            }
            if (!is_null($lines) && !empty($lines) && $lines[0]['aprice'] == $line['aprice']) {
                $this->message .= "expected aprice is : " . $line['aprice'] . $this->pass;
                $this->assertTrue(1);
            }
        }
    }
}

