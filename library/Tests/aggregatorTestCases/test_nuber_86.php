<?php

class Test_Case_86 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles + discount unlimit,discount for 3 cyces  from 02-12-2020 to 02-03-2021, the service from is more future to revision from , 1st cycle  ',
        'test_number' => 86,
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
            'stamp' => '202101',
            'force_accounts' => [
                991657,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 991657,
            'after_vat' => [
                991657 => 2.3829634288791293,
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
