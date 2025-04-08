<?php

class Test_Case_3 {
    public function test_case() {
        return array (
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
);
    }
}
