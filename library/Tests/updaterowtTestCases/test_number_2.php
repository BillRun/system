<?php

class Test_Case_2 {
    public function test_case() {
        return array (
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
);
    }
}
