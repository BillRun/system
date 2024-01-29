<?php

class Test_Case_77 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 01-12-2020 to 01-03-2021, the service from is future , 4th cycle  ',
        'test_number' => 77,
        'aid' => 991647,
        'sid' => 991648,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202104',
            'force_accounts' => [
                991647,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 991647,
            'after_vat' => [
                991648 => 73.871866295253,
            ],
            'total' => 73.871866295253,
            'vatable' => 63.1383472609,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => '',
];
    }
}
