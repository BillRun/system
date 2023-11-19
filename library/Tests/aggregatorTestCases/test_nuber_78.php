<?php

class Test_Case_78 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 01-12-2020 to 01-03-2021, the service from is equal to revision from , 1st cycle  ',
        'test_number' => 78,
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
            'stamp' => '202101',
            'force_accounts' => [
                991649,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 991649,
            'after_vat' => [
                991650 => 0,
            ],
            'total' => 0,
            'vatable' => 0,
            'vat' => 0,
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
