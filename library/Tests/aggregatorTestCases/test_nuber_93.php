<?php

class Test_Case_93 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles + discount unlimit from 02-02-2021, discount for 3 cyces  from 15-12-2020 to 15-03-2021, the service from is equal to revision from , 4th cycle ',
        'test_number' => 93,
        'aid' => 991663,
        'sid' => 991664,
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
                991663,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 991663,
            'after_vat' => [
                991663 => 34.83892533021287,
            ],
            'total' => 34.83892533021287,
            'vatable' => 29.776859256592193,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => 0.0,
];
    }
}
