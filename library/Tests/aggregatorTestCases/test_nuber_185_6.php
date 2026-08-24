<?php

class Test_Case_185_6 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => '185-6',
        'aid' => 352617,
        'sid' => 352618,
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
                352617,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202204',
            'aid' => 352617,
            'after_vat' => [
                352618 => 320.80645161290323,
            ],
            'total' => 320.80645161290323,
            'vatable' => 274.19354838709677,
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
                    'sid' => 352618,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT1',
                    'aprice' => -67.74193548387098,
                ],
                'aprice' => -67.74193548387098,
            ],
            [
                'query' => [
                    'sid' => 352618,
                    'billrun' => '202204',
                    'plan' => 'UPFRONT2',
                    'aprice' => 141.93548387096774,
                ],
                'aprice' => 141.93548387096774,
            ],
            [
                'query' => [
                    'sid' => 352618,
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
