<?php

class Test_Case_33 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 33,
        'aid' => 66,
        'sid' => 67,
        'function' => [
            'takeLastRevision',
        ],
        'options' => [
            'stamp' => '201810',
            'force_accounts' => [
                66,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'firstname' => 'yossiB',
        ],
    ],
];
    }
}
