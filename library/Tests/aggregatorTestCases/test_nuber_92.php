<?php

class Test_Case_92 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles + discount unlimit from 02-02-2021,discount for 3 cyces  from 15-12-2020 to 15-03-2021, the service from is equal to revision from , 1st cycle  ',
        'test_number' => 92,
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
            'stamp' => '202101',
            'force_accounts' => [
                991663,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 991663,
            'after_vat' => [
                991663 => 29.024494563747787,
            ],
            'total' => 29.024494563747787,
            'vatable' => 24.807260310895547,
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
