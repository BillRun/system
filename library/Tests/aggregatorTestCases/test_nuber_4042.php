<?php

class Test_Case_4042 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'change service quantity during the month',
        'test_number' => 4042,
        'aid' => 80798,
        'sid' => [
            80806,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202302',
            'force_accounts' => [
                80798,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202302',
            'aid' => 80798,
            'after_vat' => [
                80806 => 1013.355198131,
            ],
            'total' => 1013.355198131,
            'vatable' => 866.115553958,
            'vat' => 17,
        ],
    ],
];
    }
}
