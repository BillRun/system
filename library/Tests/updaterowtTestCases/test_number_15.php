<?php

class Test_Case_15 {
    public function test_case() {
        return array (
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
);
    }
}
