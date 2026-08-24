<?php

class Test_Case_25 {
    public function test_case() {
        return array (
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
);
    }
}
