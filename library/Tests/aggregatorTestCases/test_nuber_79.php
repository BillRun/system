<?php

class Test_Case_79 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 01-12-2020 to 01-03-2021, the service from is equal to revision from ',
        'test_number' => 79,
        'aid' => 991649,
        'sid' => 991650,
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
                991649,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 991649,
            'after_vat' => [
                991650 => 73.871866295253,
            ],
            'total' => 73.871866295253,
            'vatable' => 63.1383472609,
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
