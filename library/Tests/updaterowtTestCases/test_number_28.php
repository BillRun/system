<?php

class Test_Case_28 {
    public function test_case() {
        return array (
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
);
    }
}
