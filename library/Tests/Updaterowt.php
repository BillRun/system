<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * reset lines
 *
 * @package  rebalance
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Updaterowt extends UnitTestCase {

	use Tests_SetUp;

	protected $fails = [];
	protected $row = [];
	protected $balances = [];
	protected $message = '';
	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
	protected $servicesToUse = ["SERVICE1", "SERVICE2"];
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';
	protected $rows = [
		////	New tests for new override price and includes format
//		case F: NEW-PLAN-X3+NEW-SERVICE1+NEW-SERVICE2
//Test num 1 f1
		array('row' => array('stamp' => 'f1', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 60, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2']),
			'expected' => array('in_group' => 60, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))
		),
//Test num 2 f2
		array('row' => array('stamp' => 'f2', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 50, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2']),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 3 f3
		array('row' => array('stamp' => 'f3', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 50, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2'],),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
///Test num 4 f4
		array('row' => array('stamp' => 'f4', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 280, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2',],),
			'expected' => array('in_group' => 55, 'over_group' => 225, 'aprice' => 106.5, 'charge' => array('retail' => 106.5,))),
//Test num 5 f5
		array('rebalance' => true, 'row' => array('stamp' => 'f5', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 180, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2'],),
			'expected' => array('in_group' => 0, 'over_group' => 180, 'aprice' => 18, 'charge' => array('retail' => 18,))),
		//case G: NEW-PLAN-X3+NEW-SERVICE3
//Test num 6 g1
		array('row' => array('stamp' => 'g1', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 120, 'services_data' => ['NEW-SERVICE3'],),
			'expected' => array('in_group' => 120, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 7 g2
		array('row' => array('stamp' => 'g2', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 110.5, 'services_data' => ['NEW-SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 110.5, 'aprice' => 11.1, 'charge' => array('retail' => 11.1,))),
//Test num 8 g3
		array('row' => array('stamp' => 'g3', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 20, 'services_data' => ['NEW-SERVICE3',],),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 9 g5
		array('rebalance' => true, 'row' => array('stamp' => 'g5', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 8, 'services_data' => ['NEW-SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 8, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),
		//	case H: NEW-PLAN-A0 (without groups)+NEW-SERVICE1+NEW-SERVICE4  
//Test num 10 h1
		array('row' => array('stamp' => 'h1', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'gr', 'usagev' => 35, 'services_data' => ['NEW-SERVICE4',],),
			'expected' => array('in_group' => 35, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 11 h2
		array('row' => array('stamp' => 'h2', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 35.5, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 35.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 12 h3
		array('row' => array('stamp' => 'h3', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'gr', 'usagev' => 180, 'services_data' => ['NEW-SERVICE4'],),
			'expected' => array('in_group' => 165, 'over_group' => 15, 'aprice' => 3, 'charge' => array('retail' => 3,))),
//Test num 13 h4
		array('row' => array('stamp' => 'h4', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 4.5, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 4.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 14 h5
		array('rebalance' => true, 'row' => array('stamp' => 'h5', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 12, 'services_data' => ['NEW-SERVICE1'],),
			'expected' => array('in_group' => 0, 'over_group' => 12, 'aprice' => 6, 'charge' => array('retail' => 6,))),
		//case I NEW-PLAN-A1 (with two groups) no services
//Test num 15 i1
		array('row' => array('stamp' => 'i1', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 24),
			'expected' => array('in_group' => 24, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 16 i2
		array('row' => array('stamp' => 'i2', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'gr', 'usagev' => 12),
			'expected' => array('in_group' => 12, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 17 i3
		array('row' => array('stamp' => 'i3', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 50),
			'expected' => array('in_group' => 26, 'over_group' => 24, 'aprice' => 12, 'charge' => array('retail' => 12,))),
//Test num 18 i4
		array('row' => array('stamp' => 'i4', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'gr', 'usagev' => 80),
			'expected' => array('in_group' => 38, 'over_group' => 42, 'aprice' => 6.4, 'charge' => array('retail' => 6.4,))),
//Test num 19 i5
		array('rebalance' => true, 'row' => array('stamp' => 'i5', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 50.5),
			'expected' => array('in_group' => 0, 'over_group' => 50.5, 'aprice' => 5.1, 'charge' => array('retail' => 5.1,))),
//		//case J NEW-PLAN-A2 multiple groups with same name
//Test num 20 j1
		array('row' => array('stamp' => 'j1', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 21 j2
		array('row' => array('stamp' => 'j2', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 75, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 70, 'over_group' => 5, 'aprice' => 2.5, 'charge' => array('retail' => 2.5,))),
//Test num 22 j3
		array('row' => array('stamp' => 'j3', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 15, 'charge' => array('retail' => 15,))),
//Test num 23 j4
		array('rebalance' => true, 'row' => array('stamp' => 'j4', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'gr', 'usagev' => 30, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 6, 'charge' => array('retail' => 6,))),
		//case K shared account test
//Test num 24 k1
		array('row' => array('stamp' => 'k1', 'aid' => 7770, 'sid' => 71, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 8, 'services_data' => ['SHARED-SERVICE1'],),
			'expected' => array('in_group' => 8, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 25 k2
		array('rebalance' => true, 'row' => array('stamp' => 'k2', 'aid' => 7770, 'sid' => 72, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 8, 'services_data' => ['SHARED-SERVICE1',],),
			'expected' => array('in_group' => 2, 'over_group' => 6, 'aprice' => 0.6, 'charge' => array('retail' => 0.6,))),
//Test num 26 k3
		array('rebalance' => true, 'row' => array('stamp' => 'k3', 'aid' => 7771, 'sid' => 73, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'SHARED-PLAN-K3', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['SHARED-SERVICE1',],),
			'expected' => array('in_group' => 15, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),
//Test num 27 k4
		array('row' => array('stamp' => 'k4', 'aid' => 7772, 'sid' => 74, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['SHARED-SERVICE1', 'NO-SHARED-SERVICE2',],),
			'expected' => array('in_group' => 15, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),
//Test num 28 k5
		array('rebalance' => true, 'row' => array('stamp' => 'k5', 'aid' => 7772, 'sid' => 75, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['SHARED-SERVICE1', 'NO-SHARED-SERVICE2',],),
			'expected' => array('in_group' => 5, 'over_group' => 15, 'aprice' => 1.5, 'charge' => array('retail' => 1.5,))),
		//old tests
		//case A: PLAN-X3+SERVICE1+SERVICE2
//Test num 29 a1
		array('row' => array('stamp' => 'a1', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 60, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 60, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 30 a2
		array('row' => array('stamp' => 'a2', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 50, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 31 a3
		array('row' => array('stamp' => 'a3', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 50, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 32 a4
		array('row' => array('stamp' => 'a4', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 280, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 55, 'over_group' => 225, 'aprice' => 90, 'charge' => array('retail' => 90,))),
//Test num 33 a5
		array('rebalance' => true, 'row' => array('stamp' => 'a5', 'sid' => 51, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 180, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 0, 'over_group' => 180, 'aprice' => 18, 'charge' => array('retail' => 18,))),
		//case B: PLAN-X3+SERVICE3
//Test num 34 b1
		array('row' => array('stamp' => 'b1', 'sid' => 52, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 120, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 120, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 35 b2
		array('row' => array('stamp' => 'b2', 'sid' => 52, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 110.5, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 110.5, 'aprice' => 11.1, 'charge' => array('retail' => 11.1,))),
//Test num 36 b3
		array('row' => array('stamp' => 'b3', 'sid' => 52, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 20, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 37 b5
		array('rebalance' => true, 'row' => array('stamp' => 'b5', 'sid' => 52, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 8, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 8, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),
//		//case C: PLAN-A0 (without groups)+SERVICE1+SERVICE4  
////Test num 38 c1
		array('row' => array('stamp' => 'c1', 'sid' => 53, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 35, 'services_data' => ['SERVICE4',],),
			'expected' => array('in_group' => 35, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 39 c2
		array('row' => array('stamp' => 'c2', 'sid' => 53, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 35.5, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 35.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 40 c3
		array('row' => array('stamp' => 'c3', 'sid' => 53, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 180, 'services_data' => ['SERVICE4',],),
			'expected' => array('in_group' => 165, 'over_group' => 15, 'aprice' => 3, 'charge' => array('retail' => 3,))),
//Test num 41 c4
		array('row' => array('stamp' => 'c4', 'sid' => 53, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 4.5, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 4.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 42 c5
		array('rebalance' => true, 'row' => array('stamp' => 'c5', 'sid' => 53, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 12, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 12, 'aprice' => 6, 'charge' => array('retail' => 6,))),
		//case D PLAN-A1 (with two groups) no services
//Test num 43 d1
		array('row' => array('stamp' => 'd1', 'sid' => 54, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 24),
			'expected' => array('in_group' => 24, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 44 d2
		array('row' => array('stamp' => 'd2', 'sid' => 54, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 12),
			'expected' => array('in_group' => 12, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 45 d3
		array('row' => array('stamp' => 'd3', 'sid' => 54, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50),
			'expected' => array('in_group' => 26, 'over_group' => 24, 'aprice' => 12, 'charge' => array('retail' => 12,))),
//Test num 46 d4
		array('row' => array('stamp' => 'd4', 'sid' => 54, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 80),
			'expected' => array('in_group' => 38, 'over_group' => 42, 'aprice' => 8.4, 'charge' => array('retail' => 8.4,))),
//Test num 47 d5
		array('rebalance' => true, 'row' => array('stamp' => 'd5', 'sid' => 54, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50.5),
			'expected' => array('in_group' => 0, 'over_group' => 50.5, 'aprice' => 5.1, 'charge' => array('retail' => 5.1,))),
//		//case E PLAN-A2 multiple groups with same name
////Test num 48 e1
		array('row' => array('stamp' => 'e1', 'aid' => 552, 'sid' => 55, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 49 e2
		array('row' => array('stamp' => 'e2', 'aid' => 552, 'sid' => 55, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 75, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 50, 'over_group' => 25, 'aprice' => 12.5, 'charge' => array('retail' => 12.5,))),
//Test num 50 e3
		array('row' => array('stamp' => 'e3', 'aid' => 552, 'sid' => 55, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 15, 'charge' => array('retail' => 15,))),
//Test num 51 e4
		array('rebalance' => true, 'row' => array('stamp' => 'e4', 'aid' => 552, 'sid' => 55, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'gr', 'usagev' => 30, 'services_data' => ['SERVICE1']),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 6, 'charge' => array('retail' => 6,))),
//		/*		 * ** NEW TEST CASES *** */
//		//case L cost
////Test num 52 l1
		array('row' => array('stamp' => 'l1', 'aid' => 23457, 'sid' => 77, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-Z5', 'usaget' => 'gr', 'usagev' => 240, 'services_data' => ['NEW-SERVICE5']),
			'expected' => array('in_group' => 30, 'over_group' => 18, 'aprice' => 18, 'charge' => array('retail' => 18,))),
//Test num 53 l2
		array('row' => array('stamp' => 'l2', 'aid' => 23457, 'sid' => 78, 'rates' => array('RATE-L3' => 'retail'), 'plan' => 'PLAN-L2', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-L3',],),
			'expected' => array('in_group' => 30, 'over_group' => 210, 'aprice' => 21, 'charge' => array('retail' => 21,))),
//Test num 54 l3
		array('rebalance' => true, 'row' => array('stamp' => 'l3', 'aid' => 23457, 'sid' => 79, 'rates' => array('RATE-L3' => 'retail'), 'plan' => 'PLAN-L3', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-L2',],),
			'expected' => array('in_group' => 12, 'over_group' => 12, 'aprice' => 12, 'charge' => array('retail' => 12,))),
////Test num 55 l4
		array('rebalance' => true, 'row' => array('stamp' => 'l4', 'aid' => 23458, 'sid' => 80, 'rates' => array('RATE-L3' => 'retail'), 'plan' => 'PLAN-L4-SHARED', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-L2',],),
			'expected' => array('in_group' => 35, 'over_group' => 205, 'aprice' => 20.5, 'charge' => array('retail' => 20.5,))),
//		//case M pooled account services
////Test num 56 m1
		array('rebalance' => true, 'row' => array('stamp' => 'm1', 'aid' => 8880, 'sid' => 800, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 10, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 10, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
////Test num 57 m2
		array('rebalance' => true, 'row' => array('stamp' => 'm2', 'aid' => 8881, 'sid' => 801, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
////Test num 58 m3
		array('rebalance' => true, 'row' => array('stamp' => 'm3', 'aid' => 8882, 'sid' => 803, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 10, 'over_group' => 10, 'aprice' => 1, 'charge' => array('retail' => 1,))),
//Test num 59 m4
		array('rebalance' => true, 'row' => array('stamp' => 'm4', 'aid' => 8883, 'sid' => 804, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 15, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 10, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),
////Test num 60 m5
		array('row' => array('stamp' => 'm5', 'aid' => 8884, 'sid' => 806, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'POOLED-PLAN-1', 'usaget' => 'call', 'usagev' => 25, 'services_data' => ['POOLED-SERVICE12', 'POOLED-SERVICE11',],),
			'expected' => array('in_group' => 25, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 61 m6
		array('rebalance' => true, 'row' => array('stamp' => 'm6', 'aid' => 8884, 'sid' => 807, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'POOLED-PLAN-1', 'usaget' => 'call', 'usagev' => 10, 'services_data' => ['POOLED-SERVICE12', 'POOLED-SERVICE11',],),
			'expected' => array('in_group' => 5, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),
////Test num 62 m7
		array('rebalance' => true, 'row' => array('stamp' => 'm7', 'aid' => 8885, 'sid' => 809, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 100, 'services_data' => ['POOLED-SERVICE3',],),
			'expected' => array('in_group' => 60, 'over_group' => 40, 'aprice' => 4, 'charge' => array('retail' => 4,))),
//		// case N - new structure support multiple usage types
////Test num 63 n1
		array('row' => array('stamp' => 'n1', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N1' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 125, 'services_data' => ['SERVICE-N1',],),
			'expected' => array('in_group' => 125, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		// N2 - depend on N1
//Test num 64 n2
		array('row' => array('stamp' => 'n2', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N1b' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'incoming_call', 'usagev' => 275, 'services_data' => ['SERVICE-N1',],),
			'expected' => array('in_group' => 175, 'over_group' => 100, 'aprice' => 10, 'charge' => array('retail' => 10,))),
//Test num 65 n3
		array('row' => array('stamp' => 'n3', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N3' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-N3',],),
			'expected' => array('in_group' => 240, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		// N4 - depend on N1
//Test num 66 n4
		array('row' => array('stamp' => 'n4', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N3' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 475, 'services_data' => ['SERVICE-N3'],),
			'expected' => array('in_group' => 60, 'over_group' => 415, 'aprice' => 7, 'charge' => array('retail' => 7,))),
//Test num 67 n5
		array('row' => array('stamp' => 'n5', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N5' => 'retail'), 'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5),
			'expected' => array('in_group' => 5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 68 n6
		array('row' => array('stamp' => 'n6', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N5' => 'retail'), 'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5),
			'expected' => array('in_group' => 5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 69 n7
		array('rebalance' => true, 'row' => array('stamp' => 'n7', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N5' => 'retail'), 'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5),
			'expected' => array('in_group' => 0, 'over_group' => 5, 'aprice' => 2.5, 'charge' => array('retail' => 2.5,))),
//		// case O - custom period balance support
////Test num 70 o1
		array('row' => array('stamp' => 'o1', 'aid' => 9501, 'sid' => 950, 'rates' => array('RATE-O1' => 'retail'), 'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 35, 'services_data' => [['name' => 'SERVICE-O1', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-01 09:00:00+03:00'),
			'expected' => array('in_group' => 35, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 71 o2
		array('row' => array('stamp' => 'o2', 'aid' => 9501, 'sid' => 950, 'rates' => array('RATE-O1' => 'retail'), 'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 62, 'services_data' => [['name' => 'SERVICE-O1', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-16T09:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 62, 'aprice' => 0.62, 'charge' => array('retail' => 0.62,))),
//Test num 72 o3
		array('rebalance' => true, 'row' => array('stamp' => 'o3', 'aid' => 9501, 'sid' => 950, 'rates' => array('RATE-O2' => 'retail'), 'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 45, 'services_data' => [['name' => 'SERVICE-O1', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14 09:00:00+03:00',),
			'expected' => array('in_group' => 25, 'over_group' => 20, 'aprice' => 0.02, 'charge' => array('retail' => 0.02,))),
//		// O4- plan includes - use all
////Test num 73 o4
		array('row' => array('stamp' => 'o4', 'aid' => 9502, 'sid' => 951, 'rates' => array('RATE-O4' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 40, 'services_data' => [['name' => 'SERVICE-O4', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14 09:00:00+03:00',),
			'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 74 o5  try to use service includes
		array('row' => array('stamp' => 'o5', 'aid' => 9502, 'sid' => 951, 'rates' => array('RATE-O4' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 30, 'services_data' => [['name' => 'SERVICE-O4', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		// O6- plan includes - use part of it
		// O7 - try to use service includes
		// p1 service with limited cycle's 
//Test num 75 o6
		array('rebalance' => true, 'row' => array('stamp' => 'o6', 'aid' => 9502, 'sid' => 951, 'rates' => array('RATE-O4' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-O4', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14',),
			'expected' => array('in_group' => 70, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),
////Test num 76 p1
		array('row' => array('stamp' => 'p1', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 7500000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-09-01',),
			'expected' => array('in_group' => 7500000, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 77 p2
		array('row' => array('stamp' => 'p2', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-09-14',),
			'expected' => array('in_group' => 75000000, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 78 p3
		array('row' => array('stamp' => 'p3', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-09-30',),
			'expected' => array('in_group' => 75000000, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 79 p4
		array('row' => array('stamp' => 'p4', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 7500000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-10-01 00:00:01+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 7500000, 'aprice' => 8, 'charge' => array('retail' => 8,))),
//Test num 80 p5
		array('rebalance' => true, 'row' => array('stamp' => 'p5', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-10-14',),
			'expected' => array('in_group' => 0, 'over_group' => 75000000, 'aprice' => 75, 'charge' => array('retail' => 75,))),
////Test num 81 q1
		array('row' => array('stamp' => 'q1', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 70, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-25 11:00:00+03:00',),
			'expected' => array('in_group' => 70, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 82 q2
		array('row' => array('stamp' => 'q2', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q2' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 30, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-26 11:00:00+03:00',),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 83 q3
		array('row' => array('stamp' => 'q3', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q2' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-23 11:00:00+03:00',),
			'expected' => array('in_group' => 150, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 84 q4
		array('rebalance' => true, 'row' => array('stamp' => 'q4', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 250, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-27 11:00:00+03:00',),
			'expected' => array('in_group' => 120, 'over_group' => 130, 'aprice' => 1.3, 'charge' => array('retail' => 1.3,))),
////Test num 85 r1
		array('row' => array('stamp' => 'r1', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 235, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-11 11:00:00+03:00',),
			'expected' => array('in_group' => 235, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 86 r2
		array('row' => array('stamp' => 'r2', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 245, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-12 11:00:00+03:00',),
			'expected' => array('in_group' => 245, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 87 r3
		array('row' => array('stamp' => 'r3', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 70, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-13 11:00:00+03:00',),
			'expected' => array('in_group' => 20, 'over_group' => 50, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),
//Test num 88 r4
		array('row' => array('stamp' => 'r4', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 120, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-09 11:00:00+03:00'),
			'expected' => array('in_group' => 0, 'over_group' => 120, 'aprice' => 1.2, 'charge' => array('retail' => 1.2,))),
//Test num 89 r5
		array('row' => array('stamp' => 'r5', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 60, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-21 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 60, 'aprice' => 0.6, 'charge' => array('retail' => 0.6,))),
//Test num 90 r6
		array('rebalance' => true, 'row' => array('stamp' => 'r6', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),
//		//Included services
//		//is1 should be included
////Test num 91 is1
		array('row' => array('stamp' => 'is1', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 75, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//is2 after the service time  (by the service price cycles not the plan de-activation)
//Test num 92 is2
		array('row' => array('stamp' => 'is2', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-11-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),
//should be half included
//Test num 93 is4
		array('row' => array('stamp' => 'is4', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 25, 'over_group' => 50, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),
////should not be included
////Test num 94 is5
		array('rebalance' => true, 'row' => array('stamp' => 'is5', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),
//		// s custom period with pooled/shard
//		// s1 & s2 are one test case for check service period pooled
////Test num 95 s1
		array('row' => array('stamp' => 's1', 'aid' => 24, 'sid' => 25, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 15, 'services_data' => [['name' => 'PERIOD_POOLED', 'from' => '2017-08-01', 'to' => '2017-09-01']], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 15, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 96 s2
		array('rebalance' => true, 'row' => array('stamp' => 's2', 'aid' => 24, 'sid' => 26, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 10, 'services_data' => [['name' => 'PERIOD_POOLED', 'from' => '2017-08-01', 'to' => '2017-09-01',]], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 5, 'over_group' => 5, 'aprice' => 5, 'charge' => array('retail' => 5,))),
		//s3 & s4 are one test case for check service period shard
////Test num 97 s3
		array('row' => array('stamp' => 's3', 'aid' => 27, 'sid' => 28, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 20, 'services_data' => [['name' => 'PERIOD_SHARED', 'from' => '2017-08-01', 'to' => '2017-09-01']], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//Test num 98 s4
		array('row' => array('stamp' => 's4', 'aid' => 27, 'sid' => 29, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 15, 'services_data' => [['name' => 'PERIOD_SHARED', 'from' => '2017-08-01', 'to' => '2017-09-01'],], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 10, 'over_group' => 5, 'aprice' => 5, 'charge' => array('retail' => 5,))),
		//T test for wholesale
//Test num 99 t1
		array('row' => array('stamp' => 't1', 'aid' => 27, 'sid' => 30, 'rates' => array('NEW-CALL-USA' => 'retail', 'CALL' => 'wholesale'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 60, 'urt' => '2017-08-14 11:00:00+03:00'),
			'expected' => array('in_group' => 0, 'over_group' => 60, 'aprice' => 30, 'charge' => array('retail' => 30, 'wholesale' => 60))),
		//test for prepriced line
//Test num 100 u1
		array('rebalance' => true, 'row' => array('stamp' => 'u1', 'aid' => 27, 'sid' => 31, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'prepriced' => "true", 'type' => 'Preprice_Dynamic', 'uf' => array('preprice' => 100), 'usaget' => 'call', 'usagev' => 15, 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 15, 'aprice' => 100, 'charge' => array('retail' => 100,))),
////Test num 101 v1
//// Service price overriding
		array('row' => array('stamp' => 'v1', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 21, 'services_data' => [['name' => 'SERVICE_OVERRIDE_PRICE', 'service_id' => 20003,'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 21, 'aprice' => 3.22222, 'charge' => array('retail' => 3.22222,))),
//Test num 102 v2
// Service price overriding
		array('row' => array('stamp' => 'v2', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 21, 'services_data' => [['name' => 'SERVICE_INCLUDE_PLUS_OVERRIDE_PRICE', 'service_id' => 20004, 'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 10, 'over_group' => 11, 'aprice' => 3.555555, 'charge' => array('retail' => 3.555555,))),
//Test num 103 v3
// Service price overriding
		array('row' => array('stamp' => 'v3', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 80, 'services_data' => [['name' => 'SERVICE_OVERRIDE_PRICE', 'service_id' => 20003,'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 30, 'aprice' => 12.22222, 'charge' => array('retail' => 12.22222,))),
////Test num 104 v4
// Service price overriding - service wins if both service and plan override the same product
		array('rebalance' => true, 'row' => array('stamp' => 'v4', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW_PLAN_OVERRIDE_USA', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 10, 'services_data' => [['name' => 'SERVICE_OVERRIDE_PRICE', 'service_id' => 20003, 'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 10, 'aprice' => 1.11111, 'charge' => array('retail' => 1.11111,))),
//		//Test num 105 w1
////Service quantity based quota
//		//half use
		array('row' => array('stamp' => 'w1', 'aid' => 35, 'sid' => 36, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'MUL', 'service_id' => 10001, 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-14 23:11:45+03:00',),
			'expected' => array('in_group' => 150, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//full use
		array('row' => array('stamp' => 'w2', 'aid' => 35, 'sid' => 36, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'MUL', 'service_id' => 10001, 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-15 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
//		//over use
		array('rebalance' => true, 'row' => array('stamp' => 'w3', 'aid' => 35, 'sid' => 36, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'MUL', 'service_id' => 10001, 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),
		//1st sub multiple quantity by 1 ,2nd multiple quantity by 2, check : half use ,full use, over use
//		//half use
		array('row' => array('stamp' => 'w4', 'aid' => 37, 'sid' => 38, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		array('row' => array('stamp' => 'w5', 'aid' => 37, 'sid' => 39, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//full use
		array('row' => array('stamp' => 'w6', 'aid' => 37, 'sid' => 38, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 100, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 100, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		array('row' => array('stamp' => 'w7', 'aid' => 37, 'sid' => 39, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 100, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 100, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//over use
		array('row' => array('stamp' => 'w8', 'aid' => 37, 'sid' => 38, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 150, 'aprice' => 150, 'charge' => array('retail' => 150,))),
		array('rebalance' => true, 'row' => array('stamp' => 'w9', 'aid' => 37, 'sid' => 39, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 150, 'aprice' => 150, 'charge' => array('retail' => 150,))),
		//both subs multiple quantity by 1 , check : half use ,full use, over use
		//half use
		array('row' => array('stamp' => 'w10', 'aid' => 40, 'sid' => 41, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		array('row' => array('stamp' => 'w11', 'aid' => 40, 'sid' => 42, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//full use
		array('row' => array('stamp' => 'w12', 'aid' => 40, 'sid' => 41, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		array('row' => array('stamp' => 'w13', 'aid' => 40, 'sid' => 42, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//over use
		array('row' => array('stamp' => 'w14', 'aid' => 40, 'sid' => 41, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),
		array('rebalance' => true, 'row' => array('stamp' => 'w15', 'aid' => 40, 'sid' => 42, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),
		//1st sub multiple quantity by 1 ,2nd not have any group
		array('rebalance' => true, 'row' => array('stamp' => 'w16', 'aid' => 43, 'sid' => 44, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		array('rebalance' => true, 'row' => array('stamp' => 'w17', 'aid' => 43, 'sid' => 45, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),
//		//BRCD-2581 usage volume 0 - check the price isn't NaN
		array('row' => array('stamp' => 'w18', 'aid' => 100, 'sid' => 101, 'rates' => array('pricing_method_volume' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 0, 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//w19 + w20 are test for https://billrun.atlassian.net/browse/BRCD-1493
		array('row' => array('stamp' => 'w19', 'aid' => 50, 'sid' => 551, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 110, 'urt' => '2018-11-16 23:11:45+03:00',
				'services_data' => [
					['name' => 'CALL', 'from' => '2017-08-01', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "123", "quantity" => 1],
					['name' => 'CALL', 'from' => '2017-08-01', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "1234", "quantity" => 1]
				]),
			'expected' => array('in_group' => 110, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 110,))),
		array('rebalance' => true, 'row' => array('stamp' => 'w20', 'aid' => 50, 'sid' => 551, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 100, 'urt' => '2018-11-16 23:11:45+03:00',
				'services_data' => [
					['name' => 'CALL', 'from' => '2017-08-01 ', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "123", "quantity" => 1],
					['name' => 'CALL', 'from' => '2017-08-01', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "1234", "quantity" => 1]
				]),
			'expected' => array('in_group' => 90, 'over_group' => 10, 'aprice' => 10, 'charge' => array('retail' => 110,))),
		/* test for BRCD-2627 - subscriber purchase service for one cycle at 2020-05-10, the cdr is from 2020-06-05, it will not create/use in the service balance */
		array('row' => array('stamp' => 'x1', 'aid' => 52, 'sid' => 53, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [
					["name" => "ONE_CYCLE_ADDON",
						"from" => "2020-05-10",
						"to" => "2130-09-01",
						"service_id" => "123",
						"creation_time" => "2020-05-10"],
				],
				'urt' => '2020-06-05',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),
		//BRCD-2865 Override product's price by percentage 
		//subscriber without override plan - will charge full price 
		array('rebalance' => true, 'row' => array('stamp' => 'x2', 'aid' => 52, 'sid' => 54, 'rates' => array('CALL_OVERRIDE_B' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
				'urt' => '2020-06-05',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),
		//BRCD-2865 Override product's price by percentage 
		//subscriber with override plan - override 100% will charge 0 
		array('row' => array('stamp' => 'x3', 'aid' => 52, 'sid' => 5555, 'rates' => array('CALL_OVERRIDE_B' => 'retail'), 'plan' => 'OVERRIDE', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
				'urt' => '2020-06-05 ',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//BRCD-2865 Override product's price by percentage 
		//subscriber with override plan - override 50% will charge 50%
		array('rebalance' => true, 'row' => array('stamp' => 'x4', 'aid' => 52, 'sid' => 5555, 'rates' => array('CALL_OVERRIDE_A' => 'retail'), 'plan' => 'OVERRIDE', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
				'urt' => '2020-06-05',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 25, 'charge' => array('retail' => 25,))),
		//BRCD-2865 Override product's price by percentage 
		//subscriber without override service - will charge full price 
		array('rebalance' => true, 'row' => array('stamp' => 'x5', 'aid' => 52, 'sid' => 54, 'rates' => array('CALL_OVERRIDE_SERVICE_A' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
				'urt' => '2020-06-05',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),
		//BRCD-2865 Override product's price by percentage 
		//subscriber with override services - override 100% will charge 0 
		array('row' => array('stamp' => 'x6', 'aid' => 52, 'sid' => 56, 'rates' => array('CALL_OVERRIDE_SERVICE_B' => 'retail'), 'plan' => 'OVERRIDE', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
				'urt' => '2020-06-05', 'services_data' => [
					["name" => "OVERRIDE_BY_PERCENTAGE",
						"from" => "2020-05-10",
						"to" => "2130-09-01",
						"service_id" => "123",
						"creation_time" => "2020-05-10"],
				],),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 0, 'charge' => array('retail' => 0,))),
		//BRCD-2865 Override product's price by percentage 
		//subscriber with override service - override 50% will charge 50%
		array('rebalance' => true, 'row' => array('stamp' => 'x7', 'aid' => 52, 'sid' => 56, 'rates' => array('CALL_OVERRIDE_SERVICE_A' => 'retail'), 'plan' => 'OVERRIDE', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
				'urt' => '2020-06-05', 'services_data' => [
					["name" => "OVERRIDE_BY_PERCENTAGE",
						"from" => "2020-05-10",
						"to" => "2130-09-01",
						"service_id" => "123",
						"creation_time" => "2020-05-10"],
				],),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 25, 'charge' => array('retail' => 25,))),
		//test rebalance conditions part a
		array('row' => array('stamp' => 'xa1', "aid" => 741, 'sid' => 147, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1']),
			'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0)),),
		//part b
		array('rebalance' => true, 'conditions' => [741 => [[[['field_name' => 'usaget', 'op' => '$eq', 'value' => 'gr']]]]], 'groupExpected' =>
			[
				"NEW-SERVICE1" => ["AAA" => ["count" => 1, "left" => 0, "total" => 40, "usagev" => 40]],
				"NEW-SERVICE4" => ["SR4" => ["count" => 0, "left" => 200, "total" => 200, "usagev" => 0]],
			],
			'row' => array('stamp' => 'xa2', 'aid' => 741, 'sid' => 147, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'PLAN_A', 'usaget' => 'gr', 'usagev' => 40, 'services_data' => ['NEW-SERVICE4']),
			'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),
	];

	protected $stampsToRun = []; // empty means "run all"

	public function __construct($label = false) {
		parent::__construct("test UpdateRow");
		$request = new Yaf_Request_Http;
		$this->runRebalnce = $request->get('rebalance');
		date_default_timezone_set('Asia/Jerusalem');
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->balancesCol = Billrun_Factory::db()->balancesCollection();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		$this->construct(null, ['lines', 'balances', 'rebalance_queue']);
		$this->setColletions();
		$this->loadDbConfig();
	}

	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}

	public function testUpdateRow() {
		//running test
		foreach ($this->rows as $key => $row) {
			if (!empty($this->stampsToRun) && !in_array($row['row']['stamp'], $this->stampsToRun)) {
				continue;
			}

			$this->row = $row;
			$this->message .= "<span id={$row['row']['stamp']}>test stamp : " . $row['row']['stamp'] . '</span><br>';
			$fixrow = $this->fixRow($row['row'], $key);
			$this->linesCol->insert($fixrow, ['w' => 'majority']);
			$updatedRow = $this->runT($fixrow['stamp']);
			$result = $this->compareExpected($key, $updatedRow, $row);
			if ($this->runRebalnce) {
				if (isset($row['rebalance']) && $row['rebalance'] == true) {
					$this->balances = $this->getBalance($updatedRow);
					$conditions = isset($row['conditions']) ? $row['conditions'] : null;
					$this->rebalance($updatedRow, $conditions);
				}
			}
			$fail = $this->assertTrue($result[0]);
			if (!$fail) {
				$this->reportFail();
			}
			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
		}
		if ($this->fails) {
			foreach ($this->fails as $fail) {
				$this->message .= $fail;
			}
		}
		print_r($this->message);

		$this->restoreColletions();
	}

	public function reportFail() {
		if (!array_key_exists($this->row['row']['stamp'], $this->fails)) {
			$this->fails[$this->row['row']['stamp']] = "|---|<a href='#{$this->row['row']['stamp']}'>{$this->row['row']['stamp']}</a>";
		}
	}

	protected function getBalance($row) {
		$balances = array();
		$searchQuery = ["aid" => $row['aid']];
		$cursor = Billrun_Factory::db()->balancesCollection()->query($searchQuery)->cursor()->limit(100000);
		foreach ($cursor as $balance) {
			$balances[] = $balance->getRawData();
		}
		return $balances;
	}

	/**
	 * sent  account to rebalance and calls the appropriate test function
	 * @param type $row the row of tested account
	 * @param type $conditions if it  pass so the  rebalance is conditional  
	 */
	protected function rebalance($row, $conditions = null) {
		$aids = [$row['aid']];
		$billrun_key = $row['billrun'];
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/process_time_offset.ini');
		$rebalance = new ResetLinesModel($aids, $billrun_key, $conditions);
		$rebalance->reset();
		$this->balances = $this->getBalance($row);
		if (isset($this->row['conditions'])) {
			$this->conditionRebalance();
		} else {
			$this->checkRebalance($aids, $billrun_key, $this->balances);
		}
	}

	/**
	 * in case of conditional rebalance this function is test by defined expected values in the test case VS actual result 
	 */
	public function conditionRebalance() {
		$expected = $this->row['groupExpected'];
		$result = $this->balances;
		$this->message .= "<b>check that only groups who met the conditions are reset</b><br>";
		foreach ($result as $balances => $balance) {
			$this->message .= "<b>group  name :	" . ($balance['service_name'] ?? '') .  "</b><br>";
			if (isset($balance['service_name']) && array_key_exists($balance['service_name'], $expected)) {
				foreach ($expected[$balance['service_name']] as $key => $unused) {
					$expectedKey = $key;
					break;
				}

				foreach ($balance['balance']['groups'][$expectedKey] as $filde => $value) {
					if ($value != $expected[$balance['service_name']][$expectedKey][$filde]) {
						$this->message .= "$filde expected to be: {$expected[$balance['service_name']][$expectedKey][$filde]}, result : $value " . $this->fail;
						$this->assertTrue(0);
						$this->reportFail();
					} else {
						$this->message .= "$filde  : $value " . $this->pass;
						$this->assertTrue(1);
					}
				}
			}
		}
	}

	/**
	 * get balance and check if $resetFildes all values from $resetFildes are rest
	 * @param type $balances
	 */
	public function RecorsiveTest($balances) {
		$resetFildes = ['cost', 'count', 'usagev'];
		foreach ($balances as $key => $value) {
			if (is_array($value) && !empty($value)) {
				$this->message .= " $key . ";
				$this->RecorsiveTest($value);
			}
			if (in_array($key, $resetFildes)) {
				if (!Billrun_Util::isEqual($value, 0, Billrun_Bill::precision)) {
					$this->message .= $key . " : " . $value . $this->fail;
					$this->assertTrue(0);
					$pass = false;
					$this->reportFail();
				} else {
					$this->message .= $key . " : " . $value . $this->pass;
					$this->assertTrue(1);
				}
			}
		}
	}

	/**
	 *  call $this->RecorsiveTest for each relevant balance and add info to message about the  test 
	 * @param type $aids to check 
	 * @param type $billrun_key
	 * @param type $balances to check 
	 */
	public function checkRebalance($aids, $billrun_key, $balances) {
		$this->message .= "<b>rebalance test : </b><br>";
		$balancesToTest = array_filter($balances, function ($balance_) {
			if ($balance_['sid'] == 0 || $balance_['sid'] == $this->row['row']['sid']) {
				return $balance_;
			}
		});
		foreach ($balancesToTest as $balance) {
			$pass = true;
			$this->message .= "</br>AID {$balance['aid']}, SID : {$balance['sid']}<br>";
			$service_name = isset($balance['service_name']) ? $balance['service_name'] : 'defualt';
			$this->message .= "<b>balance name :	$service_name </b><br>";
			$this->RecorsiveTest($balance);
		}
	}

	protected function runT($stamp) {
		$entity = $this->linesCol->setReadPreference('RP_PRIMARY')->query(array('stamp' => $stamp))->cursor()->current();
		$ret = $this->calculator->updateRow($entity);
		$this->calculator->writeLine($entity, '123');
		$this->calculator->removeBalanceTx($entity);
		$entityAfter = $entity->getRawData();
		return ($entityAfter);
	}

	protected function compareExpected($key, $returnRow, $row) {

		$charge = (array_key_exists('charge', $row["expected"])) ? $row["expected"]['charge'] : '';
		$passed = True;
		$epsilon = 0.000001;
		$inGroupE = $row["expected"]['in_group'];
		$out_group = isset($row["expected"]['out_group']) ? $row["expected"]['out_group'] : null;
		$overGroupE = $row["expected"]['over_group'];
		$aprice = round(10 * ($row["expected"]['aprice']), (1 / $epsilon)) / 10;
		$this->message .= "<b>customer pricing  test : </b><br>";
		$this->message .= '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . ($key + 1) . '(#' . $returnRow['stamp'] . '). <b> Expected: </b> <br> — aprice: ' . $aprice . '<br> — in_group: ' . $inGroupE . '<br> — over_group: ' . $overGroupE . '<br>';
		if (is_array($charge)) {
			foreach ($charge as $key => $value) {
				$this->message .= "— $key : $value <br>";
			}
		}
		$this->message .= '<b> Result: </b> <br>';
		$this->message .= '— aprice: ' . $returnRow['aprice'];

		if (Billrun_Util::isEqual($returnRow['aprice'], $aprice, $epsilon)) {

			$this->message .= $this->pass;
		} else {
			$this->message .= $this->fail;
			$passed = False;
		}
		if (!empty($charge)) {
			foreach ($charge as $category => $price) {
				$checkRate = current(array_filter($returnRow['rates'], function(array $cat) use ($category) {
						return $cat['tariff_category'] === $category;
					}));
				if (!empty($checkRate)) {
					//when the tariff_category is retail check if aprice equle to him charge
					if ($checkRate['tariff_category'] == 'retail') {
						if (Billrun_Util::isEqual($aprice, $checkRate['pricing']['charge'], $epsilon)) {
							$this->message .= "— $category equle to aprice  $this->pass ";
						} else {
							$this->message .= "— The difference between $category vs aprice its " . abs($aprice - $price) . "$this->fail";
							$passed = False;
						}
						//check if the charge is currect 
						if (Billrun_Util::isEqual($aprice, $checkRate['pricing']['charge'], $epsilon)) {
							$this->message .= "— $category {$checkRate['pricing']['charge']} $this->pass ";
						} else {
							$this->message .= "— $category {$checkRate['pricing']['charge']} $this->fail";
							$passed = False;
						}
					}
				} else {
					$passed = False;
				}
			}
		}
		if ($inGroupE == 0) {
			if ((!isset($returnRow['in_group'])) || Billrun_Util::isEqual($returnRow['in_group'], 0, $epsilon)) {
				$this->message .= '— in_group: 0' . $this->pass;
			} else {
				$this->message .= '— in_group: ' . $returnRow['in_group'] . $this->fail;
				$passed = False;
			}
		} else {
			if (!isset($returnRow['in_group'])) {
				$this->message .= '— in_group: 0' . $this->fail;
				$passed = False;
			} else if (!Billrun_Util::isEqual($returnRow['in_group'], $inGroupE, $epsilon)) {
				$this->message .= '— in_group: ' . $returnRow['in_group'] . $this->fail;
				$passed = False;
			} else {
				$this->message .= '— in_group: ' . $returnRow['in_group'] . $this->pass;
			}
		}
		if ($overGroupE == 0) {
			if (((!isset($returnRow['over_group'])) || (Billrun_Util::isEqual($returnRow['over_group'], 0, $epsilon))) && ((!isset($returnRow['out_plan'])) || (Billrun_Util::isEqual($returnRow['out_plan'], 0, $epsilon)))) {
				$this->message .= '— over_group and out_plan: doesnt set' . $this->pass;
			} else {
				if (isset($returnRow['over_group'])) {
					$this->message .= '— over_group: ' . $returnRow['over_group'] . $this->fail;
					$passed = False;
				}
			}
		} else {
			if ((!isset($returnRow['over_group'])) && (!isset($returnRow['out_plan']))) {
				$this->message .= '— over_group and out_plan: dont set' . $this->fail;
				$passed = False;
			} else if (isset($returnRow['over_group'])) {
				if (!Billrun_Util::isEqual($returnRow['over_group'], $overGroupE, $epsilon)) {
					$this->message .= '— over_group: ' . $returnRow['over_group'] . $this->fail;
					$passed = False;
				} else {
					$this->message .= '— over_group: ' . $returnRow['over_group'] . $this->pass;
				}
			}
		}
		if (isset($out_group)) {
			if (Billrun_Util::isEqual($returnRow['out_group'], $out_group, $epsilon)) {
				$this->message .= '— out_group: ' . $returnRow['out_group'] . $this->pass;
			} else {
				$this->message .= '— out_group: ' . $returnRow['out_group'] . $this->fail;
				$passed = False;
			}
		}
		$this->message .= ' </p>';
		return [$passed, $this->message];
	}

	protected function calcServiceId($aid = 12345770, $sid, $service) {
		$countChar = 0;
		foreach (str_split($service) as $char) {
			$countChar += ord($char);
		}

		return $aid * $sid * $countChar;
	}

	protected function fixRow($row, $key) {

		if (!array_key_exists('process_time', $row)) {
			$row['process_time'] = new MongoDate(time() - 200);
		} else {
			$row['process_time'] = new MongoDate(strtotime($row['urt']) - 200);
		}
		if (!array_key_exists('urt', $row)) {
			$row['urt'] = new MongoDate(time() + $key);
		} else {
			$row['urt'] = new MongoDate(strtotime($row['urt']));
		}
		if (!isset($row['aid'])) {
			$row['aid'] = 1234;
		}
		if (!isset($row['sid'])) {
			$row['sid'] = 1234;
		}
		if (!isset($row['type'])) {
			$row['type'] = 'mytype';
		}
		if (!isset($row['usaget'])) {
			$row['usaget'] = 'call';
		}
		if (isset($row['services_data'])) {
			foreach ($row['services_data'] as $key => $service) {
				if (!is_array($service)) {
					$row['services_data'][$key] = array('name' => $service);
					$row['services_data'][$key]['service_id'] = $this->calcServiceId($row['aid'], $row['sid'], $service);
				}
				if (isset($service['from'])) {
					$row['services_data'][$key]['from'] = new MongoDate(strtotime($service['from']));
				}
				if (isset($service['to'])) {
					$row['services_data'][$key]['to'] = new MongoDate(strtotime($service['to']));
				}
				if (isset($service['creation_time'])) {
					$row['services_data'][$key]['creation_time'] = new MongoDate(strtotime($service['creation_time']));
				}
			}
		}

		if (isset($row['arate_key'])) {
			$row['rates'] = array($row['arate_key'] => 'retail');
		}
		$keys = [];
		foreach ($row['rates'] as $rate_key => $tariff_category) {
			$rate = $this->ratesCol->setReadPreference('RP_PRIMARY')->query(array('key' => $rate_key))->cursor()->current();
			$keys[] = array(
				'rate' => MongoDBRef::create('rates', (new MongoId((string) $rate['_id']))),
				'tariff_category' => $tariff_category,
			);
		}
		$row['rates'] = $keys;
		$plan = $this->plansCol->query(array('name' => $row['plan']))->cursor()->current();
		$row['plan_ref'] = MongoDBRef::create('plans', (new MongoId((string) $plan['_id'])));
		return $row;
	}

}
