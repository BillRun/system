<?php

class Test_Case_3 {
    public function test_case() {
        return array(
            //Cdr value is  cross  the event condition - event will created
            'test_num' => 3,
            'row' => array('stamp' => 1003, 'aid' => 1234, 'sid' => 52, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 51, 'urt' => '2018-11-16 23:11:45+03:00', 'services_data' => array(array('name' => 'SERVICE1', 'from' => '2017-08-01 00:00:00+03:00', 'to' => '2030-09-01 00:00:00+03:00', 'service_id' => '123'))),
            'functions' => array('isEventCreated'),
            'expected' => array('event_code' => array('group1' => array('ShouldCreate' => true)))
        );
    }
}
