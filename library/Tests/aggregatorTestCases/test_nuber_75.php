<?php

class Test_Case_75 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test the Conditional charge is applied only to one subscriber under the account instead of two',
        'test_number' => 75,
        'aid' => 3082,
        'sid' => [
            3083,
            3084,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202106',
            'force_accounts' => [
                3082,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202106',
            'aid' => 3082,
            'after_vat' => [
                3083 => 175.5,
                3084 => 175.5,
            ],
            'total' => 351,
            'vatable' => 300,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'credit',
            ],
        ],
    ],
];
    }
}
