<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Itc_test_cases
 *
 * @author yossi
 */
class Itc_test_cases {

	public function tests() {
		$request = new Yaf_Request_Http;
		$this->test_cases = $request->get('tests');
		$cases = [
			["test_num" => 1,
				"data" =>
				[
					"123456" => [
						"uf" =>
						[
							"sid" => 53,
							"date" => "2020-03-27 10:39:00",
							"usage" => "sms",
							"volume" => 100,
							"rate" => "SMS"
						],
						"stamp" => "123456",
						"type" => "abc",
					]
				],
				"expected" => [['arate_key' => 'SMS', 'aprice' => 100, 'final_charge' => 117, 'aid' => 1234, 'sid' => 53, 'tax_data.total_amount' => 17, 'billrun' => '202102', "usaget" => "sms", "usagev_unit" => "counter", "usagev" => 100]]
			],
			["test_num" => 2,
				"data" =>
				[
					"1234567" => [
						"uf" =>
						[
							"sid" => 53,
							"date" => "2020-03-27 10:38:00",
							"usage" => "sms",
							"volume" => 200,
							"rate" => "SMS"
						],
						"stamp" => "123456",
						"type" => "abc",
					]
				],
				"expected" => [['rate' => 'SMS', 'aprice' => 10, 'final_charge' => 100, 'aid' => 1234, 'sid' => 53, 'tax_data.total_amount' => 17, 'billrun' => '202101']]
			]
		];
		if ($this->test_cases) {
			$this->test_cases = explode(',', $this->test_cases);
			foreach ($cases as $case) {
				if (in_array($case['test_num'], $this->test_cases))
					$newarr[] = $case;
			}
			return $newarr;
		}
		return $cases;
	}

}
