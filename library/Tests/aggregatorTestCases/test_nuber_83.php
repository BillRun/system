<?php

class Test_Case_83 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles , from 30-12-2020 to 30-03-2021, the service from is equal to revision from ',
        'test_number' => 83,
        'aid' => 991653,
        'sid' => 991654,
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
                991653,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 991653,
            'after_vat' => [
                991653 => 4.7659268577582585,
            ],
            'total' => 4.7659268577582585,
            'vatable' => 4.073441758767743,
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
