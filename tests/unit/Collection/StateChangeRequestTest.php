<?php

/**
 * Unit tests for Billrun_CollectionSteps_Db::runCollectionStateChange() - the
 * "collection state change" request sent to the CRM (httpnoack step). By default
 * the request lists the aids: {"state", "aids": [...], "process_name"}. With
 * change_state_include_debt enabled (per process settings, or globally under
 * collection.settings) the requests carry each account with its debt instead:
 * {"state": ..., "accounts": [{"aid": ..., "debt": ...}], "process_name"}
 * - in_collection: the debt calculated by the collect run (BRCD-5519)
 * - out_of_collection: the balance calculated by the collect run - the debt left
 *   below the minimum debt, 0 when settled, negative when the account is in credit
 * Both come in the aid => debt map passed by the caller (Billrun_Account::updateCrmInCollection).
 * The steps are captured before they are run, so no request leaves the test.
 */
class StateChangeRequestTest extends \Codeception\Test\Unit {

	const SETTING = 'change_state_include_debt';
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
	 * the scenarios, label => row. Each row is positional:
	 * [0] in - true: the accounts entered collection (in_collection request), false: they left it (out_of_collection request)
	 * [1] process settings - merged into the process' settings (change_state_include_debt / change_state_batch_size);
	 *     an empty array = the process sets nothing
	 * [2] global setting - the value of collection.settings.change_state_include_debt
	 * [3] aids - the accounts whose state changed, passed to runCollectionStateChange
	 * [4] debts - aid => debt as calculated by the collect run, passed to runCollectionStateChange: the debt of the accounts
	 *     entering collection, the balance of the accounts leaving it
	 * [5] expected requests - the expected extra_params of each batch step, in order
	 */
	protected function getStateChangeRequestTests() {
		$debts = array(1001 => 10.5, 1002 => 20, 1003 => 300.25); // entering collection
		$balances = array(
			1001 => 5.0, // leaving collection, still owes 5
			1002 => -30.0, // in credit
			1003 => 0, // settled
		);
		$aids = array_keys($debts);
		$on = array(self::SETTING => true);
		$off = array(self::SETTING => false);
		$aidsRequest = array('state' => 'in_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		$accountsRequest = $this->getAccountsRequest('in_collection', $debts);
		$outRequest = array('state' => 'out_of_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		$outAccountsRequest = $this->getAccountsRequest('out_of_collection', $balances);
		// row: [in, process settings, global setting, aids, debts, expected requests]
		return array(
			// default - the requests are unchanged
			'no setting - aids' => array(true, array(), false, $aids, $debts, array($aidsRequest)),
			'process setting off - aids' => array(true, $off, false, $aids, $debts, array($aidsRequest)),
			'process setting off overrides global on - aids' => array(true, $off, true, $aids, $debts, array($aidsRequest)),
			'out_of_collection, no setting - aids' => array(false, array(), false, $aids, $balances, array($outRequest)),
			'out_of_collection, process setting off overrides global on - aids' => array(false, $off, true, $aids, $balances, array($outRequest)),
			// enabled - the accounts with their debt (in_collection) or balance (out_of_collection)
			'process setting on - accounts with debt' => array(true, $on, false, $aids, $debts, array($accountsRequest)),
			'global setting on - accounts with debt' => array(true, array(), true, $aids, $debts, array($accountsRequest)),
			'out_of_collection, process setting on - accounts with balance' => array(false, $on, false, $aids, $balances, array($outAccountsRequest)),
			'out_of_collection, global setting on - accounts with balance' => array(false, array(), true, $aids, $balances, array($outAccountsRequest)),
			// batches keep each account with its own amount
			'batches of 2 - accounts with debt' => array(true, $on + array('change_state_batch_size' => 2), false, $aids, $debts, array(
				$this->getAccountsRequest('in_collection', array_slice($debts, 0, 2, true)),
				$this->getAccountsRequest('in_collection', array_slice($debts, 2, null, true)),
			)),
			'out_of_collection, batches of 2 - accounts with balance' => array(false, $on + array('change_state_batch_size' => 2), false, $aids, $balances, array(
				$this->getAccountsRequest('out_of_collection', array_slice($balances, 0, 2, true)),
				$this->getAccountsRequest('out_of_collection', array_slice($balances, 2, null, true)),
			)),
		);
	}

	/**
	 * the expected extra_params of a state change request batch that carries the accounts with their debt
	 *
	 * @param string $state in_collection / out_of_collection
	 * @param array $debtByAid aid => debt, in the order of the batch
	 *
	 * @return array ['state', 'accounts' => [['aid' => aid, 'debt' => debt], ...], 'process_name']
	 */
	protected function getAccountsRequest($state, $debtByAid) {
		$accounts = array();
		foreach ($debtByAid as $aid => $debt) {
			$accounts[] = array('aid' => $aid, 'debt' => $debt);
		}
		return array('state' => $state, 'accounts' => $accounts, 'process_name' => self::PROCESS_NAME);
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
