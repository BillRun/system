<?php

class Test_Case_185_5 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => '185-5',
        'aid' => 352615,
        'sid' => 352616,
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
                352615,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202204',
            'aid' => 352615,
            'after_vat' => [
                352616 => 347.2258064516129,
            ],
            'total' => 347.2258064516129,
            'vatable' => 296.77419354838713,
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
                    'sid' => 352616,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                    'aprice' => -96.774193548387,
                ],
                'aprice' => -96.77419354838709,
            ],
            [
                'query' => [
                    'sid' => 352616,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT2',
                    'aprice' => 193.5483870967742,
                ],
                'aprice' => 193.5483870967742,
            ],
            [
                'query' => [
                    'sid' => 352616,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT2',
                    'aprice' => 200,
                ],
                'aprice' => 200,
            ],
        ],
    ],
];
    }
}
