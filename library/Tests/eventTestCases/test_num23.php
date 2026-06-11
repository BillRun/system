<?php

class Test_Case_23
{
    ///test for recurring events (reached constant) with float values caused division by zero exception
     ///https://billrun.atlassian.net/browse/BRCD-5108
    public function test_case()
    {
        return array(
            'test_num' => 23,
            'row' => array(
                'stamp' => 10233, 
                'aid' => 770, 
                'sid' => 77051, 
                'rates' => array('RATE_S' => 'retail'), 
                'plan' => 'WITH_NOTHING', 
                'type' => 'realTime', 
                'usaget' => 'call',
                 'usagev' => 2, 
                 'urt' => '2025-12-16 23:11:45+03:00', 
                 'services_data' => array(
                    array(
                        'name' => 'SERVICE1_DD', 
                        'from' => '2017-08-01 00:00:00+03:00',
                         'to' => '2130-09-01 00:00:00+03:00',
                          'service_id' => '123456'
                        )
                    )
                ),
            'functions' => array('isEventCreated'),
            'expected' => array('event_code' => array('a' => array('ShouldCreate' => true)))
        );
    }
}
