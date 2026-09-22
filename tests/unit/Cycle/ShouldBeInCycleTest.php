<?php

class ShouldBeInCycleTest extends \Codeception\Test\Unit {

	protected $tester;
	private $defaultTimezone;

	protected function _before() {
		Billrun_Factory::config();
		$this->defaultTimezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
	}

	protected function _after() {
		date_default_timezone_set($this->defaultTimezone);
	}

	public function testShouldBeInCycle() {
		$tests = json_decode(file_get_contents(__DIR__ . '/TestData/should_be_in_cycle_test_data.json'), true);

		foreach ($tests as $label => $test) {
			$cycle  = new Billrun_DataTypes_CycleTime($test['cycle']);
			$config = $test['config'];

			if (isset($config['start'])) {
				$config['start'] = strtotime($config['start']);
			}
			if (isset($config['end'])) {
				$config['end'] = strtotime($config['end']);
			}

			$result = Billrun_Utils_Cycle::shouldBeInCycle($config, $cycle);
			$this->assertEquals($test['expected'], $result, $label);
		}
	}
}
