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
			["test_num" => 1,'file_type'=>'ICT',
				"expected" => [
					['arate_key' => 'RATE_MTT_ICTDC_FIX_TI_MTTNI04', 'aprice' =>0.041333333333416, 'final_charge' => 0.041333333333416, 'aid' => 1530, 'sid' => 1947, 'billrun' => '202104', "usaget" => "transit_incoming_call", "usagev_unit" => "seconds", "usagev" => 248,"cf.call_direction" => "TI"],
					['arate_key' => 'RATE_CYTA_ICTDC_FIX_TO_CTA_SING', 'aprice' => 0.10912000000000001, 'final_charge' => 0.10912000000000001,'aid' => 1530, 'sid' => 1947, 'billrun' => '202104', "usaget" => "transit_outgoing_call", "usagev_unit" => "seconds", "usagev" => 248,"cf.call_direction" => "TO"]
				]
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
