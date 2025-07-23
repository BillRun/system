<?php

class Test_Case_10 {
    public function test_case() {
        return array(
            			//Cdr value is under the event condition - event will not created


            'test_num' => 10,
            'row' => array('stamp' => 1010, 'aid' => 1234, 'sid' => 55, 'rates' => array('SMS' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'sms', 'usagev' => 100, 'urt' => '2018-11-16 23:11:45+03:00', 'services_data' => array(array('name' => 'SMS', 'from' => '2017-08-01 00:00:00+03:00', 'to' => '2030-09-01 00:00:00+03:00', 'service_id' => '123'))),
            'functions' => array('isEventCreated'),
            'expected' => array('event_code' => array('activitiTpeExceedingUnits' => array('ShouldCreate' => false)))
        );
    }
}
