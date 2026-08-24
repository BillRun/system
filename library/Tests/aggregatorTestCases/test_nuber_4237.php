<?php

class Test_Case_4237 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test for BRCD-4237 part 1,need to pay for teh service',
        'test_number' => 4237,
        'aid' => 4237,
        'sid' => 42371,
        'function' => [
            'totalsPrice',
        ],
        'options' => [
            'stamp' => '202310',
            'force_accounts' => [
                4237,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202310',
            'aid' => 4237,
            'after_vat' => [
                4237 => 11.7,
            ],
            'total' => 11.7,
            'vatable' => 10,
            'vat' => 17,
        ],
        'lines' => [
        ],
    ],
    'duplicate' => false,
];
    }
}
