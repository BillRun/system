<?php

class Test_Case_185_1 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => '185-1',
        'aid' => 35267,
        'sid' => 35268,
        'function' => [
            'totalsPrice',
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
            'checkPerLine',
        ],
        'options' => [
            'stamp' => '202204',
            'force_accounts' => [
                35267,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202204',
            'aid' => 35267,
            'after_vat' => [
                35268 => 234,
            ],
            'total' => 234,
            'vatable' => 200,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
        'lines' => [
            [
                'query' => [
                    'sid' => 35268,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                ],
                'aprice' => 100,
            ],
            [
                'query' => [
                    'sid' => 35268,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                ],
                'aprice' => 100,
            ],
        ],
    ],
];
    }
}
