<?php

class Test_Case_7 {
    public function test_case() {
        return array(
            //Cdr value is Exactly the event condition -  event will created **unlimited group
            'test_num' => 7,
            'row' => array('stamp' => 1007, 'aid' => 1234, 'sid' => 53, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 80, 'urt' => '2018-11-16 23:11:45+03:00', 'services_data' => array(array('name' => 'UNLIMITETED1', 'from' => '2017-08-01 00:00:00+03:00', 'to' => '2030-09-01 00:00:00+03:00', 'service_id' => '123'))),
            'functions' => array('isEventCreated'),
            'expected' => array('event_code' => array('unlimitedGroup' => array('ShouldCreate' => true)))
        );
    }
}
