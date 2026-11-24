<?php

use Helper\PaymentStatus;

require_once __DIR__ . '/AbstractPaymentCase.php';

/**
 * Offline payment / refund cases (legacy Paymenttest cases 1-8, 35, 36, 37):
 * recording offline payments and refunds, paying off / partially paying debt,
 * overpayment, cheque method, and paying a specific invoice via the `pays` link.
 *
 * Style: one method per case; account + debt created through the API; bills
 * verified with verifyCollectionRecord. The mockup CreditGuard gateway is
 * enabled in _before (offline cases don't charge it, but the account carries a
 * payment_gateway uniformly).
 */
class OfflinePaymentTest extends AbstractPaymentCase
{
    protected $pinVat = true;

    // case 1
    public function testRecordOfflineRefund()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'method' => 'cash', 'type' => 'rec',
            'amount' => 15, 'due' => 15, 'left_to_pay' => 15,
        ]);
    }

    // case 2
    public function testRecordOfflinePayment()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'fc', 'cash');
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'method' => 'cash', 'type' => 'rec',
            'amount' => 15, 'due' => -15, 'left' => 15,
        ]);
    }

    // case 3 - payment that settles an existing invoice debt
    public function testPaymentSettlesInvoiceDebt()
    {
        $aid = $this->newAccount();
        $this->tester->seedCallRate();
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 1]);
        $this->tester->payOffline($aid, 23.4, 'fc', 'cash');
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'method' => 'cash', 'type' => 'rec',
            'amount' => 23.4, 'due' => -23.4, 'left' => 0,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
    }

    // case 4 - pay off a tc debt of 15 with an fc payment of 15
    public function testPayOffDebtFully()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');   // debt
        $this->tester->payOffline($aid, 15, 'fc', 'cash');   // payment

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'type' => 'rec', 'amount' => 15,
            'due' => -15, 'left' => 0, 'pays' => ['$exists' => true],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'type' => 'rec', 'amount' => 15,
            'left_to_pay' => 0, 'paid' => ['$in' => PaymentStatus::PAID],
            'paid_by' => ['$exists' => true],
        ]);
    }

    // case 5 - pay part (10) of a 15 debt
    public function testPayDebtPartially()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 10, 'fc', 'cash');

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'type' => 'rec', 'amount' => 10, 'due' => -10, 'left' => 0,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'type' => 'rec', 'amount' => 15,
            'left_to_pay' => 5, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // case 6 - pay remaining 5 after 10 already paid (3 bills total)
    public function testPayRemainingAfterPartial()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 10, 'fc', 'cash');
        $this->tester->payOffline($aid, 5, 'fc', 'cash');

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'type' => 'rec', 'amount' => 15,
            'left_to_pay' => 0, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'type' => 'rec', 'amount' => 5, 'due' => -5, 'left' => 0,
        ]);
        $this->tester->verifyCollectionCount('bills', 3, ['aid' => $aid, 'type' => 'rec']);
    }

    // case 7 - overpay: pay 20 against a 15 debt -> 5 left as credit
    public function testOverpayLeavesCredit()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 20, 'fc', 'cash');

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'type' => 'rec', 'amount' => 20, 'due' => -20, 'left' => 5,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'type' => 'rec', 'amount' => 15,
            'left_to_pay' => 0, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
    }

    // case 8 - refund 20 when customer has a 15 overpayment credit
    public function testRefundBiggerThanOverpay()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'fc', 'cash');   // overpayment / credit
        $this->tester->payOffline($aid, 20, 'tc', 'cash');   // refund

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'type' => 'rec', 'amount' => 15, 'due' => -15, 'left' => 0,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'type' => 'rec', 'amount' => 20,
            'due' => 20, 'left_to_pay' => 5, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // case 35 - cheque payment with deposit_slip
    public function testChequePayment()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'fc', 'cheque', [
            'payer_name' => 'Test Payer', 'deposit_slip' => '3', 'deposit_slip_bank' => '', 'source' => 'web',
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'method' => 'cheque', 'type' => 'rec',
            'amount' => 15, 'due' => -15, 'left' => 15, 'deposit_slip' => '3',
        ]);
    }

    // case 36 - pay one specific invoice (via pays link) leaving another debt unpaid
    public function testPaySpecificInvoiceViaPays()
    {
        $aid = $this->newAccount();
        $this->tester->seedCallRate();
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 1]); // inv due 23.4
        $invoiceId = $this->tester->grabBill(['aid' => $aid, 'type' => 'inv'])['invoice_id'];
        $this->tester->payOffline($aid, 10, 'tc', 'cash');   // a second, separate debt

        $this->tester->payPayments([[
            'dir' => 'fc', 'aid' => $aid, 'amount' => 23.4,
            'pays' => ['inv' => [(string) $invoiceId => 23.4]],
        ]], 'cash');

        // the targeted invoice is paid ...
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'invoice_id' => $invoiceId,
            'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        // ... while the unrelated tc debt is left untouched
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'type' => 'rec', 'amount' => 10, 'left_to_pay' => 10,
        ]);
    }

    // case 37 - partially pay a specific invoice (5 of 23.4) via pays link
    public function testPayPartOfSpecificInvoiceViaPays()
    {
        $aid = $this->newAccount();
        $this->tester->seedCallRate();
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 1]); // inv due 23.4
        $invoiceId = $this->tester->grabBill(['aid' => $aid, 'type' => 'inv'])['invoice_id'];
        $this->tester->payOffline($aid, 10, 'tc', 'cash');

        $this->tester->payPayments([[
            'dir' => 'fc', 'aid' => $aid, 'amount' => 5,
            'pays' => ['inv' => [(string) $invoiceId => 5]],
        ]], 'cash');

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'invoice_id' => $invoiceId,
            'left_to_pay' => 18.4, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }
}
