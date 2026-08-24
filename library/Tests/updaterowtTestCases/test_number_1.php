<?php

class Test_Case_1 {
    public function test_case() {
        return array (
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
);
    }
}
