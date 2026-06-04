<?php

use Helper\PaymentStatus;

/**
 * Adjust-payments cases (legacy Paymenttest cases 57-69): /api/adjustpayments
 * adjusts an existing payment by cancelling it and creating a correction (new
 * amount and/or method), plus the validation errors (negative amount, unknown
 * id, missing id, illegal method, empty adjustments, missing amount/method).
 */
class AdjustPaymentTest extends \Codeception\Test\Unit
{
    /** @var \AcceptanceTester */
    protected $tester;

    const CARD_OK = '1022273188555888';

    protected function _before()
    {
        $this->tester->setTimezone('Asia/Jerusalem');
        $this->tester->enableCreditGuardPGWithSettings();
        $this->tester->cleanDB();
    }

    protected function _after()
    {
        $this->tester->restoreTimezone();
    }

    // case 57 - adjust a payment's amount (15 -> 10)
    public function testAdjustAmount()
    {
        $aid = $this->newAccount();
        $txid = $this->payment($aid, 15);
        $this->tester->adjustPayments([['id' => $txid, 'amount' => 10]]);

        $this->tester->verifyCollectionRecord('bills', ['aid' => $aid, 'amount' => 15, 'cancelled' => true]);
        $this->tester->verifyCollectionRecord('bills', ['aid' => $aid, 'amount' => 10, 'correction' => true]);
    }

    // case 58 - adjust to a negative amount -> error
    public function testAdjustNegativeAmountFails()
    {
        $aid = $this->newAccount();
        $txid = $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([['id' => $txid, 'amount' => -15]]);
        $this->assertStringContainsString('amount: -15', json_encode($resp));
        $this->assertNoCorrection($aid);
    }

    // case 59 - adjust an unknown id -> error
    public function testAdjustUnknownIdFails()
    {
        $aid = $this->newAccount();
        $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([['id' => 'Unknown', 'amount' => 30]]);
        $this->assertStringContainsString('Unknown id', json_encode($resp));
        $this->assertNoCorrection($aid);
    }

    // case 60 - adjust two payments
    public function testAdjustTwoPayments()
    {
        $aid = $this->newAccount();
        $t1 = $this->payment($aid, 15);
        $t2 = $this->payment($aid, 20);
        $this->tester->adjustPayments([
            ['id' => $t1, 'amount' => 25],
            ['id' => $t2, 'amount' => 10],
        ]);
        $this->tester->verifyCollectionRecord('bills', ['aid' => $aid, 'amount' => 25, 'correction' => true]);
        $this->tester->verifyCollectionRecord('bills', ['aid' => $aid, 'amount' => 10, 'correction' => true]);
    }

    // case 61 - two adjustments, second has an unknown id -> error
    public function testAdjustSecondUnknownFails()
    {
        $aid = $this->newAccount();
        $txid = $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([
            ['id' => $txid, 'amount' => 25],
            ['id' => 'Unknown', 'amount' => 10],
        ]);
        $this->assertStringContainsString('Unknown id', json_encode($resp));
    }

    // case 62 - adjust method + amount (cash -> cheque, 15 -> 20)
    public function testAdjustMethodAndAmount()
    {
        $aid = $this->newAccount();
        $txid = $this->payment($aid, 15);
        $this->tester->adjustPayments([['id' => $txid, 'method' => 'cheque', 'amount' => 20,
            'payer_name' => 'Yossi', 'deposit_slip_bank' => '12']]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'amount' => 20, 'method' => 'cheque', 'correction' => true,
        ]);
    }

    // case 63 - adjust method only (cash -> cheque)
    public function testAdjustMethodOnly()
    {
        $aid = $this->newAccount();
        $txid = $this->payment($aid, 15);
        $this->tester->adjustPayments([['id' => $txid, 'method' => 'cheque',
            'payer_name' => 'Yossi', 'deposit_slip_bank' => '12']]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'amount' => 15, 'method' => 'cheque', 'correction' => true,
        ]);
    }

    // case 64 - adjust to an illegal method -> error
    public function testAdjustIllegalMethodFails()
    {
        $aid = $this->newAccount();
        $txid = $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([['id' => $txid, 'method' => 'yossi']]);
        $this->assertStringContainsString('Illegal payment method yossi', json_encode($resp));
        $this->assertNoCorrection($aid);
    }

    // case 65 - adjustment without an id -> error
    public function testAdjustMissingIdFails()
    {
        $aid = $this->newAccount();
        $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([['amoumt' => 10]]); // no id
        $this->assertStringContainsString('Missing id', json_encode($resp));
        $this->assertNoCorrection($aid);
    }

    // case 66 - adjustment with an empty id -> error
    public function testAdjustEmptyIdFails()
    {
        $aid = $this->newAccount();
        $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([['id' => '', 'amoumt' => 10]]);
        $this->assertStringContainsString('Missing id', json_encode($resp));
        $this->assertNoCorrection($aid);
    }

    // case 67 - adjust a rejection payment -> "not applicable" (needs a seeded rejection bill)
    public function testAdjustRejectionPaymentFails()
    {
        $this->markTestSkipped('needs a seeded rejection payment bill (gateway rejection history), not reproducible offline');
    }

    // case 68 - empty adjustment object -> error
    public function testAdjustEmptyAdjustmentFails()
    {
        $aid = $this->newAccount();
        $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([]); // truly empty -> "No adjustments found"
        $this->assertStringContainsString('No adjustments found', json_encode($resp));
        $this->assertNoCorrection($aid);
    }

    // case 69 - id but neither method nor amount -> error
    public function testAdjustMissingMethodAndAmountFails()
    {
        $aid = $this->newAccount();
        $txid = $this->payment($aid, 15);
        $resp = $this->tester->adjustPayments([['id' => $txid]]);
        $this->assertStringContainsString('Missing payment method or amount', json_encode($resp));
        $this->assertNoCorrection($aid);
    }

    // ------------------------------------------------------------------

    private function newAccount($card = self::CARD_OK)
    {
        return (int) $this->tester->createAccountWithCard($card)['aid'];
    }

    /** record an offline cash payment of $amount and return its txid */
    private function payment($aid, $amount)
    {
        $this->tester->payOffline($aid, $amount, 'fc', 'cash');
        return $this->tester->grabBill(['aid' => $aid, 'method' => 'cash', 'amount' => $amount])['txid'];
    }

    private function assertNoCorrection($aid)
    {
        $this->tester->verifyCollectionCount('bills', 0, ['aid' => $aid, 'correction' => true]);
    }
}
