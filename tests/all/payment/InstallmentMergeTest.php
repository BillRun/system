<?php

use Helper\PaymentStatus;

require_once __DIR__ . '/AbstractPaymentCase.php';

/**
 * Merge-installments cases (legacy Paymenttest cases 31-34): merging the open
 * installments of a split agreement into a single new installment
 * (method=merge_installments). Covers: merge all, merge when one installment is
 * already paid, and due-date inheritance when first_charge_date is omitted.
 *
 * The merge marks the merged installments paid and creates a new tc
 * merge_installments bill carrying a bills_merged list. As with split, the
 * customer plugins that hook beforeSplitDebt are disabled.
 */
class InstallmentMergeTest extends AbstractPaymentCase
{
    // case 31 - merge all 3 installments (due/charge dates given)
    public function testMergeAllInstallments()
    {
        $aid = $this->splitDebt();
        $due = date('Y-m-t', strtotime('+1 month'));
        $resp = $this->tester->mergeInstallments($aid, $this->splitId($aid), [
            'amount' => 15, 'due_date' => $due, 'first_charge_date' => $due,
        ]);
        $this->assertStringContainsString('merged installments successfully', json_encode($resp));

        // the 3 original installments are now paid
        $this->tester->verifyCollectionCount('bills', 3, [
            'aid' => $aid, 'method' => 'installment_agreement', 'dir' => 'tc',
            'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->assertMergedBill($aid, 15, 3);
    }

    // case 32 - one installment already paid, merge the remaining two (amount 12)
    public function testMergeWhenOneInstallmentPaid()
    {
        $aid = $this->splitDebt();
        $this->tester->payOffline($aid, 3, 'fc', 'cash'); // pays installment #1 (amount 3)
        $due = date('Y-m-t', strtotime('+1 month'));
        $resp = $this->tester->mergeInstallments($aid, $this->splitId($aid), [
            'amount' => 12, 'due_date' => $due, 'first_charge_date' => $due,
        ]);
        $this->assertStringContainsString('merged installments successfully', json_encode($resp));
        $this->assertMergedBill($aid, 12, 2);
    }

    // case 33 - no first_charge_date: merged bill inherits the 1st installment due date
    public function testMergeInheritsFirstInstallmentDueDate()
    {
        $aid = $this->splitDebt();
        $this->tester->mergeInstallments($aid, $this->splitId($aid), ['amount' => 15]);
        $this->assertMergedBill($aid, 15, 3);
        $this->assertMergedDueDateEqualsInstallment($aid, 1);
    }

    // case 34 - one paid + no first_charge_date: merged bill inherits the first UNPAID installment date
    public function testMergeInheritsFirstUnpaidDueDate()
    {
        $aid = $this->splitDebt();
        $this->tester->payOffline($aid, 3, 'fc', 'cash'); // pays installment #1
        $this->tester->mergeInstallments($aid, $this->splitId($aid), ['amount' => 12]);
        $this->assertMergedBill($aid, 12, 2);
        $this->assertMergedDueDateEqualsInstallment($aid, 2);
    }

    // ------------------------------------------------------------------

    /** account with a 15 debt split into 3 installments (3, 2, 10) */
    private function splitDebt()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 15, 'tc', 'cash');
        $this->tester->splitBill($aid, 15, ['installments' => [
            ['due_date' => date('Y-m-t', strtotime('+1 month')), 'amount' => 3],
            ['due_date' => date('Y-m-t', strtotime('+2 month')), 'amount' => 2],
            ['due_date' => date('Y-m-t', strtotime('+3 month')), 'amount' => 10],
        ]]);
        return $aid;
    }

    /** id of the installment agreement (the fc bill carrying payment_agreement + pays) */
    private function splitId($aid)
    {
        $agreement = $this->tester->grabBill([
            'aid' => $aid, 'payment_agreement' => ['$exists' => 1], 'pays' => ['$exists' => 1],
        ]);
        return $agreement['payment_agreement']['id'];
    }

    private function assertMergedBill($aid, $amount, $mergedCount)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'method' => 'merge_installments', 'dir' => 'tc', 'type' => 'rec',
            'amount' => $amount, 'bills_merged' => ['$size' => $mergedCount],
        ]);
    }

    private function assertMergedDueDateEqualsInstallment($aid, $installmentIndex)
    {
        // the new consolidated installment is the merge_installments bill carrying bills_merged
        $merged = $this->tester->grabBill([
            'aid' => $aid, 'method' => 'merge_installments', 'bills_merged' => ['$exists' => 1],
        ]);
        $installment = $this->tester->grabBill([
            'aid' => $aid, 'method' => 'installment_agreement',
            'payment_agreement.installment_index' => $installmentIndex,
        ]);
        $this->assertEquals($installment['due_date']->sec, $merged['due_date']->sec);
    }
}
