<?php

class Test_Case_183 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 183,
        'aid' => 888,
        'sid' => 778,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                888,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 888,
            'after_vat' => [
                778 => 5.85,
            ],
            'total' => 5.85,
            'vatable' => 5,
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
