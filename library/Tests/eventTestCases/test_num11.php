<?php

class Test_Case_11 {
    public function test_case() {
        return array(
            			//			//Cdr value is Exactly the event condition -  event will created


            'test_num' => 11,
            'row' => array('stamp' => 1011, 'aid' => 1234, 'sid' => 55, 'rates' => array('SMS' => 'retail'), 'plan' => 'WITH_NOTHING', 'type' => 'realTime', 'usaget' => 'sms', 'usagev' => 10, 'urt' => '2018-11-16 23:11:45+03:00', 'services_data' => array(array('name' => 'SMS', 'from' => '2017-08-01 00:00:00+03:00', 'to' => '2030-09-01 00:00:00+03:00', 'service_id' => '123'))),
            'functions' => array('isEventCreated'),
            'expected' => array('event_code' => array('activitiTpeExceedingUnits' => array('ShouldCreate' => true)))
        );
    }
}
