<?php

class Test_Case_85 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 31-12-2020 to 31-03-2021, the service from is equal to revision from ',
        'test_number' => 85,
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
            'stamp' => '202104',
            'force_accounts' => [
                991655,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 991655,
            'after_vat' => [
                991655 => 2.3829634288791293,
            ],
            'total' => 2.3829634288791293,
            'vatable' => 2.0367208793838714,
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
