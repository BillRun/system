<?php

class Test_Case_42739 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service - porated start  (26 days from 30 days) charge + period 1',
        'test_number' => 42739,
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
            'stamp' => '202312',
            'force_accounts' => [
                42738,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202312',
            'aid' => 42738,
            'after_vat' => [
                42739 => 101.4,
            ],
            'total' => 101.4,
            'vatable' => 86.66666666666667,
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
