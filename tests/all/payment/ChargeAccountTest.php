<?php

use Helper\PaymentStatus;

require_once __DIR__ . '/AbstractPaymentCase.php';

/**
 * chargeAccount cases (legacy Paymenttest cases 72-77): /billrun/chargeAccount
 * charges accounts that have outstanding debt through the (mockup) CreditGuard
 * gateway. Covers: no filter (charge all given), aids filter, min_invoice_date,
 * pay_mode (one_payment vs multiple_payments) and exclude_accounts.
 *
 * Charging multiple accounts runs asynchronously (the controller forks a CLI
 * worker), so assertions poll for the resulting receipt bill.
 */
class ChargeAccountTest extends AbstractPaymentCase
{
    const CHARGE_WAIT = 45;

    protected $pinVat = true;
    protected $clearBills = true;
    protected $seedCallRate = true;

    // case 72 - no filter: both accounts with debt get charged
    public function testChargeAllAccounts()
    {
        $a1 = $this->newAccount();
        $a2 = $this->newAccount();
        $this->invoice($a1, 20); // 23.4
        $this->invoice($a2, 40); // 46.8
        $this->tester->chargeAccountApi(['aids' => "$a1,$a2"]);
        $this->assertCharged($a1, 23.4);
        $this->assertCharged($a2, 46.8);
    }

    // case 73 - aids filter: only the listed account is charged
    public function testChargeOnlyFilteredAccount()
    {
        $a1 = $this->newAccount();
        $a2 = $this->newAccount();
        $this->invoice($a1, 20);
        $this->invoice($a2, 40);
        $this->tester->chargeAccountApi(['aids' => "$a1"]);
        $this->assertCharged($a1, 23.4);
        // a2 stays unpaid
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $a2, 'type' => 'inv', 'amount' => 46.8, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // case 75 - pay_mode multiple_payments: one receipt per invoice
    public function testChargePayModeMultiple()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $this->invoice($aid, 40);
        $this->tester->chargeAccountApi(['aids' => "$aid", 'pay_mode' => 'multiple_payments']);
        $this->waitForReceipt($aid);
        $this->tester->verifyCollectionCount('bills', 2, [
            'aid' => $aid, 'type' => 'rec', 'vendor_response.status' => '000',
        ]);
    }

    // case 76 - pay_mode one_payment: a single receipt covers both invoices
    public function testChargePayModeOne()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $this->invoice($aid, 40);
        $this->tester->chargeAccountApi(['aids' => "$aid", 'pay_mode' => 'one_payment']);
        $this->waitForReceipt($aid);
        $this->tester->verifyCollectionCount('bills', 1, [
            'aid' => $aid, 'type' => 'rec', 'vendor_response.status' => '000',
        ]);
    }

    // case 77 - exclude_accounts: the excluded account is not charged
    public function testChargeExcludeAccount()
    {
        $a1 = $this->newAccount();
        $a2 = $this->newAccount();
        $excluded = $this->newAccount();
        $this->invoice($a1, 10);
        $this->invoice($a2, 20);
        $this->invoice($excluded, 40);
        $this->tester->chargeAccountApi(['exclude_accounts' => "$excluded"]);
        $this->assertCharged($a1, 11.7);
        $this->assertCharged($a2, 23.4);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $excluded, 'type' => 'inv', 'amount' => 46.8, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // case 74 - min_invoice_date filters out invoices dated before the threshold:
    // only the newer invoice (invoice_date >= threshold) is charged; the older one
    // stays unpaid. invoice_date for an immediate invoice == its invoice_unixtime.
    public function testChargeMinInvoiceDate()
    {
        $aid = $this->newAccount();
        $this->billAt($aid, 20, time() - 32 * 86400); // 23.4, older -> filtered out
        $this->billAt($aid, 40, time() - 28 * 86400); // 46.8, newer -> charged
        $minDate = date('m/d/Y', time() - 30 * 86400); // threshold strictly between the two days

        $this->tester->chargeAccountApi(['aids' => "$aid", 'min_invoice_date' => $minDate]);
        $this->waitForReceipt($aid);

        // the newer invoice is charged 000
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'amount' => 46.8, 'method' => 'automatic', 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'rec', 'due' => -46.8, 'pending' => false, 'vendor_response.status' => '000',
        ]);
        // the older invoice (before the threshold) stays unpaid, and only one receipt exists
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'amount' => 23.4, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
        $this->tester->verifyCollectionCount('bills', 1, ['aid' => $aid, 'type' => 'rec', 'vendor_response.status' => '000']);
    }

    // ------------------------------------------------------------------

    /** create a past-due invoice at a specific invoice_unixtime (for date-filter tests) */
    private function billAt($aid, $aprice, $unixtime)
    {
        $this->tester->onetimeInvoice($aid, [[
            'aid' => $aid, 'sid' => 0, 'rate' => 'CALL', 'usagev' => 1,
            'type' => 'credit', 'credit_time' => '2021-04-18T17:17:56Z', 'aprice' => $aprice,
        ]], ['step' => 1, 'invoice_unixtime' => $unixtime]);
    }

    /** create a PAST-due invoice (so it is chargeable); distinct day offsets avoid stamp clashes */
    private function invoice($aid, $aprice)
    {
        $unixtime = time() - 2592000 - ($this->invoiceSeq++ * 86400); // ~30+ days ago, one day apart
        $this->tester->onetimeInvoice($aid, [[
            'aid' => $aid, 'sid' => 0, 'rate' => 'CALL', 'usagev' => 1,
            'type' => 'credit', 'credit_time' => '2021-04-18T17:17:56Z', 'aprice' => $aprice,
        ]], ['step' => 1, 'invoice_unixtime' => $unixtime]);
    }

    /** poll until a gateway receipt appears for the account (multi-account charge is async) */
    private function waitForReceipt($aid)
    {
        $deadline = time() + self::CHARGE_WAIT;
        do {
            if ($this->tester->grabBill(['aid' => $aid, 'type' => 'rec', 'vendor_response.status' => '000'])) {
                return;
            }
            usleep(400000);
        } while (time() < $deadline);
        // fail loudly here rather than letting the caller's verifyCollectionRecord
        // report a confusing "0 is greater than 0" when the async charge timed out.
        $this->fail("no gateway receipt (status 000) for aid $aid within " . self::CHARGE_WAIT . 's');
    }

    /** assert the account's invoice was paid via the mockup gateway (receipt 000) */
    private function assertCharged($aid, $amount)
    {
        $this->waitForReceipt($aid);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'amount' => $amount,
            'method' => 'automatic', 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'rec', 'due' => -$amount, 'pending' => false,
            'vendor_response.status' => '000',
        ]);
    }
}
