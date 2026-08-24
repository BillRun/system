<?php

class Test_Case_178 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 178,
        'aid' => 883,
        'sid' => 773,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                883,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 883,
            'after_vat' => [
                773 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
];
    }
}
