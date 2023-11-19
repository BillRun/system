<?php

class Test_Case_81 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 02-12-2020 to 02-03-2021, the service from is equal to revision from ',
        'test_number' => 81,
        'aid' => 991651,
        'sid' => 991652,
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
                991651,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 991651,
            'after_vat' => [
                991652 => 71.48890286637386,
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
