<?php

/**
 * Unit tests for Billrun_CollectionSteps_Db::runCollectionStateChange() - the
 * "collection state change" request sent to the CRM (httpnoack step). By default
 * the request lists the aids: {"state", "aids": [...], "process_name"}. With
 * change_state_include_debt enabled (per process settings, or globally under
 * collection.settings) the requests carry each account with its debt instead:
 * {"state": ..., "accounts": [{"aid": ..., "debt": ...}], "process_name"}
 * - in_collection: the debt calculated by the collect run (BRCD-5519)
 * - out_of_collection: the balance loaded from the bills of the account - the debt
 *   left below the minimum debt, 0 when settled, negative when the account is in credit
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

	protected function _before() {
		\Helper\All::cleanDB();
		$this->seedBills();
	}

	protected function _after() {
		Billrun_Factory::config()->setConfigValue(self::GLOBAL_SETTING, false);
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
	 * [1] process settings - merged into the process' settings (change_state_include_debt / change_state_batch_size);
	 *     an empty array = the process sets nothing
	 * [2] global setting - the value of collection.settings.change_state_include_debt
	 * [3] aids - the accounts whose state changed, passed to runCollectionStateChange
	 * [4] debts - aid => debt as calculated by the collect run, passed to runCollectionStateChange (only meaningful for in = true,
	 *     the out_of_collection request loads the balances from the seeded bills)
	 * [5] expected requests - the expected extra_params of each batch step, in order
	 */
	protected function getStateChangeRequestTests() {
		$debts = array(1001 => 10.5, 1002 => 20, 1003 => 300.25);
		$aids = array_keys($debts);
		$on = array(self::SETTING => true);
		$off = array(self::SETTING => false);
		// the debt each account leaving collection is expected with - the balance of the seeded bills
		$outDebts = array(
			1001 => 5.0, // still owes 5
			1002 => -30.0, // in credit
			1003 => 0, // settled - no open bill
		);
		$aidsRequest = array('state' => 'in_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		$accountsRequest = $this->getAccountsRequest('in_collection', $debts);
		$outRequest = array('state' => 'out_of_collection', 'aids' => $aids, 'process_name' => self::PROCESS_NAME);
		$outAccountsRequest = $this->getAccountsRequest('out_of_collection', $outDebts);
		// row: [in, process settings, global setting, aids, debts, expected requests]
		return array(
			// default - the requests are unchanged
			'no setting - aids' => array(true, array(), false, $aids, $debts, array($aidsRequest)),
			'process setting off - aids' => array(true, $off, false, $aids, $debts, array($aidsRequest)),
			'process setting off overrides global on - aids' => array(true, $off, true, $aids, $debts, array($aidsRequest)),
			'out_of_collection, no setting - aids' => array(false, array(), false, $aids, array(), array($outRequest)),
			'out_of_collection, process setting off overrides global on - aids' => array(false, $off, true, $aids, array(), array($outRequest)),
			// enabled, in_collection - the accounts with the debt passed in
			'process setting on - accounts with debt' => array(true, $on, false, $aids, $debts, array($accountsRequest)),
			'global setting on - accounts with debt' => array(true, array(), true, $aids, $debts, array($accountsRequest)),
			// enabled, out_of_collection - the accounts with their balance, loaded from their bills (nothing is passed in)
			'out_of_collection, process setting on - accounts with balance' => array(false, $on, false, $aids, array(), array($outAccountsRequest)),
			'out_of_collection, global setting on - accounts with balance' => array(false, array(), true, $aids, array(), array($outAccountsRequest)),
			// batches keep each account with its own debt, each out_of_collection batch loads the balance of its own accounts
			'batches of 2 - accounts with debt' => array(true, $on + array('change_state_batch_size' => 2), false, $aids, $debts, array(
				$this->getAccountsRequest('in_collection', array_slice($debts, 0, 2, true)),
				$this->getAccountsRequest('in_collection', array_slice($debts, 2, null, true)),
			)),
			'out_of_collection, batches of 2 - accounts with balance' => array(false, $on + array('change_state_batch_size' => 2), false, $aids, array(), array(
				$this->getAccountsRequest('out_of_collection', array_slice($outDebts, 0, 2, true)),
				$this->getAccountsRequest('out_of_collection', array_slice($outDebts, 2, null, true)),
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
