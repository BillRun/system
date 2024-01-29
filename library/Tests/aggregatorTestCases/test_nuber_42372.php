<?php

class Test_Case_42372 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test for BRCD-4237 part 2,Should not be charged for the service (finished last month)',
        'test_number' => 42372,
        'aid' => 4237,
        'sid' => 42371,
        'function' => [
            'totalsPrice',
        ],
        'options' => [
            'stamp' => '202311',
            'force_accounts' => [
                4237,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202311',
            'aid' => 4237,
            'after_vat' => [
                4237 => 0,
            ],
            'total' => 0,
            'vatable' => 0,
            'vat' => 0,
        ],
        'lines' => [
        ],
    ],
    'duplicate' => false,
];
    }
}
