<?php

class Test_Case_87 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles + discount unlimit,discount for 3 cyces  from 02-12-2020 to 02-03-2021, the service from is more future to revision from , 4th cycle ',
        'test_number' => 87,
        'aid' => 991657,
        'sid' => 991658,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202104',
            'force_accounts' => [
                991657,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 991657,
            'after_vat' => [
                991657 => 61.480456465081524,
            ],
            'total' => 61.480456465081524,
            'vatable' => 52.54739868810387,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => '',
];
    }
}
