<?php

use Helper\PaymentStatus;

/**
 * One-time-invoice charge/refund cases (legacy Paymenttest cases 78-89): the
 * /api/onetimeinvoice endpoint creating an immediate invoice and charging it via
 * the (mockup) CreditGuard gateway, with charge_flow regular vs
 * charge_before_invoice, refunds (negative amount) and gateway rejection.
 *
 * step 2 = create the bill AND charge it. A successful charge yields a receipt
 * with vendor_response.status=000 and a paid invoice; a rejected charge (an
 * un-chargeable card token) yields a failure response.
 */
class OnetimeInvoiceTest extends \Codeception\Test\Unit
{
    /** @var \AcceptanceTester */
    protected $tester;

    const CARD_OK = '1022273188555888';
    const CARD_REJECT = '1022273188';

    protected function _before()
    {
        $this->tester->setTimezone('Asia/Jerusalem');
        $this->tester->enableCreditGuardPGWithSettings();
        $this->tester->overrideConfigValue('taxation.default', ['key' => 'DEFAULT_VAT']); // 17%
        $this->tester->cleanDB();
        $this->tester->seedCallRate();
    }

    protected function _after()
    {
        $this->tester->restoreTimezone();
    }

    // case 78 - charge_before_invoice: invoice created and charged (10 -> 11.7)
    public function testChargeBeforeInvoice()
    {
        $aid = $this->newAccount();
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 10)], ['step' => 2, 'charge_flow' => 'charge_before_invoice']);
        $this->assertChargedInvoice($aid, 11.7);
    }

    // case 79 - regular flow, step 2: invoice created and charged
    public function testChargeRegularFlow()
    {
        $aid = $this->newAccount();
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 10)], ['step' => 2]);
        $this->assertChargedInvoice($aid, 11.7);
    }

    // case 80 - refund (negative), regular flow: credit invoice + refund receipt
    public function testRefundRegularFlow()
    {
        $aid = $this->newAccount();
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, -10)], ['step' => 2]);
        $this->assertRefundInvoice($aid, 11.7);
    }

    // case 81 - refund (negative) with charge_before_invoice: an outbound refund is
    // not completed synchronously by the CreditGuard gateway, so the charge-before
    // step fails and NO invoice is created (contrast case 80's regular flow, which
    // records the refund invoice + a pending refund receipt).
    public function testRefundChargeBefore()
    {
        $aid = $this->newAccount();
        $resp = $this->tester->onetimeInvoice($aid, [$this->cdr($aid, -10)], ['step' => 2, 'charge_flow' => 'charge_before_invoice']);
        $this->assertEquals(0, $resp['status'] ?? ($resp[0]['status'] ?? null));
        $this->tester->verifyCollectionCount('bills', 0, ['aid' => $aid, 'type' => 'inv']);
    }

    // case 82 - charge_before_invoice with a card that the gateway rejects -> charge fails
    public function testChargeBeforeInvoiceRejection()
    {
        $aid = $this->newAccount(self::CARD_REJECT);
        $resp = $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 2, 'charge_flow' => 'charge_before_invoice']);
        // charge_before_invoice aborts when the gateway declines
        $this->assertEquals(0, $resp[0]['status'] ?? ($resp['status'] ?? null));
        // no successful receipt was created
        $this->tester->verifyCollectionCount('bills', 0, ['aid' => $aid, 'type' => 'rec', 'vendor_response.status' => '000']);
    }

    // cases 83, 85, 86, 88, 89 - rejection-refund and the "step 2 after a prior
    // rejection" retry scenarios depend on a pre-existing rejected/aged invoice
    // state (fixture invoice_id 8100000006) that the API can't reproduce in one
    // shot; they belong with the gateway-rejection/retry workflow.
    // case 83 - a refund whose outbound charge is rejected: regular flow creates the
    // refund (credit) invoice, then the refund charge is declined -> rejection bills.
    public function testRefundRejection()
    {
        $aid = $this->newAccount(self::CARD_REJECT);
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, -20)], ['step' => 2, 'invoice_unixtime' => 'past']);
        $this->tester->verifyCollectionRecord('bills', ['aid' => $aid, 'type' => 'inv', 'amount' => 23.4, 'due' => -23.4]);
        $this->tester->verifyCollectionRecord('bills', ['aid' => $aid, 'type' => 'rec', 'rejection' => true]);
    }

    // case 85 - account already has an unpaid debt, then a new immediate charge:
    // the 35.1 charge clears the prior 23.4 debt (FIFO) and part of the new invoice,
    // so a 35.1 gateway receipt exists and the net balance is the remaining 23.4.
    public function testChargeAccountWithExistingDebt()
    {
        $aid = $this->newAccount();
        // prior unpaid debt (distinct invoice_unixtime avoids the same-second billrun stamp clash)
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 1, 'invoice_unixtime' => time() - 86400]);
        // new immediate charge of 30 -> 35.1, charged through the gateway
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 30)], ['step' => 2, 'charge_flow' => 'charge_before_invoice']);

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'rec', 'amount' => 35.1, 'due' => -35.1,
            'pending' => false, 'vendor_response.status' => '000',
        ]);
        $resp = $this->tester->billQuery('get_balances', ['aids' => $aid]);
        $this->assertEqualsWithDelta(23.4, $resp['details'][$aid]['total'], 0.001);
    }

    // case 86 - account is in credit (a prior refund invoice), then a new immediate
    // charge: the credit invoice remains; the new invoice is charged 000.
    public function testChargeAccountOverpaid()
    {
        $aid = $this->newAccount();
        // prior credit (refund invoice) -> account in credit -23.4
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, -20)], ['step' => 1, 'invoice_unixtime' => time() - 86400]);
        // new immediate charge of 30 -> 35.1, charged through the gateway
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 30)], ['step' => 2, 'charge_flow' => 'charge_before_invoice']);

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'amount' => 23.4, 'due' => -23.4, 'due_before_vat' => -20,
        ]);
        $this->assertChargedInvoice($aid, 35.1);
    }

    // case 88 - an invoice whose charge was rejected is charged again (retry) with a
    // working card and gets paid. Regular flow creates the invoice before charging,
    // so the rejected charge leaves a real past-due unpaid invoice to retry.
    public function testStep2AfterRejection()
    {
        $aid = $this->newAccount(self::CARD_REJECT);
        // step 2 regular flow: invoice is created, the charge is rejected -> invoice stays unpaid
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 2, 'invoice_unixtime' => 'past']);
        $invoiceId = $this->tester->grabBill(['aid' => $aid, 'type' => 'inv', 'paid' => ['$in' => PaymentStatus::UNPAID]])['invoice_id'];

        // retry: flip to a working card and charge the outstanding (past-due) debt
        $this->tester->setAccountCardToken($aid, self::CARD_OK);
        $this->tester->chargeAccountApi(['aids' => "$aid"]);

        // the previously-rejected invoice is now paid by a 000 receipt
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'invoice_id' => $invoiceId, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'rec', 'amount' => 23.4, 'due' => -23.4, 'pending' => false, 'vendor_response.status' => '000',
        ]);
    }

    // case 89 - as case 88, but the retry uses the charge_before_invoice flow: a
    // previously-rejected invoice is settled when a later charge (with a working
    // card) succeeds and its receipt pays the outstanding debt (FIFO).
    public function testStep2AfterRejectionChargeBefore()
    {
        $aid = $this->newAccount(self::CARD_REJECT);
        // regular flow: invoice created, charge rejected -> past-due unpaid invoice
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 2, 'invoice_unixtime' => 'past']);
        $priorId = $this->tester->grabBill(['aid' => $aid, 'type' => 'inv', 'paid' => ['$in' => PaymentStatus::UNPAID]])['invoice_id'];

        // retry via charge_before_invoice with a working card -> 000 receipt clears the prior debt
        $this->tester->setAccountCardToken($aid, self::CARD_OK);
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 2, 'charge_flow' => 'charge_before_invoice']);

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'invoice_id' => $priorId, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'rec', 'amount' => 23.4, 'due' => -23.4, 'pending' => false, 'vendor_response.status' => '000',
        ]);
    }

    // ------------------------------------------------------------------

    private function newAccount($card = self::CARD_OK)
    {
        return (int) $this->tester->createAccountWithCard($card)['aid'];
    }

    private function cdr($aid, $aprice)
    {
        return [
            'aid' => $aid, 'sid' => 0, 'rate' => 'CALL', 'usagev' => 1,
            'type' => 'credit', 'credit_time' => '2021-04-18T17:17:56Z', 'aprice' => $aprice,
        ];
    }

    /** a debit invoice was created and paid by a gateway receipt */
    private function assertChargedInvoice($aid, $amount)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'amount' => $amount, 'due' => $amount,
            'method' => 'automatic', 'paid' => ['$in' => PaymentStatus::PAID], 'left_to_pay' => 0,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'rec', 'amount' => $amount, 'due' => -$amount,
            'pending' => false, 'vendor_response.status' => '000',
        ]);
    }

    /**
     * A credit (refund) invoice was created and a refund receipt issued.
     * NB: against the mockup gateway a refund-to-customer receipt is recorded
     * pending (paid='2'), not auto-completed like an inbound charge, so we assert
     * the refund invoice + the matching refund receipt rather than a 000 status.
     */
    private function assertRefundInvoice($aid, $amount)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'amount' => $amount, 'due' => -$amount, 'method' => 'automatic',
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'rec', 'dir' => 'tc', 'amount' => $amount, 'due' => $amount, 'method' => 'automatic',
        ]);
    }
}
