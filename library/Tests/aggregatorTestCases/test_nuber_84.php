<?php

class Test_Case_84 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 31-12-2020 to 31-03-2021, the service from is equal to revision from , 1st cycle  ',
        'test_number' => 84,
        'aid' => 991655,
        'sid' => 991656,
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
                991655,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 991655,
            'after_vat' => [
                991655 => 71.48890286637386,
            ],
            'total' => 71.48890286637386,
            'vatable' => 61.10162638151613,
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
