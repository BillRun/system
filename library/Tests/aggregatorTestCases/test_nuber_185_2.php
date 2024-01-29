<?php

class Test_Case_185_2 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => '185-2',
        'aid' => 35269,
        'sid' => 352610,
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
                35269,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202204',
            'aid' => 35269,
            'after_vat' => [
                352610 => 200.03225806451613,
            ],
            'total' => 200.03225806451613,
            'vatable' => 170.96774193548387,
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
                    'sid' => 352610,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                    'aprice' => 100,
                ],
                'aprice' => 100,
            ],
            [
                'query' => [
                    'sid' => 352610,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                    'aprice' => 70.96774193548387,
                ],
                'aprice' => 70.96774193548387,
            ],
        ],
    ],
];
    }
}
