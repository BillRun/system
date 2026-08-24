<?php

class Test_Case_42745 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service - porated period 1  + porated period 2',
        'test_number' => 42745,
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
            'stamp' => '202410',
            'force_accounts' => [
                42738,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202410',
            'aid' => 42738,
            'after_vat' => [
                42739 => 218.4,
            ],
            'total' => 218.4,
            'vatable' => 186.6666666666667,
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
