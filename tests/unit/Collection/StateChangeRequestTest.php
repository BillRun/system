<?php

/**
 * Unit tests for Billrun_CollectionSteps_Db::runCollectionStateChange() - the
 * "collection state change" request sent to the CRM (httpnoack step). By default
 * the request lists the aids: {"state", "aids": [...], "process_name"}. With
 * change_state_include_debt enabled (per process settings, or globally under
 * collection.settings) the in_collection request carries each account with the
 * debt calculated by the collect run instead:
 * {"state": "in_collection", "accounts": [{"aid": ..., "debt": ...}], "process_name"}
 * (BRCD-5519). The out_of_collection request never changes.
 * The steps are captured before they are run, so no request leaves the test.
 */
class StateChangeRequestTest extends \Codeception\Test\Unit {

	const GLOBAL_SETTING = 'collection.settings.change_state_include_debt';
	const PROCESS_NAME = 'unit_process';
	const CHANGE_STATE_URL = 'http://crm.unit.test/collection/state';

	/**
	 * @var \UnitTester
	 */
	protected $tester;

	protected function _after() {
		Billrun_Factory::config()->setConfigValue(self::GLOBAL_SETTING, false);
	}

	/**
	 * label => [in (true - entered collection, false - left collection), process settings,
	 *           global setting, aids, aid => debt of the collect run, expected extra_params per batch]
	 */
	protected function getStateChangeRequestTests() {
		$debts = array(1001 => 10.5, 1002 => 20, 1003 => 300.25);
		$aids = array_keys($debts);
		$aidsRequest = array('state' => 'in_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		$accountsRequest = array(
			'state' => 'in_collection',
			'accounts' => array(
				array('aid' => 1001, 'debt' => 10.5),
				array('aid' => 1002, 'debt' => 20),
				array('aid' => 1003, 'debt' => 300.25),
			),
			'process_name' => self::PROCESS_NAME,
		);
		$outRequest = array('state' => 'out_of_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		return array(
			// default - the request is unchanged
			'no setting - aids' => array(true, array(), false, $aids, $debts, array($aidsRequest)),
			'process setting off - aids' => array(true, array('change_state_include_debt' => false), false, $aids, $debts, array($aidsRequest)),
			'process setting off overrides global on - aids' => array(true, array('change_state_include_debt' => false), true, $aids, $debts, array($aidsRequest)),
			// enabled - the accounts with their debt
			'process setting on - accounts with debt' => array(true, array('change_state_include_debt' => true), false, $aids, $debts, array($accountsRequest)),
			'global setting on - accounts with debt' => array(true, array(), true, $aids, $debts, array($accountsRequest)),
			// batches keep each account with its own debt
			'batches of 2 - accounts with debt' => array(true, array('change_state_include_debt' => true, 'change_state_batch_size' => 2), false, $aids, $debts, array(
				array(
					'state' => 'in_collection',
					'accounts' => array(array('aid' => 1001, 'debt' => 10.5), array('aid' => 1002, 'debt' => 20)),
					'process_name' => self::PROCESS_NAME,
				),
				array(
					'state' => 'in_collection',
					'accounts' => array(array('aid' => 1003, 'debt' => 300.25)),
					'process_name' => self::PROCESS_NAME,
				),
			)),
			// out_of_collection is never changed
			'out_of_collection with the setting on - aids' => array(false, array('change_state_include_debt' => true), true, $aids, array(), array($outRequest)),
		);
	}

	public function testStateChangeRequest() {
		foreach ($this->getStateChangeRequestTests() as $label => $test) {
			list($in, $settings, $global, $aids, $debts, $expected) = $test;
			Billrun_Factory::config()->setConfigValue(self::GLOBAL_SETTING, $global);
			$process = array(
				'name' => self::PROCESS_NAME,
				'label' => 'Unit process',
				'settings' => array_merge(array('change_state_url' => self::CHANGE_STATE_URL, 'change_state_method' => 'POST'), $settings),
			);
			$collectionSteps = $this->getCapturingCollectionSteps();

			$result = $collectionSteps->runCollectionStateChange($aids, $in, $process, $debts);

			$this->assertTrue($result, $label);
			$this->assertCount(count($expected), $collectionSteps->steps, $label . ': batches');
			foreach ($expected as $index => $extraParams) {
				$step = $collectionSteps->steps[$index];
				$batchLabel = $label . ': batch ' . ($index + 1);
				$this->assertSame('collection state change', $step['step_code'], $batchLabel);
				$this->assertSame('httpnoack', $step['step_type'], $batchLabel);
				$this->assertSame(array('url' => self::CHANGE_STATE_URL, 'method' => 'POST'), $step['step_config'], $batchLabel);
				$this->assertSame($extraParams, $step['extra_params'], $batchLabel);
				$this->assertNotEmpty($step['creation_time'], $batchLabel);
			}
		}
	}

	/**
	 * collection steps that capture the state change steps instead of running them (no request is sent)
	 */
	protected function getCapturingCollectionSteps() {
		return new class extends Billrun_CollectionSteps_Db {

			public $steps = array();

			protected function runStep($step) {
				$this->steps[] = $step;
				return true;
			}

		};
	}

}
