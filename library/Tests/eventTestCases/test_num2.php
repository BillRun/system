<?php

class Test_Case_2 {
    public function test_case() {
        return array(
            //Cdr value is Exactly the event condition -  event will created
            'test_num' => 2,
            'row' => array('stamp' => 1002, 'aid' => 1234, 'sid' => 51, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 30, 'urt' => '2018-11-16 23:11:45+03:00', 'services_data' => array(array('name' => 'SERVICE1', 'from' => '2017-08-01 00:00:00+03:00', 'to' => '2030-09-01 00:00:00+03:00', 'service_id' => '123'))),
            'functions' => array('isEventCreated'),
            'expected' => array('event_code' => array('group1' => array('ShouldCreate' => true)))
        );
    }
}
