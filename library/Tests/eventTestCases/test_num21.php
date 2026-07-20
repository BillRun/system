<?php

class Test_Case_21 {
    public function test_case() {
        return array(
            'test_num' => 21,
            'row' => array('stamp' => 1021, 'aid' => 1234, 'sid' => 59, 'rates' => array('CALL' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'call', 'usagev' => 8, 'urt' => '2020-10-16 23:11:45+03:00', 'services_data' => array(array('name' => 'ISG', 'from' => '2017-08-01 00:00:00+03:00', 'to' => '2030-09-01 00:00:00+03:00', 'service_id' => '123'))),
            'functions' => array('isEventCreated'),
            'expected' => array('event_code' => array('graet' => array('ShouldCreate' => false)))
        );
    }
}
