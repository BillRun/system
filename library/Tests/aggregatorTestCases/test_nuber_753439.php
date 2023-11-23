<?php

class Test_Case_753439 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test the Conditional charge is applied only to one subscriber under the account instead of two',
        'test_number' => 753439,
        'aid' => 30823439,
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
                30823439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202106',
            'aid' => 30823439,
            'after_vat' => [
                30833439 => 175.5,
                30843439 => 175.5,
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
    'duplicate' => true,
];
    }
}
