<?php

/**
 * UpdateRow (customer pricing) test cases - migrated from the (removed) legacy
 * library/Tests/Updaterowt.php (the inline cases) and library/Tests/updaterowtTestCases/
 * (one case per file).
 *
 * The cases run in this order, one after the other, on the same DB state - a case
 * relies on the balances the previous cases of its subscriber left. The order is the
 * one the legacy test ran them in: the file cases sorted by stamp, then the inline
 * cases sorted by stamp (string order, so e.g. w1, w10, ..., w19, w2, w20, w3).
 *
 * "Test num N" comments keep the legacy Updaterowt numbering.
 *
 * Case keys:
 *  - row: the line to insert and price (see updateRowCest::fixRow for the defaults)
 *  - expected: in_group, over_group, aprice, charge.retail, and optionally out_group, billrun
 *  - rebalance: rebalance the account after pricing and verify its balances were reset
 *  - conditions + groupExpected: conditional rebalance and the expected balance groups
 *  - preTest: updateRowCest methods to run before the case (removeBillruns, multiDayCycle, runCycle)
 *  - skip: reason to skip the case
 *
 * @package         Tests
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
return [

	// ow1 - updaterowtTestCases/test_number_1.php
	array (
  'row' => 
  array (
    'stamp' => 'ow1',
    'aid' => 95011,
    'sid' => 950111,
    'rates' => 
    array (
      'RATE-O1' => 'retail',
    ),
    'plan' => 'NEW-PLAN-O1',
    'usaget' => 'call',
    'usagev' => 35,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'SERVICE-QUANTITY-O1',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-01 09:00:00+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 35,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow10 - updaterowtTestCases/test_number_10.php
	array (
  'row' => 
  array (
    'stamp' => 'ow10',
    'aid' => 95014,
    'sid' => 95015,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-01 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow11 - updaterowtTestCases/test_number_11.php
	array (
  'row' => 
  array (
    'stamp' => 'ow11',
    'aid' => 95014,
    'sid' => 95016,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-01 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow12 - updaterowtTestCases/test_number_12.php
	array (
  'row' => 
  array (
    'stamp' => 'ow12',
    'aid' => 95014,
    'sid' => 95015,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 100,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 100,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow13 - updaterowtTestCases/test_number_13.php
	array (
  'row' => 
  array (
    'stamp' => 'ow13',
    'aid' => 95014,
    'sid' => 95016,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 100,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 100,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow14 - updaterowtTestCases/test_number_14.php
	array (
  'row' => 
  array (
    'stamp' => 'ow14',
    'aid' => 95014,
    'sid' => 95015,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 150,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-07-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 150,
    'aprice' => 150,
    'charge' => 
    array (
      'retail' => 150,
    ),
  ),
),

	// ow15 - updaterowtTestCases/test_number_20.php
	array (
  'row' => 
  array (
    'stamp' => 'ow15',
    'aid' => 95017,
    'sid' => 95018,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow16 - updaterowtTestCases/test_number_21.php
	array (
  'row' => 
  array (
    'stamp' => 'ow16',
    'aid' => 95017,
    'sid' => 95019,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow17 - updaterowtTestCases/test_number_22.php
	array (
  'row' => 
  array (
    'stamp' => 'ow17',
    'aid' => 95017,
    'sid' => 95018,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow18 - updaterowtTestCases/test_number_23.php
	array (
  'row' => 
  array (
    'stamp' => 'ow18',
    'aid' => 95017,
    'sid' => 95019,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow19 - updaterowtTestCases/test_number_24.php
	array (
  'row' => 
  array (
    'stamp' => 'ow19',
    'aid' => 95017,
    'sid' => 95018,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// ow2 - updaterowtTestCases/test_number_2.php
	array (
  'row' => 
  array (
    'stamp' => 'ow2',
    'aid' => 950111,
    'sid' => 950111,
    'rates' => 
    array (
      'RATE-O1' => 'retail',
    ),
    'plan' => 'NEW-PLAN-O1',
    'usaget' => 'call',
    'usagev' => 62,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'SERVICE-QUANTITY-O1',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-16T09:00:00+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 62,
    'aprice' => 0.62,
    'charge' => 
    array (
      'retail' => 0.62,
    ),
  ),
),

	// ow20 - updaterowtTestCases/test_number_25.php
	array (
  'rebalance' => true,
  'row' => 
  array (
    'stamp' => 'ow20',
    'aid' => 95017,
    'sid' => 95019,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// ow21 - updaterowtTestCases/test_number_26.php
	array (
  'rebalance' => true,
  'row' => 
  array (
    'stamp' => 'ow21',
    'aid' => 43,
    'sid' => 44,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow22 - updaterowtTestCases/test_number_28.php
	array (
  'row' => 
  array (
    'stamp' => 'ow22',
    'aid' => 95025,
    'sid' => 95026,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 110,
    'urt' => '2017-09-01 23:11:45+03:00',
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'plan_included' => false,
        'quantity' => 1,
        'service_id' => 1,
      ),
      1 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'plan_included' => false,
        'quantity' => 1,
        'service_id' => 2,
      ),
    ),
  ),
  'expected' => 
  array (
    'in_group' => 110,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 110,
    ),
  ),
),

	// ow23 - updaterowtTestCases/test_number_29.php
	array (
  'rebalance' => true,
  'row' => 
  array (
    'stamp' => 'ow23',
    'aid' => 95025,
    'sid' => 95026,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 100,
    'urt' => '2017-09-01 23:11:45+03:00',
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01 ',
        'to' => '2017-09-14',
        'plan_included' => false,
        'quantity' => 1,
        'service_id' => 1,
      ),
      1 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'plan_included' => false,
        'quantity' => 1,
        'service_id' => 2,
      ),
    ),
  ),
  'expected' => 
  array (
    'in_group' => 90,
    'over_group' => 10,
    'aprice' => 10,
    'charge' => 
    array (
      'retail' => 110,
    ),
  ),
),

	// ow25 - updaterowtTestCases/test_number_16.php
	array (
  'row' => 
  array (
    'stamp' => 'ow25',
    'aid' => 95017,
    'sid' => 95018,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-08-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// ow26 - updaterowtTestCases/test_number_17.php
	array (
  'row' => 
  array (
    'stamp' => 'ow26',
    'aid' => 95017,
    'sid' => 95019,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2017-08-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// ow27 - updaterowtTestCases/test_number_18.php
	array (
  'row' => 
  array (
    'stamp' => 'ow27',
    'aid' => 95017,
    'sid' => 95018,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2018-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// ow28 - updaterowtTestCases/test_number_19.php
	array (
  'row' => 
  array (
    'stamp' => 'ow28',
    'aid' => 95017,
    'sid' => 95019,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 1,
      ),
    ),
    'urt' => '2018-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// ow29 - updaterowtTestCases/test_number_27.php
	array (
  'rebalance' => true,
  'row' => 
  array (
    'stamp' => 'ow29',
    'aid' => 43,
    'sid' => 45,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 0,
    'out_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// ow3 - updaterowtTestCases/test_number_3.php
	array (
  'rebalance' => true,
  'row' => 
  array (
    'stamp' => 'ow3',
    'aid' => 95011,
    'sid' => 950111,
    'rates' => 
    array (
      'RATE-O2' => 'retail',
    ),
    'plan' => 'NEW-PLAN-O1',
    'usaget' => 'call',
    'usagev' => 100,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'SERVICE-QUANTITY-O1',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-14 09:00:00+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 85,
    'over_group' => 15,
    'aprice' => 0.02,
    'charge' => 
    array (
      'retail' => 0.02,
    ),
  ),
),

	// ow4 - updaterowtTestCases/test_number_4.php
	array (
  'row' => 
  array (
    'stamp' => 'ow4',
    'aid' => 95022,
    'sid' => 95111,
    'rates' => 
    array (
      'RATE-O4' => 'retail',
    ),
    'plan' => 'NEW-PLAN-O4',
    'usaget' => 'call',
    'usagev' => 40,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'SERVICE-QUANTITY-O4',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-14 09:00:00+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 40,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow5 - updaterowtTestCases/test_number_5.php
	array (
  'row' => 
  array (
    'stamp' => 'ow5',
    'aid' => 95022,
    'sid' => 95111,
    'rates' => 
    array (
      'RATE-O4' => 'retail',
    ),
    'plan' => 'NEW-PLAN-O4',
    'usaget' => 'call',
    'usagev' => 30,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'SERVICE-QUANTITY-O4',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-13 11:00:00+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 30,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow6 - updaterowtTestCases/test_number_6.php
	array (
  'row' => 
  array (
    'stamp' => 'ow6',
    'aid' => 95012,
    'sid' => 95013,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 150,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-11-15 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 150,
    'aprice' => 150,
    'charge' => 
    array (
      'retail' => 150,
    ),
  ),
),

	// ow7 - updaterowtTestCases/test_number_7.php
	array (
  'row' => 
  array (
    'stamp' => 'ow7',
    'aid' => 95012,
    'sid' => 95013,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 150,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-12 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 150,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow8 - updaterowtTestCases/test_number_8.php
	array (
  'row' => 
  array (
    'stamp' => 'ow8',
    'aid' => 95012,
    'sid' => 95013,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 50,
    'over_group' => 0,
    'aprice' => 0,
    'charge' => 
    array (
      'retail' => 0,
    ),
  ),
),

	// ow9 - updaterowtTestCases/test_number_9.php
	array (
  'row' => 
  array (
    'stamp' => 'ow9',
    'aid' => 95012,
    'sid' => 95013,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 50,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'MUL_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 50,
    'aprice' => 50,
    'charge' => 
    array (
      'retail' => 50,
    ),
  ),
),

	// w24 - updaterowtTestCases/test_number_15.php
	array (
  'rebalance' => true,
  'row' => 
  array (
    'stamp' => 'w24',
    'aid' => 95014,
    'sid' => 95016,
    'rates' => 
    array (
      'CALL' => 'retail',
    ),
    'plan' => 'WITH_NOTHING',
    'type' => 'realTime',
    'usaget' => 'call',
    'usagev' => 150,
    'services_data' => 
    array (
      0 => 
      array (
        'name' => 'POOLD_CUSTOM',
        'from' => '2017-09-01',
        'to' => '2017-09-14',
        'quantity_affected' => true,
        'quantity' => 2,
      ),
    ),
    'urt' => '2017-09-14 23:11:45+03:00',
  ),
  'expected' => 
  array (
    'in_group' => 0,
    'over_group' => 150,
    'aprice' => 150,
    'charge' => 
    array (
      'retail' => 150,
    ),
  ),
),

	// a1 - Updaterowt::tests()
	//old tests
	//case A: PLAN-X3+SERVICE1+SERVICE2
	//Test num 29 a1
	array('row' => array('stamp' => 'a1', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 60, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 60, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// a2 - Updaterowt::tests()
	//Test num 30 a2
	array('row' => array('stamp' => 'a2', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 50, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// a3 - Updaterowt::tests()
	//Test num 31 a3
	array('row' => array('stamp' => 'a3', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 50, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// a4 - Updaterowt::tests()
	//Test num 32 a4
	array('row' => array('stamp' => 'a4', 'sid' => 51, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 280, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 55, 'over_group' => 225, 'aprice' => 90, 'charge' => array('retail' => 90,))),

	// a5 - Updaterowt::tests()
	//Test num 33 a5
	array('rebalance' => true, 'row' => array('stamp' => 'a5', 'sid' => 51, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 180, 'services_data' => ['SERVICE1', 'SERVICE2',],),
			'expected' => array('in_group' => 0, 'over_group' => 180, 'aprice' => 18, 'charge' => array('retail' => 18,))),

	// b1 - Updaterowt::tests()
	//case B: PLAN-X3+SERVICE3
	//Test num 34 b1
	array('row' => array('stamp' => 'b1', 'sid' => 52, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 120, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 120, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// b2 - Updaterowt::tests()
	//Test num 35 b2
	array('row' => array('stamp' => 'b2', 'sid' => 52, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 110.5, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 110.5, 'aprice' => 11.1, 'charge' => array('retail' => 11.1,))),

	// b3 - Updaterowt::tests()
	//Test num 36 b3
	array('row' => array('stamp' => 'b3', 'sid' => 52, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 20, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// b5 - Updaterowt::tests()
	//Test num 37 b5
	array('rebalance' => true, 'row' => array('stamp' => 'b5', 'sid' => 52, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-X3', 'usagev' => 8, 'services_data' => ['SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 8, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),

	// c1 - Updaterowt::tests()
	//		//case C: PLAN-A0 (without groups)+SERVICE1+SERVICE4
	////Test num 38 c1
	array('row' => array('stamp' => 'c1', 'sid' => 53, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 35, 'services_data' => ['SERVICE4',],),
			'expected' => array('in_group' => 35, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// c2 - Updaterowt::tests()
	//Test num 39 c2
	array('row' => array('stamp' => 'c2', 'sid' => 53, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 35.5, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 35.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// c3 - Updaterowt::tests()
	//Test num 40 c3
	array('row' => array('stamp' => 'c3', 'sid' => 53, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 180, 'services_data' => ['SERVICE4',],),
			'expected' => array('in_group' => 165, 'over_group' => 15, 'aprice' => 3, 'charge' => array('retail' => 3,))),

	// c4 - Updaterowt::tests()
	//Test num 41 c4
	array('row' => array('stamp' => 'c4', 'sid' => 53, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 4.5, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 4.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// c5 - Updaterowt::tests()
	//Test num 42 c5
	array('rebalance' => true, 'row' => array('stamp' => 'c5', 'sid' => 53, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 12, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 12, 'aprice' => 6, 'charge' => array('retail' => 6,))),

	// d1 - Updaterowt::tests()
	//case D PLAN-A1 (with two groups) no services
	//Test num 43 d1
	array('row' => array('stamp' => 'd1', 'sid' => 54, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 24),
			'expected' => array('in_group' => 24, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// d2 - Updaterowt::tests()
	//Test num 44 d2
	array('row' => array('stamp' => 'd2', 'sid' => 54, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 12),
			'expected' => array('in_group' => 12, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// d3 - Updaterowt::tests()
	//Test num 45 d3
	array('row' => array('stamp' => 'd3', 'sid' => 54, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50),
			'expected' => array('in_group' => 26, 'over_group' => 24, 'aprice' => 12, 'charge' => array('retail' => 12,))),

	// d4 - Updaterowt::tests()
	//Test num 46 d4
	array('row' => array('stamp' => 'd4', 'sid' => 54, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 80),
			'expected' => array('in_group' => 38, 'over_group' => 42, 'aprice' => 8.4, 'charge' => array('retail' => 8.4,))),

	// d5 - Updaterowt::tests()
	//Test num 47 d5
	array('rebalance' => true, 'row' => array('stamp' => 'd5', 'sid' => 54, 'rates' => array('CALL-EUROPE' => 'retail'), 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50.5),
			'expected' => array('in_group' => 0, 'over_group' => 50.5, 'aprice' => 5.1, 'charge' => array('retail' => 5.1,))),

	// e1 - Updaterowt::tests()
	//		//case E PLAN-A2 multiple groups with same name
	////Test num 48 e1
	array('row' => array('stamp' => 'e1', 'aid' => 552, 'sid' => 55, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// e2 - Updaterowt::tests()
	//Test num 49 e2
	array('row' => array('stamp' => 'e2', 'aid' => 552, 'sid' => 55, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 75, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 50, 'over_group' => 25, 'aprice' => 12.5, 'charge' => array('retail' => 12.5,))),

	// e3 - Updaterowt::tests()
	//Test num 50 e3
	array('row' => array('stamp' => 'e3', 'aid' => 552, 'sid' => 55, 'rates' => array('CALL-USA' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 15, 'charge' => array('retail' => 15,))),

	// e4 - Updaterowt::tests()
	//Test num 51 e4
	array('rebalance' => true, 'row' => array('stamp' => 'e4', 'aid' => 552, 'sid' => 55, 'rates' => array('VEG' => 'retail'), 'plan' => 'PLAN-A2', 'usaget' => 'gr', 'usagev' => 30, 'services_data' => ['SERVICE1']),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 6, 'charge' => array('retail' => 6,))),

	// f1 - Updaterowt::tests()
	//New tests for new override price and includes format
	//		case F: NEW-PLAN-X3+NEW-SERVICE1+NEW-SERVICE2
	//Test num 1 f1
	array('row' => array('stamp' => 'f1', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 60, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2']),
				'expected' => array('in_group' => 60, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))
			),

	// f2 - Updaterowt::tests()
	//Test num 2 f2
	array('row' => array('stamp' => 'f2', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 50, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2']),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// f3 - Updaterowt::tests()
	//Test num 3 f3
	array('row' => array('stamp' => 'f3', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 50, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2'],),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// f4 - Updaterowt::tests()
	///Test num 4 f4
	array('row' => array('stamp' => 'f4', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 280, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2',],),
			'expected' => array('in_group' => 55, 'over_group' => 225, 'aprice' => 106.5, 'charge' => array('retail' => 106.5,))),

	// f5 - Updaterowt::tests()
	//Test num 5 f5
	array('rebalance' => true, 'row' => array('stamp' => 'f5', "aid" => 2345, 'sid' => 62, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 180, 'services_data' => ['NEW-SERVICE1', 'NEW-SERVICE2'],),
			'expected' => array('in_group' => 0, 'over_group' => 180, 'aprice' => 18, 'charge' => array('retail' => 18,))),

	// g1 - Updaterowt::tests()
	//case G: NEW-PLAN-X3+NEW-SERVICE3
	//Test num 6 g1
	array('row' => array('stamp' => 'g1', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 120, 'services_data' => ['NEW-SERVICE3'],),
			'expected' => array('in_group' => 120, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// g2 - Updaterowt::tests()
	//Test num 7 g2
	array('row' => array('stamp' => 'g2', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 110.5, 'services_data' => ['NEW-SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 110.5, 'aprice' => 11.1, 'charge' => array('retail' => 11.1,))),

	// g3 - Updaterowt::tests()
	//Test num 8 g3
	array('row' => array('stamp' => 'g3', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 20, 'services_data' => ['NEW-SERVICE3',],),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// g5 - Updaterowt::tests()
	//Test num 9 g5
	array('rebalance' => true, 'row' => array('stamp' => 'g5', "aid" => 2345, 'sid' => 63, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-X3', 'usagev' => 8, 'services_data' => ['NEW-SERVICE3',],),
			'expected' => array('in_group' => 0, 'over_group' => 8, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),

	// h1 - Updaterowt::tests()
	//case H: NEW-PLAN-A0 (without groups)+NEW-SERVICE1+NEW-SERVICE4
	//Test num 10 h1
	array('row' => array('stamp' => 'h1', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'gr', 'usagev' => 35, 'services_data' => ['NEW-SERVICE4',],),
			'expected' => array('in_group' => 35, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// h2 - Updaterowt::tests()
	//Test num 11 h2
	array('row' => array('stamp' => 'h2', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 35.5, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 35.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// h3 - Updaterowt::tests()
	//Test num 12 h3
	array('row' => array('stamp' => 'h3', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'gr', 'usagev' => 180, 'services_data' => ['NEW-SERVICE4'],),
			'expected' => array('in_group' => 165, 'over_group' => 15, 'aprice' => 3, 'charge' => array('retail' => 3,))),

	// h4 - Updaterowt::tests()
	//Test num 13 h4
	array('row' => array('stamp' => 'h4', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 4.5, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 4.5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// h5 - Updaterowt::tests()
	//Test num 14 h5
	array('rebalance' => true, 'row' => array('stamp' => 'h5', 'aid' => 2345, 'sid' => 64, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A0', 'usaget' => 'call', 'usagev' => 12, 'services_data' => ['NEW-SERVICE1'],),
			'expected' => array('in_group' => 0, 'over_group' => 12, 'aprice' => 6, 'charge' => array('retail' => 6,))),

	// i1 - Updaterowt::tests()
	//case I NEW-PLAN-A1 (with two groups) no services
	//Test num 15 i1
	array('row' => array('stamp' => 'i1', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 24),
			'expected' => array('in_group' => 24, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// i2 - Updaterowt::tests()
	//Test num 16 i2
	array('row' => array('stamp' => 'i2', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'gr', 'usagev' => 12),
			'expected' => array('in_group' => 12, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// i3 - Updaterowt::tests()
	//Test num 17 i3
	array('row' => array('stamp' => 'i3', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 50),
			'expected' => array('in_group' => 26, 'over_group' => 24, 'aprice' => 12, 'charge' => array('retail' => 12,))),

	// i4 - Updaterowt::tests()
	//Test num 18 i4
	array('row' => array('stamp' => 'i4', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'gr', 'usagev' => 80),
			'expected' => array('in_group' => 38, 'over_group' => 42, 'aprice' => 6.4, 'charge' => array('retail' => 6.4,))),

	// i5 - Updaterowt::tests()
	//Test num 19 i5
	array('rebalance' => true, 'row' => array('stamp' => 'i5', 'aid' => 23451, 'sid' => 65, 'rates' => array('NEW-CALL-EUROPE' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'usaget' => 'call', 'usagev' => 50.5),
			'expected' => array('in_group' => 0, 'over_group' => 50.5, 'aprice' => 5.1, 'charge' => array('retail' => 5.1,))),

	// is1 - Updaterowt::tests()
	//		//Included services
	//		//is1 should be included
	////Test num 91 is1
	array('row' => array('stamp' => 'is1', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 75, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// is2 - Updaterowt::tests()
	//is2 after the service time  (by the service price cycles not the plan de-activation)
	//Test num 92 is2
	array('row' => array('stamp' => 'is2', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-11-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),

	// is4 - Updaterowt::tests()
	//should be half included
	//Test num 93 is4
	array('row' => array('stamp' => 'is4', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 25, 'over_group' => 50, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),

	// is5 - Updaterowt::tests()
	////should not be included
	////Test num 94 is5
	array('rebalance' => true, 'row' => array('stamp' => 'is5', 'aid' => 9803, 'sid' => 982, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'PLAN-IS1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-IS1', 'service_id' => 10002, 'from' => '2017-09-10', 'to' => '2017-12-21',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),

	// j1 - Updaterowt::tests()
	//		//case J NEW-PLAN-A2 multiple groups with same name
	//Test num 20 j1
	array('row' => array('stamp' => 'j1', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// j2 - Updaterowt::tests()
	//Test num 21 j2
	array('row' => array('stamp' => 'j2', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 75, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 70, 'over_group' => 5, 'aprice' => 2.5, 'charge' => array('retail' => 2.5,))),

	// j3 - Updaterowt::tests()
	//Test num 22 j3
	array('row' => array('stamp' => 'j3', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 15, 'charge' => array('retail' => 15,))),

	// j4 - Updaterowt::tests()
	//Test num 23 j4
	array('rebalance' => true, 'row' => array('stamp' => 'j4', 'aid' => 3345, 'sid' => 66, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'gr', 'usagev' => 30, 'services_data' => ['NEW-SERVICE1',],),
			'expected' => array('in_group' => 0, 'over_group' => 30, 'aprice' => 6, 'charge' => array('retail' => 6,))),

	// k1 - Updaterowt::tests()
	//case K shared account test
	//Test num 24 k1
	array('row' => array('stamp' => 'k1', 'aid' => 7770, 'sid' => 71, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 8, 'services_data' => ['SHARED-SERVICE1'],),
			'expected' => array('in_group' => 8, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// k2 - Updaterowt::tests()
	//Test num 25 k2
	array('rebalance' => true, 'row' => array('stamp' => 'k2', 'aid' => 7770, 'sid' => 72, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 8, 'services_data' => ['SHARED-SERVICE1',],),
			'expected' => array('in_group' => 2, 'over_group' => 6, 'aprice' => 0.6, 'charge' => array('retail' => 0.6,))),

	// k3 - Updaterowt::tests()
	//Test num 26 k3
	array('rebalance' => true, 'row' => array('stamp' => 'k3', 'aid' => 7771, 'sid' => 73, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'SHARED-PLAN-K3', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['SHARED-SERVICE1',],),
			'expected' => array('in_group' => 15, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),

	// k4 - Updaterowt::tests()
	//Test num 27 k4
	array('row' => array('stamp' => 'k4', 'aid' => 7772, 'sid' => 74, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['SHARED-SERVICE1', 'NO-SHARED-SERVICE2',],),
			'expected' => array('in_group' => 15, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),

	// k5 - Updaterowt::tests()
	//Test num 28 k5
	array('rebalance' => true, 'row' => array('stamp' => 'k5', 'aid' => 7772, 'sid' => 75, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['SHARED-SERVICE1', 'NO-SHARED-SERVICE2',],),
			'expected' => array('in_group' => 5, 'over_group' => 15, 'aprice' => 1.5, 'charge' => array('retail' => 1.5,))),

	// l1 - Updaterowt::tests()
	//		/*		 * ** NEW TEST CASES *** */
	//		//case L cost
	////Test num 52 l1
	array('row' => array('stamp' => 'l1', 'aid' => 23457, 'sid' => 77, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'NEW-PLAN-Z5', 'usaget' => 'gr', 'usagev' => 240, 'services_data' => ['NEW-SERVICE5']),
			'expected' => array('in_group' => 30, 'over_group' => 18, 'aprice' => 18, 'charge' => array('retail' => 18,))),

	// l2 - Updaterowt::tests()
	//Test num 53 l2
	array('row' => array('stamp' => 'l2', 'aid' => 23457, 'sid' => 78, 'rates' => array('RATE-L3' => 'retail'), 'plan' => 'PLAN-L2', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-L3',],),
			'expected' => array('in_group' => 30, 'over_group' => 210, 'aprice' => 21, 'charge' => array('retail' => 21,))),

	// l3 - Updaterowt::tests()
	//Test num 54 l3
	array('rebalance' => true, 'row' => array('stamp' => 'l3', 'aid' => 23457, 'sid' => 79, 'rates' => array('RATE-L3' => 'retail'), 'plan' => 'PLAN-L3', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-L2',],),
			'expected' => array('in_group' => 12, 'over_group' => 12, 'aprice' => 12, 'charge' => array('retail' => 12,))),

	// l4 - Updaterowt::tests()
	////Test num 55 l4
	array('rebalance' => true, 'row' => array('stamp' => 'l4', 'aid' => 23458, 'sid' => 80, 'rates' => array('RATE-L3' => 'retail'), 'plan' => 'PLAN-L4-SHARED', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-L2',],),
			'expected' => array('in_group' => 35, 'over_group' => 205, 'aprice' => 20.5, 'charge' => array('retail' => 20.5,))),

	// m1 - Updaterowt::tests()
	//		//case M pooled account services
	////Test num 56 m1
	array('rebalance' => true, 'row' => array('stamp' => 'm1', 'aid' => 8880, 'sid' => 800, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 10, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 10, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// m2 - Updaterowt::tests()
	////Test num 57 m2
	array('rebalance' => true, 'row' => array('stamp' => 'm2', 'aid' => 8881, 'sid' => 801, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// m3 - Updaterowt::tests()
	////Test num 58 m3
	array('rebalance' => true, 'row' => array('stamp' => 'm3', 'aid' => 8882, 'sid' => 803, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 20, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 10, 'over_group' => 10, 'aprice' => 1, 'charge' => array('retail' => 1,))),

	// m4 - Updaterowt::tests()
	//Test num 59 m4
	array('rebalance' => true, 'row' => array('stamp' => 'm4', 'aid' => 8883, 'sid' => 804, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 15, 'services_data' => ['POOLED-SERVICE1',],),
			'expected' => array('in_group' => 10, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),

	// m5 - Updaterowt::tests()
	////Test num 60 m5
	array('row' => array('stamp' => 'm5', 'aid' => 8884, 'sid' => 806, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'POOLED-PLAN-1', 'usaget' => 'call', 'usagev' => 25, 'services_data' => ['POOLED-SERVICE12', 'POOLED-SERVICE11',],),
			'expected' => array('in_group' => 25, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// m6 - Updaterowt::tests()
	//Test num 61 m6
	array('rebalance' => true, 'row' => array('stamp' => 'm6', 'aid' => 8884, 'sid' => 807, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'POOLED-PLAN-1', 'usaget' => 'call', 'usagev' => 10, 'services_data' => ['POOLED-SERVICE12', 'POOLED-SERVICE11',],),
			'expected' => array('in_group' => 5, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),

	// m7 - Updaterowt::tests()
	////Test num 62 m7
	array('rebalance' => true, 'row' => array('stamp' => 'm7', 'aid' => 8885, 'sid' => 809, 'rates' => array('SHARED-RATE' => 'retail'), 'plan' => 'NEW-PLAN-A2', 'usaget' => 'call', 'usagev' => 100, 'services_data' => ['POOLED-SERVICE3',],),
			'expected' => array('in_group' => 60, 'over_group' => 40, 'aprice' => 4, 'charge' => array('retail' => 4,))),

	// n1 - Updaterowt::tests()
	//		// case N - new structure support multiple usage types
	////Test num 63 n1
	array('row' => array('stamp' => 'n1', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N1' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 125, 'services_data' => ['SERVICE-N1',],),
			'expected' => array('in_group' => 125, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// n2 - Updaterowt::tests()
	// N2 - depend on N1
	//Test num 64 n2
	array('row' => array('stamp' => 'n2', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N1b' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'incoming_call', 'usagev' => 275, 'services_data' => ['SERVICE-N1',],),
			'expected' => array('in_group' => 175, 'over_group' => 100, 'aprice' => 10, 'charge' => array('retail' => 10,))),

	// n3 - Updaterowt::tests()
	//Test num 65 n3
	array('row' => array('stamp' => 'n3', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N3' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 240, 'services_data' => ['SERVICE-N3',],),
			'expected' => array('in_group' => 240, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// n4 - Updaterowt::tests()
	// N4 - depend on N1
	//Test num 66 n4
	array('row' => array('stamp' => 'n4', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N3' => 'retail'), 'plan' => 'NEW-PLAN-N1', 'usaget' => 'call', 'usagev' => 475, 'services_data' => ['SERVICE-N3'],),
			'expected' => array('in_group' => 60, 'over_group' => 415, 'aprice' => 7, 'charge' => array('retail' => 7,))),

	// n5 - Updaterowt::tests()
	//Test num 67 n5
	array('row' => array('stamp' => 'n5', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N5' => 'retail'), 'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5),
			'expected' => array('in_group' => 5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// n6 - Updaterowt::tests()
	//Test num 68 n6
	array('row' => array('stamp' => 'n6', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N5' => 'retail'), 'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5),
			'expected' => array('in_group' => 5, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// n7 - Updaterowt::tests()
	//Test num 69 n7
	array('rebalance' => true, 'row' => array('stamp' => 'n7', 'aid' => 9001, 'sid' => 900, 'rates' => array('RATE-N5' => 'retail'), 'plan' => 'NEW-PLAN-N5', 'usaget' => 'call', 'usagev' => 5),
			'expected' => array('in_group' => 0, 'over_group' => 5, 'aprice' => 2.5, 'charge' => array('retail' => 2.5,))),

	// o1 - Updaterowt::tests()
	//		// case O - custom period balance support
	////Test num 70 o1
	array('row' => array('stamp' => 'o1', 'aid' => 9501, 'sid' => 950, 'rates' => array('RATE-O1' => 'retail'), 'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 35, 'services_data' => [['name' => 'SERVICE-O1', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-01 09:00:00+03:00'),
			'expected' => array('in_group' => 35, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// o2 - Updaterowt::tests()
	//Test num 71 o2
	array('row' => array('stamp' => 'o2', 'aid' => 9501, 'sid' => 950, 'rates' => array('RATE-O1' => 'retail'), 'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 62, 'services_data' => [['name' => 'SERVICE-O1', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-16T09:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 62, 'aprice' => 0.62, 'charge' => array('retail' => 0.62,))),

	// o3 - Updaterowt::tests()
	//Test num 72 o3
	array('rebalance' => true, 'row' => array('stamp' => 'o3', 'aid' => 9501, 'sid' => 950, 'rates' => array('RATE-O2' => 'retail'), 'plan' => 'NEW-PLAN-O1', 'usaget' => 'call', 'usagev' => 45, 'services_data' => [['name' => 'SERVICE-O1', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14 09:00:00+03:00',),
			'expected' => array('in_group' => 25, 'over_group' => 20, 'aprice' => 0.02, 'charge' => array('retail' => 0.02,))),

	// o4 - Updaterowt::tests()
	//		// O4- plan includes - use all
	////Test num 73 o4
	array('row' => array('stamp' => 'o4', 'aid' => 9502, 'sid' => 951, 'rates' => array('RATE-O4' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 40, 'services_data' => [['name' => 'SERVICE-O4', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14 09:00:00+03:00',),
			'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// o5 - Updaterowt::tests()
	//Test num 74 o5  try to use service includes
	array('row' => array('stamp' => 'o5', 'aid' => 9502, 'sid' => 951, 'rates' => array('RATE-O4' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 30, 'services_data' => [['name' => 'SERVICE-O4', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// o6 - Updaterowt::tests()
	// O6- plan includes - use part of it
	// O7 - try to use service includes
	// p1 service with limited cycle's
	//Test num 75 o6
	array('rebalance' => true, 'row' => array('stamp' => 'o6', 'aid' => 9502, 'sid' => 951, 'rates' => array('RATE-O4' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-O4', 'from' => '2017-09-01', 'to' => '2017-09-14',]], 'urt' => '2017-09-14',),
			'expected' => array('in_group' => 70, 'over_group' => 5, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),

	// p1 - Updaterowt::tests()
	////Test num 76 p1
	array('row' => array('stamp' => 'p1', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 7500000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-09-01',),
			'expected' => array('in_group' => 7500000, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// p2 - Updaterowt::tests()
	//Test num 77 p2
	array('row' => array('stamp' => 'p2', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-09-14',),
			'expected' => array('in_group' => 75000000, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// p3 - Updaterowt::tests()
	//Test num 78 p3
	array('row' => array('stamp' => 'p3', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-09-30',),
			'expected' => array('in_group' => 75000000, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// p4 - Updaterowt::tests()
	//Test num 79 p4
	array('row' => array('stamp' => 'p4', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 7500000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-10-01 00:00:01+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 7500000, 'aprice' => 8, 'charge' => array('retail' => 8,))),

	// p5 - Updaterowt::tests()
	//Test num 80 p5
	array('rebalance' => true, 'row' => array('stamp' => 'p5', 'aid' => 9503, 'sid' => 952, 'rates' => array('INTERNET' => 'retail'), 'plan' => 'NEW-PLAN-O4', 'usaget' => 'data', 'usagev' => 75000000, 'services_data' => [['name' => '2GB_INTERNET_FOR_1_CYCLE', 'from' => '2017-09-01', 'to' => '2018-09-01',]], 'urt' => '2017-10-14',),
			'expected' => array('in_group' => 0, 'over_group' => 75000000, 'aprice' => 75, 'charge' => array('retail' => 75,))),

	// q1 - Updaterowt::tests()
	////Test num 81 q1
	array('row' => array('stamp' => 'q1', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 70, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-25 11:00:00+03:00',),
			'expected' => array('in_group' => 70, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// q2 - Updaterowt::tests()
	//Test num 82 q2
	array('row' => array('stamp' => 'q2', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q2' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 30, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-26 11:00:00+03:00',),
			'expected' => array('in_group' => 30, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// q3 - Updaterowt::tests()
	//Test num 83 q3
	array('row' => array('stamp' => 'q3', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q2' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-23 11:00:00+03:00',),
			'expected' => array('in_group' => 150, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// q4 - Updaterowt::tests()
	//Test num 84 q4
	array('rebalance' => true, 'row' => array('stamp' => 'q4', 'aid' => 9702, 'sid' => 971, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 250, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-20', 'to' => '2017-10-01',], ["name" => "SERVICE-Q2", "from" => "2017-09-25", "to" => "2017-09-30", "service_id" => 4568]], 'urt' => '2017-09-27 11:00:00+03:00',),
			'expected' => array('in_group' => 120, 'over_group' => 130, 'aprice' => 1.3, 'charge' => array('retail' => 1.3,))),

	// r1 - Updaterowt::tests()
	////Test num 85 r1
	array('row' => array('stamp' => 'r1', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 235, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-11 11:00:00+03:00',),
			'expected' => array('in_group' => 235, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// r2 - Updaterowt::tests()
	//Test num 86 r2
	array('row' => array('stamp' => 'r2', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 245, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-12 11:00:00+03:00',),
			'expected' => array('in_group' => 245, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// r3 - Updaterowt::tests()
	//Test num 87 r3
	array('row' => array('stamp' => 'r3', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 70, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-13 11:00:00+03:00',),
			'expected' => array('in_group' => 20, 'over_group' => 50, 'aprice' => 0.5, 'charge' => array('retail' => 0.5,))),

	// r4 - Updaterowt::tests()
	//Test num 88 r4
	array('row' => array('stamp' => 'r4', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 120, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-09 11:00:00+03:00'),
			'expected' => array('in_group' => 0, 'over_group' => 120, 'aprice' => 1.2, 'charge' => array('retail' => 1.2,))),

	// r5 - Updaterowt::tests()
	//Test num 89 r5
	array('row' => array('stamp' => 'r5', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 60, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-21 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 60, 'aprice' => 0.6, 'charge' => array('retail' => 0.6,))),

	// r6 - Updaterowt::tests()
	//Test num 90 r6
	array('rebalance' => true, 'row' => array('stamp' => 'r6', 'aid' => 9802, 'sid' => 981, 'rates' => array('RATE-Q1' => 'retail'), 'plan' => 'NEW-PLAN-Q1', 'usaget' => 'call', 'usagev' => 75, 'services_data' => [['name' => 'SERVICE-Q1', 'from' => '2017-09-10', 'to' => '2017-09-21', "service_id" => 1234], ["name" => "SERVICE-Q1", "from" => "2017-09-10", "to" => "2017-09-21", "service_id" => 1235]], 'urt' => '2017-09-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 75, 'aprice' => 0.8, 'charge' => array('retail' => 0.8,))),

	// s1 - Updaterowt::tests()
	//		// s custom period with pooled/shard
	//		// s1 & s2 are one test case for check service period pooled
	////Test num 95 s1
	array('row' => array('stamp' => 's1', 'aid' => 24, 'sid' => 25, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 15, 'services_data' => [['name' => 'PERIOD_POOLED', 'from' => '2017-08-01', 'to' => '2017-09-01']], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 15, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// s2 - Updaterowt::tests()
	//Test num 96 s2
	array('rebalance' => true, 'row' => array('stamp' => 's2', 'aid' => 24, 'sid' => 26, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 10, 'services_data' => [['name' => 'PERIOD_POOLED', 'from' => '2017-08-01', 'to' => '2017-09-01',]], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 5, 'over_group' => 5, 'aprice' => 5, 'charge' => array('retail' => 5,))),

	// s3 - Updaterowt::tests()
	//s3 & s4 are one test case for check service period shard
	////Test num 97 s3
	array('row' => array('stamp' => 's3', 'aid' => 27, 'sid' => 28, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 20, 'services_data' => [['name' => 'PERIOD_SHARED', 'from' => '2017-08-01', 'to' => '2017-09-01']], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 20, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// s4 - Updaterowt::tests()
	//Test num 98 s4
	array('row' => array('stamp' => 's4', 'aid' => 27, 'sid' => 29, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 15, 'services_data' => [['name' => 'PERIOD_SHARED', 'from' => '2017-08-01', 'to' => '2017-09-01'],], 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 10, 'over_group' => 5, 'aprice' => 5, 'charge' => array('retail' => 5,))),

	// t1 - Updaterowt::tests()
	//T test for wholesale
	//Test num 99 t1
	array('row' => array('stamp' => 't1', 'aid' => 27, 'sid' => 30, 'rates' => array('NEW-CALL-USA' => 'retail', 'CALL' => 'wholesale'), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 60, 'urt' => '2017-08-14 11:00:00+03:00'),
			'expected' => array('in_group' => 0, 'over_group' => 60, 'aprice' => 30, 'charge' => array('retail' => 30, 'wholesale' => 60))),

	// u1 - Updaterowt::tests()
	//test for prepriced line
	//Test num 100 u1
	array('rebalance' => true, 'row' => array('stamp' => 'u1', 'aid' => 27, 'sid' => 31, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'prepriced' => "true", 'type' => 'Preprice_Dynamic', 'uf' => array('preprice' => 100), 'usaget' => 'call', 'usagev' => 15, 'urt' => '2017-08-14 11:00:00+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 15, 'aprice' => 100, 'charge' => array('retail' => 100,))),

	// v1 - Updaterowt::tests()
	////Test num 101 v1
	//// Service price overriding
	array('row' => array('stamp' => 'v1', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 21, 'services_data' => [['name' => 'SERVICE_OVERRIDE_PRICE', 'service_id' => 20003,'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 21, 'aprice' => 3.22222, 'charge' => array('retail' => 3.22222,))),

	// v2 - Updaterowt::tests()
	//Test num 102 v2
	// Service price overriding
	array('row' => array('stamp' => 'v2', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 21, 'services_data' => [['name' => 'SERVICE_INCLUDE_PLUS_OVERRIDE_PRICE', 'service_id' => 20004, 'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 10, 'over_group' => 11, 'aprice' => 3.555555, 'charge' => array('retail' => 3.555555,))),

	// v3 - Updaterowt::tests()
	//Test num 103 v3
	// Service price overriding
	array('row' => array('stamp' => 'v3', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW-PLAN-A1', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 80, 'services_data' => [['name' => 'SERVICE_OVERRIDE_PRICE', 'service_id' => 20003,'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 30, 'aprice' => 12.22222, 'charge' => array('retail' => 12.22222,))),

	// v4 - Updaterowt::tests()
	////Test num 104 v4
	// Service price overriding - service wins if both service and plan override the same product
	array('rebalance' => true, 'row' => array('stamp' => 'v4', 'aid' => 33, 'sid' => 34, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'NEW_PLAN_OVERRIDE_USA', 'type' => 'service_override_price', 'usaget' => 'call', 'usagev' => 10, 'services_data' => [['name' => 'SERVICE_OVERRIDE_PRICE', 'service_id' => 20003, 'from' => '2017-08-01', 'to' => '2030-09-01'],], 'urt' => '2018-07-14 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 10, 'aprice' => 1.11111, 'charge' => array('retail' => 1.11111,))),

	// w1 - Updaterowt::tests()
	//		//Test num 105 w1
	////Service quantity based quota
	//		//half use
	array('row' => array('stamp' => 'w1', 'aid' => 35, 'sid' => 36, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'MUL', 'service_id' => 10001, 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-14 23:11:45+03:00',),
			'expected' => array('in_group' => 150, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w10 - Updaterowt::tests()
	//both subs multiple quantity by 1 , check : half use ,full use, over use
	//half use
	array('row' => array('stamp' => 'w10', 'aid' => 40, 'sid' => 41, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w11 - Updaterowt::tests()
	array('row' => array('stamp' => 'w11', 'aid' => 40, 'sid' => 42, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w12 - Updaterowt::tests()
	//full use
	array('row' => array('stamp' => 'w12', 'aid' => 40, 'sid' => 41, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w13 - Updaterowt::tests()
	array('row' => array('stamp' => 'w13', 'aid' => 40, 'sid' => 42, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w14 - Updaterowt::tests()
	//over use
	array('row' => array('stamp' => 'w14', 'aid' => 40, 'sid' => 41, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),

	// w15 - Updaterowt::tests()
	array('rebalance' => true, 'row' => array('stamp' => 'w15', 'aid' => 40, 'sid' => 42, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),

	// w16 - Updaterowt::tests()
	//1st sub multiple quantity by 1 ,2nd not have any group
	array('rebalance' => true, 'row' => array('stamp' => 'w16', 'aid' => 43, 'sid' => 44, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w17 - Updaterowt::tests()
	array('rebalance' => true, 'row' => array('stamp' => 'w17', 'aid' => 43, 'sid' => 45, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),

	// w18 - Updaterowt::tests()
	//		//BRCD-2581 usage volume 0 - check the price isn't NaN
	array('row' => array('stamp' => 'w18', 'aid' => 100, 'sid' => 101, 'rates' => array('pricing_method_volume' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 0, 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 0, 'out_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w19 - Updaterowt::tests()
	//w19 + w20 are test for https://billrun.atlassian.net/browse/BRCD-1493
	array('row' => array('stamp' => 'w19', 'aid' => 50, 'sid' => 551, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 110, 'urt' => '2018-11-16 23:11:45+03:00',
				'services_data' => [
						['name' => 'CALL', 'from' => '2017-08-01', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "123", "quantity" => 1],
						['name' => 'CALL', 'from' => '2017-08-01', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "1234", "quantity" => 1]
				]),
			'expected' => array('in_group' => 110, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 110,))),

	// w2 - Updaterowt::tests()
	//full use
	array('row' => array('stamp' => 'w2', 'aid' => 35, 'sid' => 36, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'MUL', 'service_id' => 10001, 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-15 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w20 - Updaterowt::tests()
	array('rebalance' => true, 'row' => array('stamp' => 'w20', 'aid' => 50, 'sid' => 551, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 100, 'urt' => '2018-11-16 23:11:45+03:00',
				'services_data' => [
						['name' => 'CALL', 'from' => '2017-08-01 ', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "123", "quantity" => 1],
						['name' => 'CALL', 'from' => '2017-08-01', 'to' => '2030-09-01', "plan_included" => false, "service_id" => "1234", "quantity" => 1]
				]),
			'expected' => array('in_group' => 90, 'over_group' => 10, 'aprice' => 10, 'charge' => array('retail' => 110,))),

	// w3 - Updaterowt::tests()
	//		//over use
	array('rebalance' => true, 'row' => array('stamp' => 'w3', 'aid' => 35, 'sid' => 36, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'MUL', 'service_id' => 10001, 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),

	// w4 - Updaterowt::tests()
	//1st sub multiple quantity by 1 ,2nd multiple quantity by 2, check : half use ,full use, over use
	//		//half use
	array('row' => array('stamp' => 'w4', 'aid' => 37, 'sid' => 38, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w5 - Updaterowt::tests()
	array('row' => array('stamp' => 'w5', 'aid' => 37, 'sid' => 39, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 50, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w6 - Updaterowt::tests()
	//full use
	array('row' => array('stamp' => 'w6', 'aid' => 37, 'sid' => 38, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 100, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 100, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w7 - Updaterowt::tests()
	array('row' => array('stamp' => 'w7', 'aid' => 37, 'sid' => 39, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 100, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 100, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// w8 - Updaterowt::tests()
	//over use
	array('row' => array('stamp' => 'w8', 'aid' => 37, 'sid' => 38, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 1]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 150, 'aprice' => 150, 'charge' => array('retail' => 150,))),

	// w9 - Updaterowt::tests()
	array('rebalance' => true, 'row' => array('stamp' => 'w9', 'aid' => 37, 'sid' => 39, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 150, 'services_data' => [['name' => 'POOLD', 'from' => '2017-08-01', 'to' => '2030-09-01', "quantity_affected" => true, "quantity" => 2]], 'urt' => '2018-11-16 23:11:45+03:00',),
			'expected' => array('in_group' => 0, 'over_group' => 150, 'aprice' => 150, 'charge' => array('retail' => 150,))),

	// x1 - Updaterowt::tests()
	/*test for BRCD-2627 - subscriber purchase service for one cycle at 2020-05-10, the cdr is from 2020-06-05, it will not create/use in the service balance */
	array('row' => array('stamp' => 'x1', 'aid' => 52, 'sid' => 53, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50, 'services_data' => [
					["name" => "ONE_CYCLE_ADDON",
							"from" => "2020-05-10",
							"to" => "2130-09-01",
						"service_id" => "123",
							"creation_time" => "2020-05-10"],
				],
					'urt' => '2020-06-05',),
			'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),

	// x2 - Updaterowt::tests()
	//BRCD-2865 Override product's price by percentage
	//subscriber without override plan - will charge full price
	array('rebalance' => true, 'row' => array('stamp' => 'x2', 'aid' => 52, 'sid' => 54, 'rates' => array('CALL_OVERRIDE_B' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
					'urt' => '2020-06-05',),
				'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),

	// x3 - Updaterowt::tests()
	//BRCD-2865 Override product's price by percentage
	//subscriber with override plan - override 100% will charge 0
	array('row' => array('stamp' => 'x3', 'aid' => 52, 'sid' => 5555, 'rates' => array('CALL_OVERRIDE_B' => 'retail'), 'plan' => 'OVERRIDE', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
					'urt' => '2020-06-05 ',),
				'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// x4 - Updaterowt::tests()
	//BRCD-2865 Override product's price by percentage
	//subscriber with override plan - override 50% will charge 50%
	array('rebalance' => true, 'row' => array('stamp' => 'x4', 'aid' => 52, 'sid' => 5555, 'rates' => array('CALL_OVERRIDE_A' => 'retail'), 'plan' => 'OVERRIDE', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
					'urt' => '2020-06-05',),
				'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 25, 'charge' => array('retail' => 25,))),

	// x5 - Updaterowt::tests()
	//BRCD-2865 Override product's price by percentage
	//subscriber without override service - will charge full price
	array('rebalance' => true, 'row' => array('stamp' => 'x5', 'aid' => 52, 'sid' => 54, 'rates' => array('CALL_OVERRIDE_SERVICE_A' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 50,
					'urt' => '2020-06-05',),
				'expected' => array('in_group' => 0, 'over_group' => 50, 'aprice' => 50, 'charge' => array('retail' => 50,))),

	// x6 - Updaterowt::tests()
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

	// x7 - Updaterowt::tests()
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

	// xa1 - Updaterowt::tests()
	//test rebalance conditions part a
	array('row' => array('stamp' => 'xa1', "aid" => 741, 'sid' => 147, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1']),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0)),),

	// xa2 - Updaterowt::tests()
	//part b
	array('rebalance' => true, 'conditions' => [741 => [[[['field_name' => 'usaget', 'op' => '$eq', 'value' => 'gr']]]]], 'groupExpected' =>
				[
					"NEW-SERVICE1" => ["AAA" => ["count" => 1, "left" => 0, "total" => 40, "usagev" => 40]],
					"NEW-SERVICE4" => ["SR4" => ["count" => 0, "left" => 200, "total" => 200, "usagev" => 0]],
				],
				'row' => array('stamp' => 'xa2', 'aid' => 741, 'sid' => 147, 'rates' => array('NEW-VEG' => 'retail'), 'plan' => 'PLAN_A', 'usaget' => 'gr', 'usagev' => 40, 'services_data' => ['NEW-SERVICE4']),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0,))),

	// z1 - Updaterowt::tests()
	/* MDC
	* Make sure there’s no regression -
	multi_day_cycle flag is “false“
	there’s no billrun object from the last 3 months  -remove all billruns
	process line and make sure it gets the runtime billrun key */
	array('preTest' => ['removeBillruns'], 'row' => array('stamp' => 'z1', "aid" => 10002, 'sid' => 20002, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-d')),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z10 - Updaterowt::tests()
	//brcd 3471 -  same service in same period , the usage group will duplicate
	array('row' => array('stamp' => 'z10', 'aid' => 3471, 'sid' => 34711, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 220,
					'urt' => '2022-02-05', 'services_data' => [
						[
							"name" => "A",
							"from" => "time*2020-02-23",
							"to" => "time*2122-02-23",
							"service_id" =>"1725648430542577",
							"creation_time" => "time*2022-02-24"
						],
						[
							"name" => "A",
							"from" => "time*2020-02-23T22:00:00Z",
							"to" => "time*2122-02-23T22:00:00Z",
							"service_id" => "1725648430542649",
							"creation_time" => "time*2022-02-24"
						]
					],),
				'expected' => array('in_group' => 200, 'over_group' => 20, 'aprice' => 20, 'charge' => array('retail' => 23.4,))),

	// z2 - Updaterowt::tests()
	/*
	* multi_day_cycle flag is “false“
	exists billrun object from the last month - run last billrun
	process line and make sure it gets the billrun key -
	/according to the “urt“ time, only on future urt will affect on billrun */
	array('preTest' => ['runCycle'], 'row' => array('runCycle' => ['stamp' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 'force_accounts' => [8]],
					'stamp' => 'z2', "aid" => 10025, 'sid' => 20025, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-d')),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z3 - Updaterowt::tests()
	/* multi_day_cycle flag is “true”
	exists billrun object from the last billrun cycle - run last billrun
	process line from the current cycle,
	and make sure it gets the right billrun key -
	according to the customer’s “invoicing_day“ field
	1. customer invoiceing day 1  urt from past in this montu-  billrun will be run time billrun
	*/
	array('preTest' => ['multiDayCycle', 'runCycle'], 'row' => array(
					'multiDayCycle' => true, 'runCycle' => ['stamp' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 'force_accounts' => [8]],
					'stamp' => 'z3', "aid" => 10000, 'sid' => 20000, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-1'), "foreign" => ["account" => ["invoicing_day" => "1"]]),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z4 - Updaterowt::tests()
	/* 	  2. customer invoiceing day 1  urt from futurre in this month - billrun will be run time billrun */
	array('preTest' => ['multiDayCycle', 'runCycle'], 'row' => array(
					'multiDayCycle' => true, 'runCycle' => ['stamp' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 'force_accounts' => [8]],
					'stamp' => 'z4', "aid" => 11000, 'sid' => 21000, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-28'), "foreign" => ["account" => ["invoicing_day" => "1"]]),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z5 - Updaterowt::tests()
	/*
	3. customer invoiceing day 28  urt from past in this montu, befor currunt cycle - will take the prev cycle */
	array('preTest' => ['removeBillruns', 'multiDayCycle', 'runCycle'], 'row' => array(
					'multiDayCycle' => true, 'runCycle' => ['stamp' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 'force_accounts' => [8]],
					'stamp' => 'z5', "aid" => 10027, 'sid' => 20027, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-10'), "foreign" => ["account" => ["invoicing_day" => "28"]],),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z6 - Updaterowt::tests()
	/*
	4. customer invoiceing day 24 urt from futurre in this month  - get currnt billrun*/
	array('preTest' => ['multiDayCycle'], 'row' => array(
					'multiDayCycle' => true,
					'stamp' => 'z6', "aid" => 10024, 'sid' => 20024, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-28'), "foreign" => ["account" => ["invoicing_day" => "24"]],),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z7 - Updaterowt::tests()
	/*-- no prev cycle run --*/
	array('preTest' => ['removeBillruns'], 'row' => array(
					'multiDayCycle' => true,
					'stamp' => 'z7', "aid" => 10023, 'sid' => 20023, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-28',strtotime('-1 month')), "foreign" => ["account" => ["invoicing_day" => "22"]],),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z8 - Updaterowt::tests()
	/*multi_day_cycle flag is “true” + past  cycle is run(for example :
	* now is 26/05/2020 ,the customer invoicing_day is 25, and cycle of billrun 202005 is run ),
	* process cdr with urt before 26/05/2020 - the billrun will be 202006.
	*/
	array('preTest' => ['removeBillruns', 'multiDayCycle', 'runCycle'], 'row' => array(
					'multiDayCycle' => true, 'runCycle' => ['stamp' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 'force_accounts' => [8]],
					'stamp' => 'z8', "aid" => 10020, 'sid' => 20020, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-21'), "foreign" => ["account" => ["invoicing_day" => date('d',strtotime('-1 day'))]],),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),

	// z9 - Updaterowt::tests()
	/*multi_day_cycle flag is “true” + past  cycle is run(for example :
	*  now is 26/05/2020 ,the customer invoicing_day is 25,
	*  and cycle of billrun 202005 is run ) ,
	* process cdr with urt between  26/05/2020 to 26/06/2020 - the billrun will be 202006. */
	array('preTest' => ['removeBillruns', 'multiDayCycle', 'runCycle'], 'row' => array(
					'multiDayCycle' => true, 'runCycle' => ['stamp' => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')), 'force_accounts' => [8]],
					'stamp' => 'z9', "aid" => 10021, 'sid' => 20021, 'rates' => array('NEW-CALL-USA' => 'retail'), 'plan' => 'PLAN_A', 'usagev' => 40, 'services_data' => ['NEW-SERVICE1'], 'urt' => date('Y-m-d',strtotime('-1 day')), "foreign" => ["account" => ["invoicing_day" => date('d',strtotime('-1 day'))]],),
				'expected' => array('in_group' => 40, 'over_group' => 0, 'aprice' => 0, 'charge' => array('retail' => 0))),
];
