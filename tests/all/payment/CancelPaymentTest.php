<?php

use Helper\PaymentStatus;

/**
 * Cancel-payments cases (legacy Paymenttest cases 38-42, 71): cancelling
 * offline payments via action=cancel_payments. Cancelling a payment marks it
 * cancelled, restores the debt it had paid, and creates a reversal record
 * carrying the supplied uf (payer_name / reason_code). Covers ignore_errors
 * on/off with a bogus txid, cancelling an already-cancelled bill, and
 * cancelling several payments that were linked to an invoice.
 */
class CancelPaymentTest extends \Codeception\Test\Unit
{
    /** @var \AcceptanceTester */
    protected $tester;

    const CARD_OK = '1022273188555888';
    const UF = ['payer_name' => 'Yossi', 'reason_code' => '4'];

    protected function _before()
    {
        $this->tester->setTimezone('Asia/Jerusalem');
        $this->tester->enableCreditGuardPGWithSettings();
        $this->tester->overrideConfigValue('taxation.default', ['key' => 'DEFAULT_VAT']); // pin VAT to 17%
        $this->tester->cleanDB();
    }

    protected function _after()
    {
        $this->tester->restoreTimezone();
    }

    // case 38 - cancel a single payment that fully paid a debt
    public function testCancelSinglePayment()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');   // debt
        $this->tester->payOffline($aid, 15, 'fc', 'cash');   // payment (left 0)
        $txid = $this->tester->grabBill(['aid' => $aid, 'dir' => 'fc', 'method' => 'cash'])['txid'];

        $this->tester->cancelPayments($aid, [['txid' => $txid, 'uf' => self::UF]]);

        // debt restored
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 15,
            'total_paid' => 0, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
        // original payment marked cancelled
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'amount' => 15, 'due' => -15, 'cancelled' => true, 'pending' => false,
        ]);
        // reversal record with uf
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'amount' => 15, 'due' => 15, 'left_to_pay' => 15,
            'uf.payer_name' => 'Yossi', 'uf.reason_code' => '4',
        ]);
    }

    // case 39 - debt paid by two payments; cancel only the 5
    public function testCancelOneOfTwoPayments()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 5, 'fc', 'cash');
        $this->tester->payOffline($aid, 10, 'fc', 'cash');
        $txid = $this->tester->grabBill(['aid' => $aid, 'dir' => 'fc', 'amount' => 5])['txid'];

        $this->tester->cancelPayments($aid, [['txid' => $txid, 'uf' => self::UF]]);

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 5,
            'total_paid' => 10, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'amount' => 5, 'due' => -5, 'cancelled' => true,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'amount' => 10, 'due' => -10, 'left' => 0,
        ]);
        // reversal of 5
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'amount' => 5, 'due' => 5, 'left_to_pay' => 5,
        ]);
    }

    // case 40 - ignore_errors=1: valid cancellation applied, bogus txid ignored
    public function testCancelIgnoreErrors()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 15, 'fc', 'cash');
        $txid = $this->tester->grabBill(['aid' => $aid, 'dir' => 'fc', 'method' => 'cash'])['txid'];

        $this->tester->cancelPayments($aid, [
            ['txid' => $txid, 'uf' => self::UF],
            ['txid' => '123456789', 'uf' => self::UF],
        ], ['ignore_errors' => '1']);

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 15, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'amount' => 15, 'cancelled' => true,
        ]);
    }

    // case 41 - ignore_errors=0: bogus txid aborts everything, nothing cancelled
    public function testCancelNoIgnoreErrorsAborts()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 15, 'fc', 'cash');
        $txid = $this->tester->grabBill(['aid' => $aid, 'dir' => 'fc', 'method' => 'cash'])['txid'];

        $resp = $this->tester->cancelPayments($aid, [
            ['txid' => $txid, 'uf' => self::UF],
            ['txid' => '123456789', 'uf' => self::UF],
        ], ['ignore_errors' => '0']);

        $this->assertStringContainsString('123456789 Not Found', json_encode($resp));
        // debt stays paid, payment NOT cancelled
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 0,
            'total_paid' => 15, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->tester->verifyCollectionCount('bills', 0, [
            'aid' => $aid, 'cancelled' => true,
        ]);
    }

    // case 42 - cancelling an already-cancelled (reversal) bill is a no-op
    public function testCancelAlreadyCancelledIsNoop()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 15, 'fc', 'cash');
        $txid = $this->tester->grabBill(['aid' => $aid, 'dir' => 'fc', 'method' => 'cash'])['txid'];
        $this->tester->cancelPayments($aid, [['txid' => $txid, 'uf' => self::UF]]);   // first cancel

        // try to cancel the reversal/cancellation record itself
        $reversal = $this->tester->grabBill(['aid' => $aid, 'cancel' => ['$exists' => 1]]);
        $this->tester->cancelPayments($aid, [['txid' => $reversal['txid'], 'uf' => self::UF]]);

        // state unchanged: still exactly one cancelled payment and one reversal
        $this->tester->verifyCollectionCount('bills', 1, ['aid' => $aid, 'dir' => 'fc', 'cancelled' => true]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 15, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // case 71 - cancel 3 payments, one of which paid an invoice -> invoice unpaid again
    public function testCancelPaymentsLinkedToInvoice()
    {
        $aid = $this->newAccount();
        $this->ensureCallRate();
        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 20)], ['step' => 1]); // inv 23.4
        $this->tester->payOffline($aid, 23.4, 'fc', 'cash');   // pays the invoice
        $this->tester->payOffline($aid, 15, 'fc', 'cash');
        $this->tester->payOffline($aid, 20, 'fc', 'cash');

        $cancellations = [];
        foreach ([23.4, 15, 20] as $amount) {
            $cancellations[] = ['txid' => $this->tester->grabBill(['aid' => $aid, 'dir' => 'fc', 'amount' => $amount])['txid']];
        }
        $this->tester->cancelPayments($aid, $cancellations);

        // the invoice is unpaid again with cleared paid_by
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'type' => 'inv', 'amount' => 23.4, 'left_to_pay' => 23.4,
            'total_paid' => 0, 'paid' => ['$in' => PaymentStatus::UNPAID], 'paid_by' => [],
        ]);
    }

    // ------------------------------------------------------------------

    private function newAccount($card = self::CARD_OK)
    {
        return (int) $this->tester->createAccountWithCard($card)['aid'];
    }

    private function ensureCallRate()
    {
        $this->tester->seedCallRate();
    }

    private function cdr($aid, $aprice, $creditTime = '2021-03-18T17:18:56Z')
    {
        return [
            'aid' => $aid, 'sid' => 0, 'rate' => 'CALL', 'usagev' => 1,
            'type' => 'credit', 'credit_time' => $creditTime, 'aprice' => $aprice,
        ];
    }
}
