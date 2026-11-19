<?php

require_once APPLICATION_PATH . '/application/plugins/israelInvoice.php';

/**
 * Unit tests for israelInvoicePlugin::beforeInvoiceConfirmed() - an invoice that needs an approval number
 * (passed invoiceNeedsApproval) must not be confirmed when it fails validateConditionsBeforeApproval
 * (e.g. the account vat number is empty), instead of being confirmed without an approval number.
 * The access token request is mocked, so no request leaves the test.
 */
class IsraelInvoiceApprovalTest extends \Codeception\Test\Unit {

	const VAT_FIELD = 'vat_number';

	/**
	 * @var \UnitTester
	 */
	protected $tester;

	protected function getPlugin($options = array()) {
		$plugin = $this->getMockBuilder('israelInvoicePlugin')
			->setConstructorArgs(array(array_merge(array('account_vat_number_field' => self::VAT_FIELD), $options)))
			->onlyMethods(array('checkConfigurationValidation', 'getAccessToken'))
			->getMock();
		return $plugin;
	}

	protected function getInvoiceData($before_vat, $vat_number, $taxes = array('vat' => 1)) {
		return array(
			'invoice_id' => 101,
			'aid' => 1,
			'invoice_date' => (object) array('sec' => strtotime('2026-07-01')),
			'totals' => array('before_vat' => $before_vat, 'taxes' => $taxes),
			'attributes' => array(self::VAT_FIELD => $vat_number),
		);
	}

	/**
	 * Runs beforeInvoiceConfirmed with a failing access token request, so an invoice that reaches the
	 * approval request is never confirmed - the expectation on getAccessToken tells if it was reached
	 * @return boolean should_be_confirmed
	 */
	protected function runBeforeInvoiceConfirmed($plugin, $invoice_data, $approval_requested) {
		$plugin->expects($approval_requested ? $this->once() : $this->never())
			->method('getAccessToken')
			->willReturn(array('error', false, ''));
		$invoice_bill = array('invoice_id' => $invoice_data['invoice_id']);
		$should_be_confirmed = true;
		$plugin->beforeInvoiceConfirmed($invoice_bill, $invoice_data, $should_be_confirmed);
		return $should_be_confirmed;
	}

	public function testInvoiceNeedsApprovalIgnoresVatNumber() {
		$plugin = $this->getPlugin();
		$this->assertTrue($plugin->invoiceNeedsApproval($this->getInvoiceData(6000, null)));
		$this->assertFalse($plugin->invoiceNeedsApproval($this->getInvoiceData(4000, '123456789')));
		$this->assertFalse($plugin->invoiceNeedsApproval($this->getInvoiceData(6000, '123456789', array('vat' => 0))));
	}

	public function testValidateConditionsBeforeApproval() {
		$plugin = $this->getPlugin();
		$plugin->validateConditionsBeforeApproval($this->getInvoiceData(6000, '123456789'));
		$plugin->validateConditionsBeforeApproval($this->getInvoiceData(6000, ' 123456789 '));
		foreach (array(null, '', 'abc', '12-34', array('123')) as $vat_number) {
			try {
				$plugin->validateConditionsBeforeApproval($this->getInvoiceData(6000, $vat_number));
				$this->fail('Expected exception for vat number ' . json_encode($vat_number));
			} catch (PHPUnit\Framework\AssertionFailedError $e) {
				throw $e;
			} catch (Exception $e) {
				$this->assertStringContainsString("'vat_number_field' check", $e->getMessage());
			}
		}
	}

	public function testValidateConditionsBeforeApprovalWithoutVatNumberField() {
		$plugin = $this->getPlugin(array('approve_accounts_with_vat_number_field' => false));
		$plugin->validateConditionsBeforeApproval($this->getInvoiceData(6000, null));
		$this->assertTrue(true);
	}

	// the bug - invoice above the threshold with no account vat number was confirmed without an approval number
	public function testAboveThresholdWithoutVatNumberIsNotConfirmed() {
		foreach (array(null, '', 'abc') as $vat_number) {
			$should_be_confirmed = $this->runBeforeInvoiceConfirmed($this->getPlugin(), $this->getInvoiceData(6000, $vat_number), false);
			$this->assertFalse($should_be_confirmed, 'vat number ' . json_encode($vat_number));
		}
	}

	public function testAboveThresholdWithVatNumberRequestsApproval() {
		$should_be_confirmed = $this->runBeforeInvoiceConfirmed($this->getPlugin(), $this->getInvoiceData(6000, '123456789'), true);
		$this->assertFalse($should_be_confirmed);
	}

	public function testAboveThresholdWithoutVatNumberFieldRequestsApproval() {
		$plugin = $this->getPlugin(array('approve_accounts_with_vat_number_field' => false));
		$should_be_confirmed = $this->runBeforeInvoiceConfirmed($plugin, $this->getInvoiceData(6000, null), true);
		$this->assertFalse($should_be_confirmed);
	}

	public function testUnderThresholdWithoutVatNumberIsConfirmed() {
		$should_be_confirmed = $this->runBeforeInvoiceConfirmed($this->getPlugin(), $this->getInvoiceData(4000, null), false);
		$this->assertTrue($should_be_confirmed);
	}

	public function testNonTaxableWithoutVatNumberIsConfirmed() {
		$should_be_confirmed = $this->runBeforeInvoiceConfirmed($this->getPlugin(), $this->getInvoiceData(6000, null, array('vat' => 0)), false);
		$this->assertTrue($should_be_confirmed);
	}
}
