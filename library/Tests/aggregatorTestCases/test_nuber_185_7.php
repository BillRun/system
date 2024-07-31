<?php

class Test_Case_185_7 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => '185-7',
        'aid' => 352619,
        'sid' => 352620,
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
                352619,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202204',
            'aid' => 352619,
            'after_vat' => [
                352620 => 237.77419354838707,
            ],
            'total' => 237.77419354838707,
            'vatable' => 203.2258064516129,
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
                    'sid' => 352620,
                    'billrun' => '202203',
                    'plan' => 'UPFRONT1',
                ],
                'aprice' => -3.225806451612903,
            ],
            [
                'query' => [
                    'sid' => 352620,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT2',
                ],
                'aprice' => 200,
            ],
            [
                'query' => [
                    'sid' => 352620,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT2',
                ],
                'aprice' => 6.4516129032258,
            ],
        ],
    ],
];
    }
}
