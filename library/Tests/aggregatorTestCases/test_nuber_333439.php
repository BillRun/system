<?php

class Test_Case_333439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 333439,
        'aid' => 663439,
        'sid' => 673439,
        'function' => [
            'takeLastRevision',
        ],
        'options' => [
            'stamp' => '201810',
            'force_accounts' => [
                663439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'firstname' => 'yossiB',
        ],
    ],
    'duplicate' => true,
];
    }
}
