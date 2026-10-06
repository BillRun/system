<?php

/**
 * Unit tests for Billrun_CollectionSteps_Db::runCollectionStateChange() - the
 * "collection state change" request sent to the CRM (httpnoack step). By default
 * the request lists the aids: {"state", "aids": [...], "process_name"}.
 * - change_state_include_debt (per process settings, or globally under
 *   collection.settings): the in_collection request carries each account with the
 *   debt calculated by the collect run instead (BRCD-5519):
 *   {"state": "in_collection", "accounts": [{"aid": ..., "debt": ...}], "process_name"}
 * - change_state_include_credit (same places): the out_of_collection request
 *   carries each account with its balance, loaded from its bills - "credit" (0 when
 *   settled), or "debt" when the account still owes below the minimum debt:
 *   {"state": "out_of_collection", "accounts": [{"aid": ..., "credit": ...}, {"aid": ..., "debt": ...}], "process_name"}
 * Each setting affects its own state only.
 * The steps are captured before they are run, so no request leaves the test.
 */
class StateChangeRequestTest extends \Codeception\Test\Unit {

	const DEBT_SETTING = 'change_state_include_debt';
	const CREDIT_SETTING = 'change_state_include_credit';
	const GLOBAL_SETTINGS_PREFIX = 'collection.settings.';
	const PROCESS_NAME = 'unit_process';
	const CHANGE_STATE_URL = 'http://crm.unit.test/collection/state';

	/**
	 * @var \UnitTester
	 */
	protected $tester;

	protected function _before() {
		\Helper\All::cleanDB();
		$this->seedBills();
	}

	protected function _after() {
		$this->setGlobalSettings(array());
	}

	/**
	 * sets the global (collection.settings) debt / credit settings - the ones not passed are turned off
	 *
	 * @param array $settings setting name => value
	 */
	protected function setGlobalSettings($settings) {
		foreach (array(self::DEBT_SETTING, self::CREDIT_SETTING) as $setting) {
			Billrun_Factory::config()->setConfigValue(self::GLOBAL_SETTINGS_PREFIX . $setting, $settings[$setting] ?? false);
		}
	}

	/**
	 * the bills behind the balance of the accounts leaving collection: 1001 still owes 5 (below the default minimum debt of 10),
	 * 1002 is in credit (an unallocated payment of 30), 1003 has no open bill
	 */
	protected function seedBills() {
		$dueDate = new Mongodloid_Date(strtotime('-10 days'));
		$bills = array(
			array(
				'type' => 'inv',
				'aid' => 1001,
				'invoice_id' => 50001,
				'amount' => 5.0,
				'due' => 5.0,
				'due_before_vat' => 4.27,
				'left_to_pay' => 5.0,
				'paid' => '0',
				'due_date' => $dueDate,
				'urt' => $dueDate,
				// counts as debt whether or not the account requires a rejection (collection.settings.rejection_required)
				'past_rejections' => array(array('txid' => 'unit_rejected_payment')),
			),
			array(
				'type' => 'rec',
				'method' => 'cash',
				'dir' => 'fc',
				'aid' => 1002,
				'amount' => 30.0,
				'due' => -30.0,
				'left' => 30.0,
				'due_date' => $dueDate,
				'urt' => $dueDate,
			),
		);
		Billrun_Factory::db()->billsCollection()->batchInsert($bills);
	}

	/**
	 * the scenarios, label => row. Each row is positional:
	 * [0] in - true: the accounts entered collection (in_collection request), false: they left it (out_of_collection request)
	 * [1] process settings - merged into the process' settings (change_state_include_debt / change_state_include_credit /
	 *     change_state_batch_size); an empty array = the process sets nothing
	 * [2] global settings - setting name => value, set under collection.settings; the ones not listed are turned off
	 * [3] aids - the accounts whose state changed, passed to runCollectionStateChange
	 * [4] debts - aid => debt as calculated by the collect run, passed to runCollectionStateChange (only meaningful for in = true,
	 *     the out_of_collection request loads the balances from the seeded bills)
	 * [5] expected requests - the expected extra_params of each batch step, in order
	 */
	protected function getStateChangeRequestTests() {
		$debts = array(1001 => 10.5, 1002 => 20, 1003 => 300.25);
		$aids = array_keys($debts);
		$debtOn = array(self::DEBT_SETTING => true);
		$debtOff = array(self::DEBT_SETTING => false);
		$creditOn = array(self::CREDIT_SETTING => true);
		// the amount each account is expected with: entering collection - the debt passed in, leaving it - the balance of the seeded bills
		$inAmounts = array_map(function ($debt) {
			return array('debt' => $debt);
		}, $debts);
		$outAmounts = array(
			1001 => array('debt' => 5.0), // still owes 5
			1002 => array('credit' => 30.0), // in credit
			1003 => array('credit' => 0), // settled - no open bill
		);
		$aidsRequest = array('state' => 'in_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		$accountsRequest = $this->getAccountsRequest('in_collection', $inAmounts);
		$outRequest = array('state' => 'out_of_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		$outAccountsRequest = $this->getAccountsRequest('out_of_collection', $outAmounts);
		// row: [in, process settings, global settings, aids, debts, expected requests]
		return array(
			// default - the request is unchanged
			'no setting - aids' => array(true, array(), array(), $aids, $debts, array($aidsRequest)),
			'process debt setting off - aids' => array(true, $debtOff, array(), $aids, $debts, array($aidsRequest)),
			'process debt setting off overrides global on - aids' => array(true, $debtOff, $debtOn, $aids, $debts, array($aidsRequest)),
			// debt enabled - the accounts with their debt
			'process debt setting on - accounts with debt' => array(true, $debtOn, array(), $aids, $debts, array($accountsRequest)),
			'global debt setting on - accounts with debt' => array(true, array(), $debtOn, $aids, $debts, array($accountsRequest)),
			// batches keep each account with its own debt
			'batches of 2 - accounts with debt' => array(true, $debtOn + array('change_state_batch_size' => 2), array(), $aids, $debts, array(
				$this->getAccountsRequest('in_collection', array_slice($inAmounts, 0, 2, true)),
				$this->getAccountsRequest('in_collection', array_slice($inAmounts, 2, null, true)),
			)),
			// each setting affects its own state only
			'credit setting on - in_collection unchanged - aids' => array(true, $creditOn, $creditOn, $aids, $debts, array($aidsRequest)),
			'debt setting on - out_of_collection unchanged - aids' => array(false, $debtOn, $debtOn, $aids, array(), array($outRequest)),
			// credit enabled - the accounts with their balance, loaded from their bills (nothing is passed in)
			'out_of_collection, no setting - aids' => array(false, array(), array(), $aids, array(), array($outRequest)),
			'out_of_collection, process credit setting on - accounts with balance' => array(false, $creditOn, array(), $aids, array(), array($outAccountsRequest)),
			'out_of_collection, global credit setting on - accounts with balance' => array(false, array(), $creditOn, $aids, array(), array($outAccountsRequest)),
			'out_of_collection, process credit setting off overrides global on - aids' => array(false, array(self::CREDIT_SETTING => false), $creditOn, $aids, array(), array($outRequest)),
			// each batch loads the balance of its own accounts
			'out_of_collection, batches of 2 - accounts with balance' => array(false, $creditOn + array('change_state_batch_size' => 2), array(), $aids, array(), array(
				$this->getAccountsRequest('out_of_collection', array_slice($outAmounts, 0, 2, true)),
				$this->getAccountsRequest('out_of_collection', array_slice($outAmounts, 2, null, true)),
			)),
		);
	}

	/**
	 * the expected extra_params of a state change request batch that carries the accounts with their amount
	 *
	 * @param string $state in_collection / out_of_collection
	 * @param array $amountByAid aid => ['debt' => amount] or ['credit' => amount], in the order of the batch
	 *
	 * @return array ['state', 'accounts' => [['aid' => aid, 'debt'|'credit' => amount], ...], 'process_name']
	 */
	protected function getAccountsRequest($state, $amountByAid) {
		$accounts = array();
		foreach ($amountByAid as $aid => $amount) {
			$accounts[] = array('aid' => $aid) + $amount;
		}
		return array('state' => $state, 'accounts' => $accounts, 'process_name' => self::PROCESS_NAME);
	}

	public function testStateChangeRequest() {
		foreach ($this->getStateChangeRequestTests() as $label => $test) {
			list($in, $settings, $global, $aids, $debts, $expected) = $test;
			$this->setGlobalSettings($global);
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
