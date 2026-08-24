<?php

class Test_Case_185_3 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => '185-3',
        'aid' => 352611,
        'sid' => 352612,
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
                352611,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202204',
            'aid' => 352611,
            'after_vat' => [
                352612 => 120.77419354838709,
            ],
            'total' => 120.77419354838709,
            'vatable' => 103.2258064516129,
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
                    'sid' => 352612,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                    'aprice' => 100,
                ],
                'aprice' => 100,
            ],
            [
                'query' => [
                    'sid' => 352612,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                    'aprice' => 3.225806451612903,
                ],
                'aprice' => 3.225806451612903,
            ],
        ],
    ],
];
    }
}
