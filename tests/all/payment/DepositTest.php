<?php

use Helper\PaymentStatus;

require_once __DIR__ . '/AbstractPaymentCase.php';

/**
 * Deposit cases (legacy Paymenttest cases 9-17): recording a (frozen) deposit,
 * and unfreezing / applying it to debt via action=use_deposit.
 *
 * A deposit is created with deposit='true' (a frozen rec: amount 0, deposit_amount N).
 * use_deposit unfreezes it (amount becomes N, due -N) and applies it to open debt.
 */
class DepositTest extends AbstractPaymentCase
{
    // case 9 - deposit only, no debt
    public function testRecordDeposit()
    {
        $aid = $this->newAccount();
        $this->deposit($aid, 20);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'type' => 'rec',
            'amount' => 0, 'due' => 0, 'deposit' => 'true', 'deposit_amount' => 20, 'left' => 0,
        ]);
    }

    // case 10 - deposit does not pay an existing debt
    public function testDepositLeavesDebtUntouched()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');   // debt
        $this->deposit($aid, 20);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'deposit' => 'true', 'amount' => 0, 'deposit_amount' => 20,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'due' => 15, 'left_to_pay' => 15,
        ]);
    }

    // case 11 - unfreeze a deposit (no debt): it becomes a usable credit
    public function testUnfreezeDeposit()
    {
        $aid = $this->newAccount();
        $this->deposit($aid, 20);
        $this->tester->useDeposit($aid, [$this->depositTxid($aid)]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'type' => 'rec', 'amount' => 20,
            'due' => -20, 'deposit' => 'true', 'deposit_amount' => 0, 'left' => 20,
        ]);
    }

    // case 12 - deposit equals debt: debt fully paid
    public function testUseDepositPaysDebtExactly()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->deposit($aid, 15);
        $this->tester->useDeposit($aid, [$this->depositTxid($aid)]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'deposit' => 'true', 'amount' => 15, 'due' => -15, 'left' => 0,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 0,
            'total_paid' => 15, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
    }

    // case 13 - deposit bigger than debt: 5 left over on the deposit
    public function testUseDepositBiggerThanDebt()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->deposit($aid, 20);
        $this->tester->useDeposit($aid, [$this->depositTxid($aid)]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'deposit' => 'true', 'amount' => 20, 'due' => -20, 'left' => 5,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 0,
            'total_paid' => 15, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
    }

    // case 14 - deposit smaller than debt: debt partially paid
    public function testUseDepositSmallerThanDebt()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 20, 'tc', 'cash');
        $this->deposit($aid, 15);
        $this->tester->useDeposit($aid, [$this->depositTxid($aid)]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'deposit' => 'true', 'amount' => 15, 'due' => -15, 'left' => 0,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 20, 'left_to_pay' => 5,
            'total_paid' => 15, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // case 15 - one deposit of 30 pays two debts of 15
    public function testUseDepositPaysTwoDebts()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->deposit($aid, 30);
        $this->tester->useDeposit($aid, [$this->depositTxid($aid)]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'deposit' => 'true', 'amount' => 30, 'due' => -30, 'left' => 0,
        ]);
        $this->tester->verifyCollectionCount('bills', 2, [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 0,
            'total_paid' => 15, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
    }

    // case 16 - deposit of 20 fully pays one debt and partially the second
    public function testUseDepositPaysTwoDebtsPartially()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->deposit($aid, 20);
        $this->tester->useDeposit($aid, [$this->depositTxid($aid)]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'deposit' => 'true', 'amount' => 20, 'due' => -20, 'left' => 0,
        ]);
        // one debt fully paid
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 0,
            'total_paid' => 15, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        // the other partially paid (5 of 15)
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'left_to_pay' => 10,
            'total_paid' => 5, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // case 17 - a frozen deposit does NOT cover a new refund
    public function testFrozenDepositDoesNotCoverRefund()
    {
        $aid = $this->newAccount();
        $this->deposit($aid, 20);
        $this->tester->payOffline($aid, 15, 'tc', 'cash');   // refund
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'deposit' => 'true', 'amount' => 0, 'deposit_amount' => 20,
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'amount' => 15, 'due' => 15,
            'left_to_pay' => 15, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
    }

    // ------------------------------------------------------------------

    /** create a frozen deposit of $amount on the account */
    private function deposit($aid, $amount)
    {
        $this->tester->payOffline($aid, $amount, 'fc', 'cash', ['deposit' => 'true']);
    }

    /** txid of the account's (frozen) deposit bill */
    private function depositTxid($aid)
    {
        return $this->tester->grabBill(['aid' => $aid, 'deposit' => 'true'])['txid'];
    }
}
