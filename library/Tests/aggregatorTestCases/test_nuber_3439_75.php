<?php

class Test_Case_3439_75 {
    public function test_case() {
    $stamp = Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month'));
        return [
    'preRun' => [
         'notallowPremature',
         'removeBillruns',
    ],
    'test' => [
        'test_number' => 753439,
        'aid' => 'NaN',
        'function' => [
            'shouldRun_Aggregate',
            'MultiDayNotallowPremature',
        ],
        'options' => [
            'stamp' => $stamp,
            'invoicing_days' => [
                '1',
                '2',
                '3',
                '4',
                '5',
                '6',
                '7',
                '8',
                '9',
                '10',
                '11',
                '12',
                '13',
                '14',
                '15',
                '16',
                '17',
                '18',
                '19',
                '20',
                '21',
                '22',
                '23',
                '24',
                '25',
                '26',
                '27',
                '28',
            ],
            'force_accounts' => [
                100003439,
                100013439,
                100023439,
                100033439,
                100043439,
                100053439,
                100063439,
                100073439,
                100083439,
                100093439,
                100103439,
                100113439,
                100123439,
                100133439,
                100143439,
                100153439,
                100163439,
                100173439,
                100183439,
                100193439,
                100203439,
                100213439,
                100223439,
                100233439,
                100243439,
                100253439,
                100263439,
                100273439,
            ],
        ],
    ],
    'expected' => [
        'shouldRunAggregate'=>false
    ],
    
    'postRun' => [
        'multi_day_cycle_false',
    ],
    'duplicate' => true,
];
    }
}
