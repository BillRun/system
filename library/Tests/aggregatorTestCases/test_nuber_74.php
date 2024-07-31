<?php

class Test_Case_74 {
    public function test_case() {
        return [
    'preRun' => [
        'allowPremature',
        'removeBillruns',
    ],
    'test' => [
        'test_number' => 74,
        'aid' => 'abcd',
        'function' => [
            'MultiDay',
        ],
        'options' => [
            'stamp' => '202311',
            'invoicing_days' => [
                '26',
                '27',
            ],
        ],
    ],
    'expected' => [
        'billrun_key' => '202311',
        'accounts' => [
            10025 => '26',
            10026 => '27',
            100263439 =>'27',
            100253439 => '26'
        ],
    ],
    'postRun' => '',
];
    }
}
