<?php

class Test_Case_3439_76 {
    function getDaysUntilToday() {
        $today = (int)date('j');
        $days = [];
            for ($day = 1; $day < $today; $day++) {
            $days[] = (string)$day;
        }
        return $days;
    }

    function getAccountNumbersForDays($daysArray) {
        $accountNumbers = [];
    
        foreach ($daysArray as $day) {
            $accountNumbers[] = '100' . str_pad($day, 2, '0', STR_PAD_LEFT) . '3439';
        }
    
        return $accountNumbers;
    }
    

    public  function getAccountNumbersWithDays() {
        $daysArray = self::getDaysUntilToday();
        $accountNumbersWithDays = [];
        foreach ($daysArray as $day) {
            $accountNumber = '100' . str_pad($day - 1, 2, '0', STR_PAD_LEFT) . '3439';
                    $accountNumbersWithDays[$accountNumber] = $day;
        }
        return $accountNumbersWithDays;
    }
    
    public function test_case() {
    $stamp = Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month'));
        return [
    'preRun' => [
         'notallowPremature',
         'removeBillruns',
         'overrideConfig' => [
            'key' => 'billrun.multi_day_cycle',
            'value' => true,
        ]
    ],
    'test' => [
        'test_number' => 763439,
        'aid' => 'NaN',
        'function' => [
            'shouldRun_Aggregate',
            'MultiDayNotallowPremature',
        ],
        'options' => [
            'stamp' => $stamp,
            'invoicing_days' => self::getDaysUntilToday(),
            'force_accounts' => self::getAccountNumbersForDays(self::getDaysUntilToday()),
        ],
    ],
    'expected' => [
        'shouldRunAggregate'=>true,
        'accounts'=>self::getAccountNumbersWithDays()
    ],
    
    'postRun' => [
        'multi_day_cycle_false',
    ],
    'duplicate' => true,
];
    }
}
