<?php

class Test_Case_42736 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'DST',
        'test_number' => 42736,
        'aid' => 42736,
        'sid' => 42737,
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202311',
            'force_accounts' => [
                42736,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202311',
            'aid' => 42736,
            'after_vat' => [
                42737 => 0.9645161290339358,
            ],
            'total' => 0.9645161290339358,
            'vatable' => 0.8243727598580648,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'service',
            ],
        ],
    ],
];
    }
}
