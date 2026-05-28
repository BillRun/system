<?php

class Test_Case_82 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 30-12-2020 to 30-03-2021, the service from is equal to revision from , 1st cycle  ',
        'test_number' => 82,
        'aid' => 991653,
        'sid' => 991654,
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
                991653,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 991653,
            'after_vat' => [
                991653 => 69.10593943749474,
            ],
            'total' => 69.10593943749474,
            'vatable' => 59.064905502132255,
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
