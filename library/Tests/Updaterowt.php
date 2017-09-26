<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Updaterowt extends UnitTestCase {

	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
	protected $servicesToUse = ["SERVICE1", "SERVICE2"];
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';
	protected $rows = [
		//New tests for new override price and includes format
		//case F: NEW-PLAN-X3+NEW-SERVICE1+NEW-SERVICE2
		array('stamp' => 'f1', 'sid' => 62, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-X3', 'usagev' => 60, 'services_data' => ["NEW-SERVICE1", "NEW-SERVICE2"]),
		array('stamp' => 'f2', 'sid' => 62, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-X3', 'usagev' => 50, 'services_data' => ["NEW-SERVICE1", "NEW-SERVICE2"]),
		array('stamp' => 'f3', 'sid' => 62, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-X3', 'usagev' => 50, 'services_data' => ["NEW-SERVICE1", "NEW-SERVICE2"]),
		array('stamp' => 'f4', 'sid' => 62, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-X3', 'usagev' => 280, 'services_data' => ["NEW-SERVICE1", "NEW-SERVICE2"]),
		array('stamp' => 'f5', 'sid' => 62, 'arate_key' => 'NEW-CALL-EUROPE', 'plan' => 'NEW-PLAN-X3', 'usagev' => 180, 'services_data' => ["NEW-SERVICE1", "NEW-SERVICE2"]),
		//case G: NEW-PLAN-X3+NEW-SERVICE3
		array('stamp' => 'g1', 'sid' => 63, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-X3', 'usagev' => 120, 'services_data' => ["NEW-SERVICE3"]),
		array('stamp' => 'g2', 'sid' => 63, 'arate_key' => 'NEW-CALL-EUROPE', 'plan' => 'NEW-PLAN-X3', 'usagev' => 110.5, 'services_data' => ["NEW-SERVICE3"]),
		array('stamp' => 'g3', 'sid' => 63, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-X3', 'usagev' => 20, 'services_data' => ["NEW-SERVICE3"]),
//		array('stamp' => 'g4', 'sid' => 63, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-X3', 'usagev' => 75.4, 'services_data' => ["NEW-SERVICE3"]),
		array('stamp' => 'g5', 'sid' => 63, 'arate_key' => 'NEW-CALL-EUROPE', 'plan' => 'NEW-PLAN-X3', 'usagev' => 8, 'services_data' => ["NEW-SERVICE3"]),
		//case H: NEW-PLAN-A0 (without groups)+NEW-SERVICE1+NEW-SERVICE4  
		array('stamp' => 'h1', 'sid' => 64, 'arate_key' => 'NEW-VEG', 'plan' => 'NEW-PLAN-A0', 'usaget' => 'gr', 'usagev' => 35, 'services_data' => ["NEW-SERVICE4"]),
		array('stamp' => 'h2', 'sid' => 64, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 35.5, 'services_data' => ["NEW-SERVICE1"]),
		array('stamp' => 'h3', 'sid' => 64, 'arate_key' => 'NEW-VEG', 'plan' => 'NEW-PLAN-A0', 'usaget' => 'gr', 'usagev' => 180, 'services_data' => ["NEW-SERVICE4"]),
		array('stamp' => 'h4', 'sid' => 64, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 4.5, 'services_data' => ["NEW-SERVICE1"]),
		array('stamp' => 'h5', 'sid' => 64, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 12, 'services_data' => ["NEW-SERVICE1"]),
		//case I NEW-PLAN-A1 (with two groups) no services
		array('stamp' => 'i1', 'sid' => 65, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 24),
		array('stamp' => 'i2', 'sid' => 65, 'arate_key' => 'NEW-VEG', 'plan' => 'NEW-PLAN-A1', 'usaget' => 'gr', 'usagev' => 12),
		array('stamp' => 'i3', 'sid' => 65, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 50),
		array('stamp' => 'i4', 'sid' => 65, 'arate_key' => 'NEW-VEG', 'plan' => 'NEW-PLAN-A1', 'usaget' => 'gr', 'usagev' => 80),
		array('stamp' => 'i5', 'sid' => 65, 'arate_key' => 'NEW-CALL-EUROPE', 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 50.5),
		//case J NEW-PLAN-A2 multiple groups with same name
		array('stamp' => 'j1', 'sid' => 66, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ["NEW-SERVICE1"]),
		array('stamp' => 'j2', 'sid' => 66, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 75, 'services_data' => ["NEW-SERVICE1"]),
		array('stamp' => 'j3', 'sid' => 66, 'arate_key' => 'NEW-CALL-USA', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ["NEW-SERVICE1"]),
		array('stamp' => 'j4', 'sid' => 66, 'arate_key' => 'NEW-VEG', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'gr', 'usagev' => 30, 'services_data' => ["NEW-SERVICE1"]),
		//case K shared account test
		array('stamp' => 'k1', 'aid' => 7770, 'sid' => 71, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 8, 'services_data' => ["SHARED-SERVICE1"]),
		array('stamp' => 'k2', 'aid' => 7770, 'sid' => 72, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 8, 'services_data' => ["SHARED-SERVICE1"]),
		array('stamp' => 'k3', 'aid' => 7771, 'sid' => 73, 'arate_key' => 'SHARED-RATE', 'plan' => 'SHARED-PLAN-K3',  'usaget' => 'call', 'usagev' => 20, 'services_data' => ["SHARED-SERVICE1"]),
		array('stamp' => 'k4', 'aid' => 7772, 'sid' => 74, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 20, 'services_data' => ["SHARED-SERVICE1", "NO-SHARED-SERVICE2"]),
		array('stamp' => 'k5', 'aid' => 7772, 'sid' => 75, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 20, 'services_data' => ["SHARED-SERVICE1", "NO-SHARED-SERVICE2"]),
		//old tests
		//case A: PLAN-X3+SERVICE1+SERVICE2
		array('stamp' => 'a1', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 60, 'services_data' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a2', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 50, 'services_data' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a3', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 50, 'services_data' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a4', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 280, 'services_data' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a5', 'sid' => 51, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-X3', 'usagev' => 180, 'services_data' => ["SERVICE1", "SERVICE2"]),
		//case B: PLAN-X3+SERVICE3
		array('stamp' => 'b1', 'sid' => 52, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 120, 'services_data' => ["SERVICE3"]),
		array('stamp' => 'b2', 'sid' => 52, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-X3', 'usagev' => 110.5, 'services_data' => ["SERVICE3"]),
		array('stamp' => 'b3', 'sid' => 52, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 20, 'services_data' => ["SERVICE3"]),
//		array('stamp' => 'b4', 'sid' => 52, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 75.4, 'services_data' => ["SERVICE3"]),
		array('stamp' => 'b5', 'sid' => 52, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-X3', 'usagev' => 8, 'services_data' => ["SERVICE3"]),
		//case C: PLAN-A0 (without groups)+SERVICE1+SERVICE4  
		array('stamp' => 'c1', 'sid' => 53, 'arate_key' => 'VEG', 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 35, 'services_data' => ["SERVICE4"]),
		array('stamp' => 'c2', 'sid' => 53, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 35.5, 'services_data' => ["SERVICE1"]),
		array('stamp' => 'c3', 'sid' => 53, 'arate_key' => 'VEG', 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 180, 'services_data' => ["SERVICE4"]),
		array('stamp' => 'c4', 'sid' => 53, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 4.5, 'services_data' => ["SERVICE1"]),
		array('stamp' => 'c5', 'sid' => 53, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 12, 'services_data' => ["SERVICE1"]),
		//case D PLAN-A1 (with two groups) no services
		array('stamp' => 'd1', 'sid' => 54, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 24),
		array('stamp' => 'd2', 'sid' => 54, 'arate_key' => 'VEG', 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 12),
		array('stamp' => 'd3', 'sid' => 54, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50),
		array('stamp' => 'd4', 'sid' => 54, 'arate_key' => 'VEG', 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 80),
		array('stamp' => 'd5', 'sid' => 54, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50.5),
		//case E PLAN-A2 multiple groups with same name
		array('stamp' => 'e1', 'sid' => 55, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ["SERVICE1"]),
		array('stamp' => 'e2', 'sid' => 55, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 75, 'services_data' => ["SERVICE1"]),
		array('stamp' => 'e3', 'sid' => 55, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ["SERVICE1"]),
		array('stamp' => 'e4', 'sid' => 55, 'arate_key' => 'VEG', 'plan' => 'PLAN-A2', 'usaget' => 'gr', 'usagev' => 30, 'services_data' => ["SERVICE1"]),
		/**** NEW TEST CASES ****/
		//case L cost
		array('stamp' => 'l1', 'aid' => 23457, 'sid' => 77, 'arate_key' => 'NEW-VEG', 'plan' => 'NEW-PLAN-Z5', 'usaget' => 'gr', 'usagev' => 240, 'services_data' => ["NEW-SERVICE5"]),
		array('stamp' => 'l2', 'aid' => 23457, 'sid' => 78, 'arate_key' => 'RATE-L3', 'plan' => 'PLAN-L2', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-L3"]),
		array('stamp' => 'l3', 'aid' => 23457, 'sid' => 79, 'arate_key' => 'RATE-L3', 'plan' => 'PLAN-L3', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-L2"]),
		array('stamp' => 'l4', 'aid' => 23458, 'sid' => 80, 'arate_key' => 'RATE-L3', 'plan' => 'PLAN-L4-SHARED', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-L2"]),
		//case M pooled account services
		array('stamp' => 'm1', 'aid' => 8880, 'sid' => 800, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 10, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm2', 'aid' => 8881, 'sid' => 801, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 20, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm3', 'aid' => 8882, 'sid' => 803, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 20, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm4', 'aid' => 8883, 'sid' => 804, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 15, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm5', 'aid' => 8884, 'sid' => 806, 'arate_key' => 'SHARED-RATE', 'plan' => 'POOLED-PLAN-1',  'usaget' => 'call', 'usagev' => 25, 'services_data' => ["POOLED-SERVICE12", "POOLED-SERVICE11"]),
		array('stamp' => 'm6', 'aid' => 8884, 'sid' => 807, 'arate_key' => 'SHARED-RATE', 'plan' => 'POOLED-PLAN-1',  'usaget' => 'call', 'usagev' => 10, 'services_data' => ["POOLED-SERVICE12", "POOLED-SERVICE11"]),
		array('stamp' => 'm7', 'aid' => 8885, 'sid' => 809, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2',  'usaget' => 'call', 'usagev' => 100, 'services_data' => ["POOLED-SERVICE3"]),
		// case N - new structure support multiple usage types
		// N1
		array('stamp' => 'n1', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N1', 
			'plan' => 'NEW-PLAN-N1',  'usaget' => 'call', 'usagev' => 125, 'services_data' => ["SERVICE-N1"]),
		// N2 - depend on N1
		array('stamp' => 'n2', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N1', 
			'plan' => 'NEW-PLAN-N1',  'usaget' => 'call', 'usagev' => 275, 'services_data' => ["SERVICE-N1"]),
		// N3
		array('stamp' => 'n3', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N3', 
			'plan' => 'NEW-PLAN-N1',  'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-N3"]),
		// N4 - depend on N1
		array('stamp' => 'n4', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N3', 
			'plan' => 'NEW-PLAN-N1',  'usaget' => 'call', 'usagev' => 475, 'services_data' => ["SERVICE-N3"]),
		// N5
		array('stamp' => 'n5', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N5', 
			'plan' => 'NEW-PLAN-N5',  'usaget' => 'call', 'usagev' => 5, 'services_data' => []),
		// N6
		array('stamp' => 'n6', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N5', 
			'plan' => 'NEW-PLAN-N5',  'usaget' => 'call', 'usagev' => 5, 'services_data' => []),
		// N7
		array('stamp' => 'n7', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N5', 
			'plan' => 'NEW-PLAN-N5',  'usaget' => 'call', 'usagev' => 5, 'services_data' => []),
		// case O - custom period balance support
		// O1
		array('stamp' => 'o1', 'aid' => 9501, 'sid' => 950, 'arate_key' => 'RATE-O1', 
			'plan' => 'NEW-PLAN-O1',  'usaget' => 'call', 'usagev' => 35, 'services_data' => ["SERVICE-O1"],
			'urt' => '2017-09-01 09:00:00+03:00'),
//		// O2
		array('stamp' => 'o2', 'aid' => 9501, 'sid' => 950, 'arate_key' => 'RATE-O1',
			'plan' => 'NEW-PLAN-O1',  'usaget' => 'call', 'usagev' => 62, 'services_data' => ["SERVICE-O1"],
			'urt' => '2017-09-15 09:00:00+03:00'),
		// O3
		array('stamp' => 'o3', 'aid' => 9501, 'sid' => 950, 'arate_key' => 'RATE-O2',
			'plan' => 'NEW-PLAN-O1',  'usaget' => 'call', 'usagev' => 45, 'services_data' => ["SERVICE-O1"],
			'urt' => '2017-09-14 09:00:00+03:00'),
		// O4- plan includes - use all
		array('stamp' => 'o4', 'aid' => 9502, 'sid' => 951, 'arate_key' => 'RATE-O4',
			'plan' => 'NEW-PLAN-O4',  'usaget' => 'call', 'usagev' => 40, 'services_data' => ["SERVICE-O4"],
			'urt' => '2017-09-14 09:00:00+03:00'),
		// O5 - try to use service includes
		array('stamp' => 'o5', 'aid' => 9502, 'sid' => 951, 'arate_key' => 'RATE-O4',
			'plan' => 'NEW-PLAN-O4',  'usaget' => 'call', 'usagev' => 30, 'services_data' => ["SERVICE-O4"],
			'urt' => '2017-09-14 11:00:00+03:00'),
		array('stamp' => 'o6', 'aid' => 9502, 'sid' => 951, 'arate_key' => 'RATE-O4',
			'plan' => 'NEW-PLAN-O4',  'usaget' => 'call', 'usagev' => 75, 'services_data' => ["SERVICE-O4"],
			'urt' => '2017-09-14 14:00:00+03:00'),

		// O6- plan includes - use part of it
		// O7 - try to use service includes
	];
	protected $expected = [
		//New tests for new override price and includes format
		//case F expected
		array('in_group' => 60, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 50, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 50, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 55, 'over_group' => 225, 'aprice' => 106.5),
		array('in_group' => 0, 'over_group' => 180, 'aprice' => 18),
		//case G expected
		array('in_group' => 120, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 0, 'over_group' => 110.5, 'aprice' => 11.1),
		array('in_group' => 20, 'over_group' => 0, 'aprice' => 0),
//		array('in_group' => 75, 'over_group' => 0.4, 'aprice' => 0.1),
		array('in_group' => 0, 'over_group' => 8, 'aprice' => 0.8),
		//case H expected
		array('in_group' => 35, 'over_group' => 0, 'aprice' => 0), //gr from service 4, remain 165
		array('in_group' => 35.5, 'over_group' => 0, 'aprice' => 0), //call from service 1, remain 165
		array('in_group' => 165, 'over_group' => 15, 'aprice' => 3), //gr from service 4, over
		array('in_group' => 4.5, 'over_group' => 0, 'aprice' => 0), //call from service 1, over
		array('in_group' => 0, 'over_group' => 12, 'aprice' => 6), //call over group
		//case I expected
		array('in_group' => 24, 'over_group' => 0, 'aprice' => 0), //call from plan
		array('in_group' => 12, 'over_group' => 0, 'aprice' => 0), //gr from plan
		array('in_group' => 26, 'over_group' => 24, 'aprice' => 12), //call from plan + over
		array('in_group' => 38, 'over_group' => 42, 'aprice' => 6.4), //gr from plan + over
		array('in_group' => 0, 'over_group' => 50.5, 'aprice' => 5.1), // over calls
		//case J expected
		array('in_group' => 30, 'over_group' => 0, 'aprice' => 0), //in groups
		array('in_group' => 70, 'over_group' => 5, 'aprice' => 2.5), //move group and over
		array('in_group' => 0, 'over_group' => 30, 'aprice' => 15), //over group
		array('in_group' => 0, 'over_group' => 30, 'aprice' => 6), //out group
		//case K expected
		array('in_group' => 8, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 2, 'over_group' => 6, 'aprice' => 0.6),
		array('in_group' => 15, 'over_group' => 5, 'aprice' => 0.5),
		array('in_group' => 15, 'over_group' => 5, 'aprice' => 0.5),
		array('in_group' => 5, 'over_group' => 15, 'aprice' => 1.5),
		//old results
		//case A expected
		array('in_group' => 60, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 50, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 50, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 55, 'over_group' => 225, 'aprice' => 90),
		array('in_group' => 0, 'over_group' => 180, 'aprice' => 18),
		//case B expected
		array('in_group' => 120, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 0, 'over_group' => 110.5, 'aprice' => 11.1),
		array('in_group' => 20, 'over_group' => 0, 'aprice' => 0),
//		array('in_group' => 75, 'over_group' => 0.4, 'aprice' => 0.16),
		array('in_group' => 0, 'over_group' => 8, 'aprice' => 0.8),
		//case C expected
		array('in_group' => 35, 'over_group' => 0, 'aprice' => 0), //gr from service 4, remain 165
		array('in_group' => 35.5, 'over_group' => 0, 'aprice' => 0), //call from service 1, remain 165
		array('in_group' => 165, 'over_group' => 15, 'aprice' => 3), //gr from service 4, over
		array('in_group' => 4.5, 'over_group' => 0, 'aprice' => 0), //call from service 1, over
		array('in_group' => 0, 'over_group' => 12, 'aprice' => 6), //call over group
		//case D expected
		array('in_group' => 24, 'over_group' => 0, 'aprice' => 0), //call from plan
		array('in_group' => 12, 'over_group' => 0, 'aprice' => 0), //gr from plan
		array('in_group' => 26, 'over_group' => 24, 'aprice' => 12), //call from plan + over
		array('in_group' => 38, 'over_group' => 42, 'aprice' => 8.4), //gr from plan + over
		array('in_group' => 0, 'over_group' => 50.5, 'aprice' => 5.1), // over calls
		//case E expected
		array('in_group' => 30, 'over_group' => 0, 'aprice' => 0), //in groups
		array('in_group' => 50, 'over_group' => 25, 'aprice' => 12.5), //move group and over
		array('in_group' => 0, 'over_group' => 30, 'aprice' => 15), //over group
		array('in_group' => 0, 'over_group' => 30, 'aprice' => 6), //out group
		//case L expected
		array('in_group' => 30, 'over_group' => 18, 'aprice' => 18), //out group
		array('in_group' => 30, 'over_group' => 210, 'aprice' => 21), //out group
		array('in_group' => 12, 'over_group' => 12, 'aprice' => 12), //out group
		array('in_group' => 35, 'over_group' => 205, 'aprice' => 20.5), //out group
		//case M expected
		array('in_group' => 10, 'over_group' => 0, 'aprice' => 0), //1 subscriber with one service
		array('in_group' => 20, 'over_group' => 0, 'aprice' => 0), //2 subscribers with one service
		array('in_group' => 10, 'over_group' => 10, 'aprice' => 1), //1 subscribers with one service (big usagev)
		array('in_group' => 10, 'over_group' => 5, 'aprice' => 0.5), //2 subscribers with different services
		array('in_group' => 25, 'over_group' => 0, 'aprice' => 0), //3 subscriber3 with pooled plan
		array('in_group' => 5, 'over_group' => 5, 'aprice' => 0.5), //3 subscriber3 with pooled plan
		array('in_group' => 60, 'over_group' => 40, 'aprice' => 4), //1 subscriber with one service of cost
		//case N expected
		array('in_group' => 125, 'over_group' => 0, 'aprice' => 0), //N1
		array('in_group' => 175, 'over_group' => 100, 'aprice' => 10), //N2
		array('in_group' => 240, 'over_group' => 0, 'aprice' => 0), //N3
		array('in_group' => 60, 'over_group' => 415, 'aprice' => 7), //N4	
		array('in_group' => 5, 'over_group' => 0, 'aprice' => 0), //N5	
		array('in_group' => 5, 'over_group' => 0, 'aprice' => 0), //N6	
		array('in_group' => 0, 'over_group' => 5, 'aprice' => 2.5), //N7
		//case O expected
		array('in_group' => 35, 'over_group' => 0, 'aprice' => 0), //O1
		array('in_group' => 0, 'over_group' => 62, 'aprice' => 0.62), //O2
		array('in_group' => 25, 'over_group' => 20, 'aprice' => 0.02), //O3

		array('in_group' => 40, 'over_group' => 0, 'aprice' => 0), //O4
		array('in_group' => 30, 'over_group' => 0, 'aprice' => 0), //O5
		array('in_group' => 70, 'over_group' => 5, 'aprice' => 0.5), //O6
	];

	public function __construct($label = false) {
		parent::__construct("test UpdateRow");
	}

	public function testUpdateRow() {

		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		$init = new Tests_UpdateRowSetUp();
		$init->setColletions();
		//Billrun_Factory::db()->subscribersCollection()->update(array('type' => 'subscriber'),array('$set' =>array('services_data'=>$this->servicesToUse)),array("multiple" => true));
		//running test
		foreach ($this->rows as $key => $row) {
			$row = $this->fixRow($row, $key);
			$this->linesCol->insert($row);
			$updatedRow = $this->runT($row['stamp']);
			$result = $this->compareExpected($key, $updatedRow);

			$this->assertTrue($result[0]);
			print ($result[1]);
			print('<p style="border-top: 1px dashed black;"></p>');
		}
		$init->restoreColletions();
		//$this->assertTrue(True);
	}

	protected function runT($stamp) {
		$entity = $this->linesCol->query(array('stamp' => $stamp))->cursor()->current();
		$ret = $this->calculator->updateRow($entity);
		$this->calculator->writeLine($entity, '123');
		$this->calculator->removeBalanceTx($entity);
		$entityAfter = $entity->getRawData();
		return ($entityAfter);
	}

	//checks return data
	protected function compareExpected($key, $returnRow) {
		$passed = True;
		$epsilon = 0.000001;
		$inGroupE = $this->expected[$key]['in_group'];
		$overGroupE = $this->expected[$key]['over_group'];
		$aprice = round(10 * ($this->expected[$key]['aprice']), (1/$epsilon)) / 10;
		$message = '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . ($key + 1) . '(#'  . $returnRow['stamp'] . '). <b> Expected: </b> <br> — aprice: ' . $aprice . '<br> — in_group: ' . $inGroupE . '<br> — over_group: ' . $overGroupE . '<br> <b> &nbsp;&nbsp;&nbsp; Result: </b> <br>';
		$message .= '— aprice: ' . $returnRow['aprice'];
		if (Billrun_Util::isEqual($returnRow['aprice'], $aprice, $epsilon)) {
			$message .= $this->pass;
		} else {
			$message .= $this->fail;
			$passed = False;
		}
		if ($inGroupE == 0) {
			if ((!isset($returnRow['in_group'])) || Billrun_Util::isEqual($returnRow['in_group'], 0, $epsilon)) {
				$message .= '— in_group: 0' . $this->pass;
			} else {
				$message .= '— in_group: ' . $returnRow['in_group'] . $this->fail;
				$passed = False;
			}
		} else {
			if (!isset($returnRow['in_group'])) {
				$message .= '— in_group: 0' . $this->fail;
				$passed = False;
			} else if (!Billrun_Util::isEqual($returnRow['in_group'], $inGroupE, $epsilon)) {
				$message .= '— in_group: ' . $returnRow['in_group'] . $this->fail;
				$passed = False;
			} else {
				$message .= '— in_group: ' . $returnRow['in_group'] . $this->pass;
			}
		}
		if ($overGroupE == 0) {
			if (((!isset($returnRow['over_group'])) || (Billrun_Util::isEqual($returnRow['over_group'], 0, $epsilon))) && ((!isset($returnRow['out_plan'])) || (Billrun_Util::isEqual($returnRow['out_plan'], 0, $epsilon)))) {
				$message .= '— over_group and out_plan: doesnt set' . $this->pass;
			} else {
				if (isset($returnRow['over_group'])) {
					$message .= '— over_group: ' . $returnRow['over_group'] . $this->fail;
					$passed = False;
				} else {
					$message .= '— out_plan: ' . $returnRow['out_plan'] . $this->fail;
					$passed = False;
				}
				$passed = False;
			}
		} else {
			if ((!isset($returnRow['over_group'])) && (!isset($returnRow['out_plan']))) {
				$message .= '— over_group and out_plan: dont set' . $this->fail;
				$passed = False;
			} else if (isset($returnRow['over_group'])) {
				if (!Billrun_Util::isEqual($returnRow['over_group'], $overGroupE, $epsilon)) {
					$message .= '— over_group: ' . $returnRow['over_group'] . $this->fail;
					$passed = False;
				} else {
					$message .= '— over_group: ' . $returnRow['over_group'] . $this->pass;
				}
			} else if (isset($returnRow['out_plan'])) {
				if (!Billrun_Util::isEqual($returnRow['out_plan'], $overGroupE, $epsilon)) {
					$message .= '— out_plan: ' . $returnRow['out_plan'] . $this->fail;
					$passed = False;
				} else {
					$message .= '— out_plan: ' . $returnRow['out_plan'] . $this->pass;
				}
			}
		}
		$message .= ' </p>';
		return [$passed, $message];
	}

	protected function fixRow($row, $key) {
		if (!isset($row['urt'])) {
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
		$rate = $this->ratesCol->query(array('key' => $row['arate_key']))->cursor()->current();
		$row['arate'] = MongoDBRef::create('rates', (new MongoId((string) $rate['_id'])));
		$plan = $this->plansCol->query(array('name' => $row['plan']))->cursor()->current();
		$row['plan_ref'] = MongoDBRef::create('plans', (new MongoId((string) $plan['_id'])));
		return $row;
	}

}
