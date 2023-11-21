<?php

class Test_Case_76 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 01-12-2020 to 01-03-2021, the service from is future , 1st cycle  ',
        'test_number' => 76,
        'aid' => 991647,
        'sid' => 991648,
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
                991647,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 991647,
            'after_vat' => [
                991648 => 0,
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
