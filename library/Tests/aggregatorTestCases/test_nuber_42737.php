<?php

class Test_Case_42737 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service - full charge + period 1',
        'test_number' => 42737,
        'aid' => 42738,
        'sid' => 42739,
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202401',
            'force_accounts' => [
                42738,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202401',
            'aid' => 42738,
            'after_vat' => [
                42739 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
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
