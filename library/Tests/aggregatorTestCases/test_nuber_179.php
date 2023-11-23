<?php

class Test_Case_179 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 179,
        'aid' => 884,
        'sid' => 774,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                884,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 884,
            'after_vat' => [
                774 => 60.387096774193544,
            ],
            'total' => 60.387096774193544,
            'vatable' => 51.61290322580645,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
];
    }
}
