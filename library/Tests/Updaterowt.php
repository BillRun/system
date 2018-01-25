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
//		//case F: NEW-PLAN-X3+NEW-SERVICE1+NEW-SERVICE2
		array('stamp' => 'f1', 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 60, 'services_data' => ["NEW-SERVICE1", "NEW-SERVICE2"]),
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
		array('stamp' => 'k1', 'aid' => 7770, 'sid' => 71, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 8, 'services_data' => ["SHARED-SERVICE1"]),
		array('stamp' => 'k2', 'aid' => 7770, 'sid' => 72, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 8, 'services_data' => ["SHARED-SERVICE1"]),
		array('stamp' => 'k3', 'aid' => 7771, 'sid' => 73, 'arate_key' => 'SHARED-RATE', 'plan' => 'SHARED-PLAN-K3', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ["SHARED-SERVICE1"]),
		array('stamp' => 'k4', 'aid' => 7772, 'sid' => 74, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ["SHARED-SERVICE1", "NO-SHARED-SERVICE2"]),
		array('stamp' => 'k5', 'aid' => 7772, 'sid' => 75, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ["SHARED-SERVICE1", "NO-SHARED-SERVICE2"]),
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
		/*		 * ** NEW TEST CASES *** */
		//case L cost
		array('stamp' => 'l1', 'aid' => 23457, 'sid' => 77, 'arate_key' => 'NEW-VEG', 'plan' => 'NEW-PLAN-Z5', 'usaget' => 'gr', 'usagev' => 240, 'services_data' => ["NEW-SERVICE5"]),
		array('stamp' => 'l2', 'aid' => 23457, 'sid' => 78, 'arate_key' => 'RATE-L3', 'plan' => 'PLAN-L2', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-L3"]),
		array('stamp' => 'l3', 'aid' => 23457, 'sid' => 79, 'arate_key' => 'RATE-L3', 'plan' => 'PLAN-L3', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-L2"]),
		array('stamp' => 'l4', 'aid' => 23458, 'sid' => 80, 'arate_key' => 'RATE-L3', 'plan' => 'PLAN-L4-SHARED', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-L2"]),
		//case M pooled account services
		array('stamp' => 'm1', 'aid' => 8880, 'sid' => 800, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 10, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm2', 'aid' => 8881, 'sid' => 801, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm3', 'aid' => 8882, 'sid' => 803, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm4', 'aid' => 8883, 'sid' => 804, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 15, 'services_data' => ["POOLED-SERVICE1"]),
		array('stamp' => 'm5', 'aid' => 8884, 'sid' => 806, 'arate_key' => 'SHARED-RATE', 'plan' => 'POOLED-PLAN-1', 'usaget' => 'call', 'usagev' => 25, 'services_data' => ["POOLED-SERVICE12", "POOLED-SERVICE11"]),
		array('stamp' => 'm6', 'aid' => 8884, 'sid' => 807, 'arate_key' => 'SHARED-RATE', 'plan' => 'POOLED-PLAN-1', 'usaget' => 'call', 'usagev' => 10, 'services_data' => ["POOLED-SERVICE12", "POOLED-SERVICE11"]),
		array('stamp' => 'm7', 'aid' => 8885, 'sid' => 809, 'arate_key' => 'SHARED-RATE', 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 100, 'services_data' => ["POOLED-SERVICE3"]),
		// case N - new structure support multiple usage types
		// N1
		array('stamp' => 'n1', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N1',
			'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 125, 'services_data' => ["SERVICE-N1"]),
		// N2 - depend on N1
		array('stamp' => 'n2', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N1b',
			'plan' => 'NEW-PLAN-N1', 'usaget' => 'incoming_call', 'usagev' => 275, 'services_data' => ["SERVICE-N1"]),
		// N3
		array('stamp' => 'n3', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N3',
			'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ["SERVICE-N3"]),
		// N4 - depend on N1
		array('stamp' => 'n4', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N3',
			'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 475, 'services_data' => ["SERVICE-N3"]),
		// N5
		array('stamp' => 'n5', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N5',
			'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5, 'services_data' => []),
		// N6
		array('stamp' => 'n6', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N5',
			'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5, 'services_data' => []),
		// N7
		array('stamp' => 'n7', 'aid' => 9001, 'sid' => 900, 'arate_key' => 'RATE-N5',
			'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5, 'services_data' => []),
		// case O - custom period balance support
		// O1
		array('stamp' => 'o1', 'aid' => 9501, 'sid' => 950, 'arate_key' => 'RATE-O1',
			'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 35, 'services_data' => [["name" => "SERVICE-O1", "service_id" => 5950, "from" => "2017-09-01T00:00:00+03:00", "to" => "2017-09-14T23:59:59+03:00"]],
			'urt' => '2017-09-01 09:00:00+03:00'),
//		// O2
		array('stamp' => 'o2', 'aid' => 9501, 'sid' => 950, 'arate_key' => 'RATE-O1',
			'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 62, 'services_data' => [["name" => "SERVICE-O1", "service_id" => 5950, "from" => "2017-09-01T00:00:00+03:00", "to" => "2017-09-14T23:59:59+03:00"]],
			'urt' => '2017-09-16T09:00:00+03:00'),
		// O3
		array('stamp' => 'o3', 'aid' => 9501, 'sid' => 950, 'arate_key' => 'RATE-O2',
			'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 45, 'services_data' => [["name" => "SERVICE-O1", "service_id" => 5950, "from" => "2017-09-01T00:00:00+03:00", "to" => "2017-09-14T23:59:59+03:00"]],
			'urt' => '2017-09-14 09:00:00+03:00'),
		// O4- plan includes - use all
		array('stamp' => 'o4', 'aid' => 9502, 'sid' => 951, 'arate_key' => 'RATE-O4',
			'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 40, 'services_data' => [["name" => "SERVICE-O4", "service_id" => 5950, "from" => "2017-09-01T00:00:00+03:00", "to" => "2017-09-14T23:59:59+03:00"]],
			'urt' => '2017-09-14 09:00:00+03:00'),
		// O5 - try to use service includes
		array('stamp' => 'o5', 'aid' => 9502, 'sid' => 951, 'arate_key' => 'RATE-O4',
			'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 30, 'services_data' => [["name" => "SERVICE-O4", "service_id" => 5950, "from" => "2017-09-01T00:00:00+03:00", "to" => "2017-09-14T23:59:59+03:00"]],
			'urt' => '2017-09-14 11:00:00+03:00'),
		array('stamp' => 'o6', 'aid' => 9502, 'sid' => 951, 'arate_key' => 'RATE-O4',
			'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [["name" => "SERVICE-O4", "service_id" => 5950, "from" => "2017-09-01T00:00:00+03:00", "to" => "2017-09-14T23:59:59+03:00"]],
			'urt' => '2017-09-14 14:00:00+03:00'),
		// O6- plan includes - use part of it
		// O7 - try to use service includes
		// p1 service with limited cycle's 
		array('stamp' => 'p1', 'aid' => 9503, 'sid' => 952, 'arate_key' => 'INTERNET', 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 7500000,
			'services_data' => [
				['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01 00:00:00+03:00', 'to' => '2018-09-01 00:00:00+03:00']
			],
			'urt' => '2017-09-01 00:00:00+03:00'),
		array('stamp' => 'p2', 'aid' => 9503, 'sid' => 952, 'arate_key' => 'INTERNET', 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000,
			'services_data' => [
				['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01 00:00:00+03:00', 'to' => '2018-09-01 00:00:00+03:00']
			],
			'urt' => '2017-09-14 14:00:00+03:00'),
		array('stamp' => 'p3', 'aid' => 9503, 'sid' => 952, 'arate_key' => 'INTERNET', 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000,
			'services_data' => [
				['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01 00:00:00+03:00', 'to' => '2018-09-01 00:00:00+03:00']
			],
			'urt' => '2017-09-30 14:00:00+03:00'),
		array('stamp' => 'p4', 'aid' => 9503, 'sid' => 952, 'arate_key' => 'INTERNET', 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 7500000,
			'services_data' => [
				['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01 00:00:00+03:00', 'to' => '2018-09-01 00:00:00+03:00']
			],
			'urt' => '2017-10-01 00:00:01+03:00'),
		array('stamp' => 'p5', 'aid' => 9503, 'sid' => 952, 'arate_key' => 'INTERNET', 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000,
			'services_data' => [
				['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01 00:00:00+03:00', 'to' => '2018-09-01 00:00:00+03:00']
			],
			'urt' => '2017-10-14 14:00:00+03:00'),
		//Q1
		array('stamp' => 'q1', 'aid' => 9702, 'sid' => 971, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 70,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-20 00:00:00+03:00", "to" => "2017-10-01 00:00:00+03:00", "service_id" => 4567], ["name" => "SERVICE-Q2", "from" => "2017-09-25 00:00:00+03:00", "to" => "2017-09-30 00:00:00+03:00", "service_id" => 4568]],
			'urt' => '2017-09-25 11:00:00+03:00'),
		//Q2
		array('stamp' => 'q2', 'aid' => 9702, 'sid' => 971, 'arate_key' => 'RATE-Q2',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 30,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-20 00:00:00+03:00", "to" => "2017-10-01 00:00:00+03:00", "service_id" => 4567], ["name" => "SERVICE-Q2", "from" => "2017-09-25 00:00:00+03:00", "to" => "2017-09-30 00:00:00+03:00", "service_id" => 4568]],
			'urt' => '2017-09-26 11:00:00+03:00'),
		//Q3
		array('stamp' => 'q3', 'aid' => 9702, 'sid' => 971, 'arate_key' => 'RATE-Q2',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 150,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-20 00:00:00+03:00", "to" => "2017-10-01 00:00:00+03:00", "service_id" => 4567], ["name" => "SERVICE-Q2", "from" => "2017-09-25 00:00:00+03:00", "to" => "2017-09-30 00:00:00+03:00", "service_id" => 4568]],
			'urt' => '2017-09-23 11:00:00+03:00'),
		//Q4
		array('stamp' => 'q4', 'aid' => 9702, 'sid' => 971, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 250,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-20 00:00:00+03:00", "to" => "2017-10-01 00:00:00+03:00", "service_id" => 4567], ["name" => "SERVICE-Q2", "from" => "2017-09-25 00:00:00+03:00", "to" => "2017-09-30 00:00:00+03:00", "service_id" => 4568]],
			'urt' => '2017-09-27 11:00:00+03:00'),
		//R1
		array('stamp' => 'r1', 'aid' => 9802, 'sid' => 981, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 235,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1235]],
			'urt' => '2017-09-11 11:00:00+03:00'),
		//R2
		array('stamp' => 'r2', 'aid' => 9802, 'sid' => 981, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 245,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1235]],
			'urt' => '2017-09-12 11:00:00+03:00'),
		//R3
		array('stamp' => 'r3', 'aid' => 9802, 'sid' => 981, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 70,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1235]],
			'urt' => '2017-09-13 11:00:00+03:00'),
		//R4
		array('stamp' => 'r4', 'aid' => 9802, 'sid' => 981, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 120,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1235]],
			'urt' => '2017-09-09 11:00:00+03:00'),
		//R5
		array('stamp' => 'r5', 'aid' => 9802, 'sid' => 981, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 60,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1235]],
			'urt' => '2017-09-21 11:00:00+03:00'),
		//r6
		array('stamp' => 'r6', 'aid' => 9802, 'sid' => 981, 'arate_key' => 'RATE-Q1',
			'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 75,
			'services_data' => [["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-09-21 00:00:00+03:00", "service_id" => 1235]],
			'urt' => '2017-09-14 11:00:00+03:00'),
		//Included services
		//is1 should be included
		array('stamp' => 'is1', 'aid' => 9803, 'sid' => 982, 'arate_key' => 'RATE-Q1',
			'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75,
			'services_data' => [["name" => "SERVICE-IS1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-12-21 00:00:00+03:00", "service_id" => 1234]],
			'urt' => '2017-09-14 11:00:00+03:00'),
		//is2 after the service time  (by the service price cycles not the plan de-activation)
		array('stamp' => 'is2', 'aid' => 9803, 'sid' => 982, 'arate_key' => 'RATE-Q1',
			'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75,
			'services_data' => [["name" => "SERVICE-IS1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-12-21 00:00:00+03:00", "service_id" => 1234]],
			'urt' => '2017-11-14 11:00:00+03:00'),
		//is3 after the service time  (by the plan detactivation not the cycles) TODO is this impossible?
//		array('stamp' => 'is3', 'aid' => 9803, 'sid' => 982, 'arate_key' => 'RATE-Q1',
//			'plan' => 'PLAN-IS1',  'usaget' => 'call', 'usagev' => 75, 
//			'services_data' => [["name" => "SERVICE-IS1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-10-11 00:00:00+03:00", "service_id" => 1234]],
//			'urt' => '2017-10-11 11:00:00+03:00'),
		//is4 should be half included
		array('stamp' => 'is4', 'aid' => 9803, 'sid' => 982, 'arate_key' => 'RATE-Q1',
			'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75,
			'services_data' => [["name" => "SERVICE-IS1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-12-21 00:00:00+03:00", "service_id" => 1234]],
			'urt' => '2017-09-14 11:00:00+03:00'),
		//is5 should not be included
		array('stamp' => 'is5', 'aid' => 9803, 'sid' => 982, 'arate_key' => 'RATE-Q1',
			'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75,
			'services_data' => [["name" => "SERVICE-IS1", "from" => "2017-09-10 00:00:00+03:00", "to" => "2017-12-21 00:00:00+03:00", "service_id" => 1234]],
			'urt' => '2017-09-14 11:00:00+03:00'),
		// s custom period with pooled/shard
		// s1 & s2 are one test case for check service period pooled
		array('stamp' => 's1', 'aid' => 24, 'sid' => 25, 'arate_key' => 'CALL',
			'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 15,
			'services_data' => [["name" => "PERIOD_POOLED", "from" => "2017-08-01 00:00:00+03:00", "to" => "2017-09-01 00:00:00+03:00", "service_id" => 123456]],
			'urt' => '2017-08-14 11:00:00+03:00'),
		array('stamp' => 's2', 'aid' => 24, 'sid' => 26, 'arate_key' => 'CALL',
			'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 10,
			'services_data' => [["name" => "PERIOD_POOLED", "from" => "2017-08-01 00:00:00+03:00", "to" => "2017-09-01 00:00:00+03:00", "service_id" => 1234567]],
			'urt' => '2017-08-14 11:00:00+03:00'),
		//s3 & s4 are one test case for check service period shard
		array('stamp' => 's3', 'aid' => 27, 'sid' => 28, 'arate_key' => 'CALL',
			'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 20,
			'services_data' => [["name" => "PERIOD_SHARED", "from" => "2017-08-01 00:00:00+03:00", "to" => "2017-09-01 00:00:00+03:00", "service_id" => 1234568]],
			'urt' => '2017-08-14 11:00:00+03:00'),
		array('stamp' => 's4', 'aid' => 27, 'sid' => 29, 'arate_key' => 'CALL',
			'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 15,
			'services_data' => [["name" => "PERIOD_SHARED", "from" => "2017-08-01 00:00:00+03:00", "to" => "2017-09-01 00:00:00+03:00", "service_id" => 1234569]],
			'urt' => '2017-08-14 11:00:00+03:00'),
		//T test for wholesale
		array('stamp' => 't1', 'aid' => 27, 'sid' => 30, 'rates' => array('NEW-CALL-USA' => 'retail', 'CALL' => 'wholesale'),
			'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 60,
			'urt' => '2017-08-14 11:00:00+03:00')
	];
	protected $expected = [
		//New tests for new override price and includes format
		//case F expected
		array('in_group' => 60, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0)),
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
		//case 8 service with limited cycle's 
		array('in_group' => 7500000, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 75000000, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 75000000, 'over_group' => 0, 'aprice' => 0),
		array('in_group' => 0, 'over_group' => 7500000, 'aprice' => 8,),
		array('in_group' => 0, 'over_group' => 75000000, 'aprice' => 75,),
//		
		//case Q expected
		array('in_group' => 70, 'over_group' => 0, 'aprice' => 0), //Q1
		array('in_group' => 30, 'over_group' => 0, 'aprice' => 0), //Q2
		array('in_group' => 150, 'over_group' => 0, 'aprice' => 0), //Q3
		array('in_group' => 120, 'over_group' => 130, 'aprice' => 1.30), //Q4; service2 - take 20, service1 - take 100
		//case R expected
		array('in_group' => 235, 'over_group' => 0, 'aprice' => 0), //R1
		array('in_group' => 245, 'over_group' => 0, 'aprice' => 0), //R2
		array('in_group' => 20, 'over_group' => 50, 'aprice' => 0.5), //R3
		array('in_group' => 0, 'over_group' => 120, 'aprice' => 1.2), //R4
		array('in_group' => 0, 'over_group' => 60, 'aprice' => 0.6), //R5
		array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8), //R6
		// case IS expected
		array('in_group' => 75, 'over_group' => 0, 'aprice' => 0), //IS1
		array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8), //IS2
		//array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8), //IS3
		array('in_group' => 25, 'over_group' => 50, 'aprice' => 0.5), ///IS4
		array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8), ///IS5
		// case s1/s2 tast for period service pooled 
		array('in_group' => 15, 'over_group' => 0, 'aprice' => 0), //s1
		array('in_group' => 5, 'over_group' => 5, 'aprice' => 5), //s2
		// case s3/s4 tast for period service shard 
		array('in_group' => 20, 'over_group' => 0, 'aprice' => 0), //s3
		array('in_group' => 10, 'over_group' => 5, 'aprice' => 5), //s4
		//T wholesale
		array('in_group' => 0, 'over_group' => 60, 'aprice' => 30, 'charge' => array('retail' => 30, 'wholesale' => 60))//T1
	];

	public function __construct($label = false) {
		parent::__construct("test UpdateRow");
	}

	public function testUpdateRow() {

		date_default_timezone_set('Asia/Jerusalem');
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
		$charge = (array_key_exists('charge', $this->expected[$key])) ? $this->expected[$key]['charge'] : '';
		$passed = True;
		$epsilon = 0.000001;
		$inGroupE = $this->expected[$key]['in_group'];
		$overGroupE = $this->expected[$key]['over_group'];
		$aprice = round(10 * ($this->expected[$key]['aprice']), (1 / $epsilon)) / 10;
		$message = '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . ($key + 1) . '(#' . $returnRow['stamp'] . '). <b> Expected: </b> <br> — aprice: ' . $aprice . '<br> — in_group: ' . $inGroupE . '<br> — over_group: ' . $overGroupE . '<br>';
		if (is_array($charge)) {
			foreach ($charge as $key => $value) {
				$message .= "— $key : $value <br>";
			}
		}
		$message .= '<b> Result: </b> <br>';
		$message .= '— aprice: ' . $returnRow['aprice'];

		if (Billrun_Util::isEqual($returnRow['aprice'], $aprice, $epsilon)) {

			$message .= $this->pass;
		} else {
			$message .= $this->fail;
			$passed = False;
		}
		if (!empty($charge)) {
			foreach ($charge as $category => $price) {
				$checkRate = current(array_filter($returnRow['rates'], function(array $cat) use ($category) {
						return $cat['tariff_category'] === $category;
					}));
				if (!empty($checkRate)) {
					if (!empty($checkRate)) {
						//when the tariff_category is retail check if aprice equle to him charge
						if ($checkRate['tariff_category'] == 'retail') {
							if ($aprice == $checkRate['pricing']['charge']) {
								$message .= "— $category equle to aprice  $this->pass ";
							} else {
								$message .= "— The difference between $category vs aprice its " . abs($aprice - $price) . "$this->fail";
								$passed = False;
							}
						}
						//check if the charge is currect 
						if ($price == $checkRate['pricing']['charge']) {
							$message .= "— $category {$checkRate['pricing']['charge']} $this->pass ";
						} else {
							$message .= "— $category {$checkRate['pricing']['charge']} $this->fail";
							$passed = False;
						}
					} else {
						$passed = False;
					}
				}
			}
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
		if (isset($row['services_data'])) {
			foreach ($row['services_data'] as $key => $service) {
				if (isset($service['from'])) {
					$row['services_data'][$key]['from'] = new MongoDate(strtotime($service['from']));
				}
				if (isset($service['to'])) {
					$row['services_data'][$key]['to'] = new MongoDate(strtotime($service['to']));
				}
			}
		}

		if (isset($row['arate_key'])) {
			$row['rates'] = array($row['arate_key'] => 'retail');
		}
		$keys = [];
		foreach ($row['rates'] as $rate_key => $tariff_category) {
			$rate = $this->ratesCol->query(array('key' => $rate_key))->cursor()->current();
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
