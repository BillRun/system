<?php

class Test_Case_42738 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service - full charge + period 2',
        'test_number' => 42738,
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
            'stamp' => '202411',
            'force_accounts' => [
                42738,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202411',
            'aid' => 42738,
            'after_vat' => [
                42739 => 234,
            ],
            'total' => 234,
            'vatable' => 200,
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
