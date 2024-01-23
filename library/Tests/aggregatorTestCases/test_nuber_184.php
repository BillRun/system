<?php

class Test_Case_184 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 184,
        'aid' => 889,
        'sid' => 779,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                889,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 889,
            'after_vat' => [
                779 => 5.85,
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
