<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for tariff logic
 *
 * @package         Tests
 * @subpackage      
 * @since           5.2
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

class Tests_Tariff extends UnitTestCase {
	protected $getAccessPriceTests = array(
		array("msg" => "Positive access price", "expected" => 10, "tariff" => array("access" => 10)),
		array("msg" => "Negative access price", "expected" => -10, "tariff" => array("access" => -10)),
		array("msg" => "Zero access price", "expected" => 0, "tariff" => array("access" => 0)),
		
		// Validation checks
		array("msg" => "Empty array", "expected" => 0, "tariff" => array()),
		array("msg" => "Null tariff array", "expected" => 0, "tariff" => null),
		array("msg" => "String tariff array", "expected" => 0, "tariff" => "Hello World"),
		array("msg" => "Integer tariff array", "expected" => 0, "tariff" => 979),
		
		// Edge cases
		array("msg" => "Access capital letters", "expected" => 0, "tariff" => array("ACCESS" => 10)),
		array("msg" => "Access mixed letters", "expected" => 0, "tariff" => array("Access" => 10)),
		
	);
	
	protected $getChargesByVolumeTests = array(
			array("msg" => "1.Valid charge", 'volume' => 1,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1,'price' => 1, 'interval' => 1))), 'expected' => 1),
			array("msg" => "2.Valid charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 1, 'interval' => 1))), 'expected' => 2),
		
			array("msg" => "3.Valid charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>10, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => 10),
			array("msg" => "4.Valid charge with access price", 'volume' => 10,'tariffs' => array('access' => 1000, 'rate'=>array(array('from' =>0 , 'to' =>10, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => 1010),
			array("msg" => "5.Valid charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>9, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => 109),
		
			array("msg" => "1.No ciel valid charge", 'volume' => 1,'tariffs' => array('rate'=>array(array('ciel'=>false, 'from' =>0 , 'to' =>1,'price' => 1, 'interval' => 1))), 'expected' => 1),
			array("msg" => "2.No ciel valid charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('ciel'=>false, 'from' =>0 , 'to' =>1, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 1, 'interval' => 1))), 'expected' => 2),
		
			array("msg" => "3.No ciel valid charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>10, 'price' => 1, 'interval' => 1), array('ciel'=>false, 'from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => 10),
			array("msg" => "4.No ciel valid charge with access price", 'volume' => 10,'tariffs' => array('access' => 1000, 'rate'=>array(array('ciel'=>false, 'from' =>0 , 'to' =>10, 'price' => 1, 'interval' => 1), array('ciel'=>false, 'from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => 1010),
			array("msg" => "5.No ciel valid charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('ciel'=>false, 'from' =>0 , 'to' =>9, 'price' => 1, 'interval' => 1), array('ciel'=>false, 'from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => 109),
		
			array("msg" => "Negative volume", 'volume' => -10,'tariffs' => array('rate'=> array(array('from' =>0 , 'to' =>9, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => -109),
			array("msg" => "Zero volume", 'volume' => 0,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>9, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 100, 'interval' => 1))), 'expected' => 0),
		
			array("msg" => "Three tariffs", 'volume' => 2, 'expected' => 2, 'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1, 'price' => 1, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 1, 'interval' => 1)))),
		
			array("msg" => "1.Valid float price charge", 'volume' => 1,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1,'price' => 0.3, 'interval' => 1))), 'expected' => 0.3),
			array("msg" => "2.Valid float price charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1,'price' => 0.3, 'interval' => 1))), 'expected' => 0.3),
			array("msg" => "3.Valid float price charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2,'price' => 0.3, 'interval' => 1))), 'expected' => 0.6),
			array("msg" => "4.Valid float price charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1, 'price' => 0.3, 'interval' => 1), array('from' =>0 , 'to' =>1, 'price' => 0.4, 'interval' => 1))), 'expected' => 0.7),
			array("msg" => "5.Valid float price charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1, 'price' => 0.5, 'interval' => 1), array('from' =>1 , 'to' =>4, 'price' => 0.5, 'interval' => 1))), 'expected' => 2),
			array("msg" => "6.Valid float price charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1, 'price' => 0.5, 'interval' => 1), array('from' =>1 , 'to' =>4, 'price' => 0.5, 'interval' => 1), array('from' =>4, 'to' =>9, 'price' => 1.2, 'interval' => 1))), 'expected' => 8),
		
			array("msg" => "1.Valid float interval charge", 'volume' => 5,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.5,'price' => 1, 'interval' => 0.5))), 'expected' => 1),
			array("msg" => "2.Valid float interval charge", 'volume' => 5,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8,'price' => 1, 'interval' => 0.5))), 'expected' => 2),
			array("msg" => "3.Valid float interval charge", 'volume' => 5,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2,'price' => 1, 'interval' => 0.5))), 'expected' => 4),
			array("msg" => "4.Valid float interval charge", 'volume' => 5,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.5, 'price' => 1, 'interval' => 0.8), array('from' =>0 , 'to' =>1, 'price' => 2, 'interval' => 4.2))), 'expected' => 3),	
			array("msg" => "5.Valid float interval charge", 'volume' => 5,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2.4, 'price' => 1, 'interval' => 0.8), array('from' =>0 , 'to' =>5, 'price' => 2, 'interval' => 4.2))), 'expected' => 5),
			array("msg" => "6.Valid float interval charge", 'volume' => 5,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>5, 'price' => 2, 'interval' => 4.2), array('from' =>0 , 'to' =>2.4, 'price' => 1, 'interval' => 0.8))), 'expected' => 4),
			array("msg" => "7.Valid float interval charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2, 'price' => 1, 'interval' => 2.5), array('from' =>0 , 'to' =>1, 'price' => 2, 'interval' => 4))), 'expected' => 1),
			array("msg" => "8.Valid float interval charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2, 'price' => 1, 'interval' => 0.5))), 'expected' => 4),
			array("msg" => "9.Valid float interval charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2, 'price' => 1, 'interval' => 0.5), array('from' =>2 , 'to' =>6, 'price' => 1, 'interval' => 3.5))), 'expected' => 6),
			array("msg" => "10.Valid float interval charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2, 'price' => 1, 'interval' => 0.5), array('from' =>2 , 'to' =>6, 'price' => 1, 'interval' => 3.5), array('from' =>6 , 'to' =>8, 'price' => 2, 'interval' => 0.1))), 'expected' => 46),
		
			array("msg" => "1.Valid float interval and price charge", 'volume' => 4,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>4,'price' => 0.5, 'interval' => 2.5))), 'expected' => 1),
			array("msg" => "2.Valid float interval and price charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1,'price' => 0.3, 'interval' => 0.5))), 'expected' => 0.6),
			array("msg" => "3.Valid float interval and price charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2,'price' => 1.5, 'interval' => 0.5))), 'expected' => 6),
			array("msg" => "4.Valid float interval and price charge", 'volume' => 6,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>5, 'price' => 0.5, 'interval' => 2.5))), 'expected' => 1),
			array("msg" => "5.Valid float interval and price charge", 'volume' => 6,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>5, 'price' => 0.5, 'interval' => 2.5), array('from' =>0 , 'to' =>6, 'price' => 0.1, 'interval' => 0.1))), 'expected' => 2),
			array("msg" => "6.Valid float interval and price charge", 'volume' => 6,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>4, 'price' => 0.1, 'interval' => 0.1), array('from' =>0 , 'to' =>6, 'price' => 0.8, 'interval' => 1.1))), 'expected' => 5.6),
			array("msg" => "7.Valid float interval and price charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2, 'price' => 1.5, 'interval' => 0.5))), 'expected' => 6),
			array("msg" => "8.Valid float interval and price charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2, 'price' => 1.5, 'interval' => 0.5), array('from' =>2 , 'to' =>6, 'price' => 0.3, 'interval' => 3.5))), 'expected' => 6.6),
			array("msg" => "9.Valid float interval and price charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>2, 'price' => 2.5, 'interval' => 0.5), array('from' =>2 , 'to' =>8, 'price' => 1.25, 'interval' => 1.5), array('from' =>8, 'to' =>10, 'price' => 0.1, 'interval' => 0.1))), 'expected' => 17),

			array("msg" => "1.Valid float from and to charge", 'volume' => 1,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1.5,'price' => 0.3, 'interval' => 1))), 'expected' => 0.3),
			array("msg" => "2.Valid float from and to charge", 'volume' => 4,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>1.5,'price' => 0.3, 'interval' => 1))), 'expected' => 0.6),
			array("msg" => "3.Valid float from and to charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>1.1 , 'to' =>2,'price' => 0.3, 'interval' => 1))), 'expected' => 0.3),
			array("msg" => "4.Valid float from and to charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8, 'price' => 0.3, 'interval' => 1))), 'expected' => 0.3),
			array("msg" => "5.Valid float from and to charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0.7 , 'to' =>2, 'price' => 1, 'interval' => 0.1))), 'expected' => 13),
			array("msg" => "6.Valid float from and to charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8, 'price' => 0.3, 'interval' => 1), array('from' =>0.7 , 'to' =>2, 'price' => 1, 'interval' => 0.1))), 'expected' => 12.3),
			array("msg" => "7.Valid float from and to charge", 'volume' => 3,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8, 'price' => 0.3, 'interval' => 1), array('from' =>0.8 , 'to' =>2.1, 'price' => 1, 'interval' => 0.1))), 'expected' => 13.3),
			array("msg" => "8.Valid float from and to charge", 'volume' => 2,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8, 'price' => 0.3, 'interval' => 1), array('from' =>0.8 , 'to' =>2.1, 'price' => 1, 'interval' => 0.1))), 'expected' => 12.3),
			array("msg" => "9.Valid float from and to charge", 'volume' => 3,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8, 'price' => 0.3, 'interval' => 1), array('from' =>0.9 , 'to' =>2.1, 'price' => 1, 'interval' => 0.1))), 'expected' => 13.3),
			array("msg" => "10.Valid float from and to charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8, 'price' => 0.5, 'interval' => 0.2), array('from' =>1 , 'to' =>3.5, 'price' => 0.2, 'interval' => 0.5))), 'expected' => 3),
			array("msg" => "11.Valid float from and to charge", 'volume' => 10,'tariffs' => array('rate'=>array(array('from' =>0 , 'to' =>0.8, 'price' => 0.5, 'interval' => 0.2), array('from' =>1 , 'to' =>3.5, 'price' => 0.2, 'interval' => 0.5), array('from' =>4.1, 'to' =>7.6, 'price' => 1, 'interval' => 0.1))), 'expected' => 38),
		
	);
	
	protected $getChargeValueByTariffRatesAndVolumeTests = array(
			array("msg" => "1.Valid charge", 'volume' => 1,'rate' => array('from' => 0, 'to' => 1, 'price' => 1, 'interval' => 1), 'expected' => 1),
			array("msg" => "2.Valid charge", 'volume' => 2,'rate' => array('from' => 0, 'to' => 1, 'price' => 1, 'interval' => 1), 'expected' => 1),
			array("msg" => "3.Valid charge", 'volume' => 2,'rate' => array('from' => 0, 'to' => 2, 'price' => 1, 'interval' => 1), 'expected' => 2),
			array("msg" => "4.Valid charge", 'volume' => 10,'rate' => array('from' => 5, 'to' => 10, 'price' => 1, 'interval' => 1), 'expected' => 5),
			array("msg" => "5 .Valid charge", 'volume' => 10,'rate' => array('from' => 0, 'to' => 10, 'price' => 1, 'interval' => 1), 'expected' => 10),
		
			array("msg" => "1.Volume lower than interval", 'volume' => 1,'rate' => array('from' => 0, 'to' => 10, 'price' => 1, 'interval' => 10), 'expected' => 1),
			array("msg" => "2.Volume (float) lower than interval", 'volume' => 9.9,'rate' => array('from' => 0, 'to' => 10, 'price' => 1, 'interval' => 10), 'expected' => 1),
			array("msg" => "3.Volume (negative) lower than interval", 'volume' => -10,'rate' => array('from' => 0, 'to' => 10,	'price' => 1, 'interval' => 1), 'expected' => -10),
		
			array("msg" => "1.Valid charge no ceil", 'volume' => 1,'rate' => array('from' => 0, 'to' => 10, 'ceil' => false, 'price' => 1, 'interval' => 1), 'expected' => 1),
			array("msg" => "2.Valid charge no ceil", 'volume' => 2,'rate' => array('from' => 0, 'to' => 10, 'ceil' => false, 'price' => 1, 'interval' => 1), 'expected' => 2),
			array("msg" => "3.Valid charge no ceil", 'volume' => 10,'rate' => array('from' => 0, 'to' => 10, 'ceil' => false, 'price' => 1, 'interval' => 1), 'expected' => 10),
		
			array("msg" => "1.Volume lower than interval no ceil", 'volume' => 1,'rate' => array('from' => 0, 'to' => 10, 'ceil' => false, 'price' => 1, 'interval' => 10), 'expected' => 0.1),
			array("msg" => "2.Volume (float) lower than interval no ceil", 'volume' => 9.9,'rate' => array('from' => 0, 'to' => 10, 'ceil' => false,'price' => 1, 'interval' => 10), 'expected' => 0.99),
			array("msg" => "3.Volume (negative) lower than interval no ceil", 'volume' => -10,'rate' => array('from' => 0, 'to' => 10, 'ceil' => false, 'price' => 1, 'interval' => 1), 'expected' => -10),
	);
		
	/**
	 * Test the get access price function
	 */
	function testGetAccessPrice() {
		foreach ($this->getAccessPriceTests as $test) {
			$tariff = $test['tariff'];
			$result = Billrun_Tariff_Util::getAccessPrice($tariff);
			$expected = $test['expected'];
			$this->assertEqual($result, $expected, $test['msg']);
		}
	}
	
	/**
	 * Test the charge by volume function with a negative volume value.
	 * In this test we compare the negative expected value to the result.
	 */
	public function testGetChargeByNegativeVolume() {
		$passed = 0;
		foreach ($this->getChargesByVolumeTests as $test) {
			$tariffs = $test['tariffs'];
			$volume = -1 * $test['volume'];
			$result = Billrun_Tariff_Util::getChargeByVolume($tariffs, $volume);
			$expected = -1 * $test['expected'];
			if($this->assertEqual($result, $expected, $test['msg'] . " Expected: " . $expected . " Result: " . $result)) {
				$passed++;
			}
		}
		print_r("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->getChargesByVolumeTests) . " cases.<br>");
	}
	
	public function testGetChargeValueByTariffRatesAndVolume() {
		$passed = 0;
		foreach ($this->getChargeValueByTariffRatesAndVolumeTests as $test) {
			$rate = array($test['rate']);
			$volume = $test['volume'];
			$result = Billrun_Tariff_Util::getChargeByTariffRatesAndVolume($rate, $volume);
			if($this->assertEqual($result, $test['expected'], $test['msg'] . " Expected: " . $test['expected'] . " Result: " . $result)) {
				$passed++;
			}
		}
		
		print_r("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->getChargeValueByTariffRatesAndVolumeTests) . " cases.<br>");
	}
	
		/**
	 * Test the charge by volume function
	 */
	public function testGetChargeByVolume() {
		$passed = 0;
		foreach ($this->getChargesByVolumeTests as $test) {
			$tariffs = $test['tariffs'];
			$volume = $test['volume'];
			$result = Billrun_Tariff_Util::getChargeByVolume($tariffs, $volume);
			if($this->assertEqual($result, $test['expected'], $test['msg'] . " Expected: " . $test['expected'] . " Result: " . $result)) {
				$passed++;
			}
		}
		print_r("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->getChargesByVolumeTests) . " cases.<br>");
	}
}
