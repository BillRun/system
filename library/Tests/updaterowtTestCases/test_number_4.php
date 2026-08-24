<?php

class Test_Case_4 {
    public function test_case() {
        return array (
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
);
    }
}
