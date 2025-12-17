<?php

class Test_Case_8 {
    public function test_case() {
        return array (
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
);
    }
}
