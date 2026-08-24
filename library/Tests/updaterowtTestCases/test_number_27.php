<?php

class Test_Case_27 {
    public function test_case() {
        return array (
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
);
    }
}
