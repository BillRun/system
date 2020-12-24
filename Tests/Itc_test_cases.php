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
							"date" => new MongoDate(1585305540),
							"usage" => "sms",
							"volume" => 100,
							"rate" => "SMS",
							"preprice" => 10,
						],
						"urt" => new MongoDate(1585305540),
						"eurt" => new MongoDate(1585305540),
						"timezone" => 0,
						"usaget" => "sms",
						"usagev_unit" => "counter",
						"usagev" => 100,
						"connection_type" => "postpaid",
						"stamp" => "123456",
						"type" => "Preprice_Dynamic",
						"source" => "Preprice_Dynamic",
						"file" => "record.csv",
						"log_stamp" => "1",
						//"process_time" => new MongoDate(1585305540),
						"row_number" => 1
					]
				],
				"expected" => [['arate_key' => 'SMS', 'aprice' => 100, 'final_charge' => 117, 'aid' => 1234, 'sid' => 53, 'tax_data.total_amount' => 17, 'billrun' => '202101']]
			],
			["test_num" => 2,
				"data" =>
				[
					"1234567" => [
						"uf" =>
						[
							"sid" => 53,
							"date" => new MongoDate(1585305540),
							"usage" => "sms",
							"volume" => 100,
							"rate" => "SMS",
							"preprice" => 10,
						],
						"urt" => new MongoDate(1585305540),
						"eurt" => new MongoDate(1585305540),
						"timezone" => 0,
						"usaget" => "sms",
						"usagev_unit" => "counter",
						"usagev" => 100,
						"connection_type" => "postpaid",
						"stamp" => "1234567",
						"type" => "Preprice_Dynamic",
						"source" => "Preprice_Dynamic",
						"file" => "record.csv",
						"log_stamp" => "1",
						//"process_time" => new MongoDate(1585305540),
						"row_number" => 1
					]
				],
				"expected" => [['rate' => 'SMS', 'aprice' => 10, 'final_charge' => 100, 'aid' => 1234, 'sid' => 53, 'tax_data.total_amount' => 17, 'billrun' => '202101']]
			]
		];
		if ($this->test_cases) {
			$this->test_cases = explode(',', $this->test_cases);
			foreach ($cases as $case) {
				if (in_array($case['test_num'],$this->test_cases))
					$newarr[] = $case;
			}
			return $newarr;
		}
		return $cases;
	}

}
