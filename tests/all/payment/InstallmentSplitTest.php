<?php

use Helper\PaymentStatus;

/**
 * Split-bill installment cases (legacy Paymenttest cases 18-30): splitting a
 * debt into installments via method=installment_agreement / action=split_bill,
 * by installments_num or by an explicit installments array, plus the rejection
 * paths (amount > debt, contradictory params, installment sum != amount).
 *
 * The split marks the original debt paid via an installment_agreement bill
 * (due = -total) and creates N tc installment bills (payment_agreement.*).
 * Exact charge dates are intentionally NOT asserted (time/timezone sensitive);
 * the structural outcome (amounts, counts, indices, paid status) is verified.
 */
class InstallmentSplitTest extends \Codeception\Test\Unit
{
    /** @var \AcceptanceTester */
    protected $tester;

    const CARD_OK = '1022273188555888';

    /** customer plugins that hook beforeSplitDebt and block split for generic accounts */
    const BLOCKING_PLUGINS = ['epicPlugin', 'gomoPlugin'];

    protected function _before()
    {
        $this->tester->setTimezone('Asia/Jerusalem');
        $this->tester->enableCreditGuardPGWithSettings();
        $this->tester->cleanDB();
        $this->tester->setPluginsEnabled(self::BLOCKING_PLUGINS, false);
    }

    protected function _after()
    {
        $this->tester->setPluginsEnabled(self::BLOCKING_PLUGINS, true);
        $this->tester->restoreTimezone();
    }

    // case 18 - split 15 into 3 (first due/charge dates transferred)
    public function testSplitByNumWithTransferredDates()
    {
        $aid = $this->debtAccount(15);
        $this->tester->splitBill($aid, 15, [
            'installments_num' => 3,
            'first_due_date' => date('Y-m-t', strtotime('next month')),
            'first_charge_date' => date('Y-m-t', strtotime('next month')),
        ]);
        $this->assertSplitOf($aid, 15, 3, 5);
    }

    // case 19 - split 15 into 3 (default dates)
    public function testSplitByNumDefaultDates()
    {
        $aid = $this->debtAccount(15);
        $this->tester->splitBill($aid, 15, ['installments_num' => 3]);
        $this->assertSplitOf($aid, 15, 3, 5);
    }

    // case 20 - split only 14 of a 15 debt -> original partially paid
    public function testSplitPartOfDebt()
    {
        $aid = $this->debtAccount(15);
        $this->tester->splitBill($aid, 14, ['installments_num' => 3]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'method' => 'cash', 'amount' => 15,
            'left_to_pay' => 1, 'total_paid' => 14, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'method' => 'installment_agreement',
            'amount' => 14, 'due' => -14, 'left' => 0,
        ]);
        $this->tester->verifyCollectionCount('bills', 3, [
            'aid' => $aid, 'method' => 'installment_agreement',
            'payment_agreement.installment_index' => ['$exists' => true],
        ]);
    }

    // case 21 - amount bigger than debt -> rejected
    public function testSplitAmountBiggerThanDebtFails()
    {
        $aid = $this->debtAccount(15);
        $resp = $this->tester->splitBill($aid, 20, ['installments_num' => 3]);
        $this->assertResponseContains($resp, 'Passed amount is bigger than the customer debt');
        $this->assertDebtUntouched($aid, 15);
    }

    // case 22 - explicit installments array (3,2,10)
    public function testSplitByInstallmentsArray()
    {
        $aid = $this->debtAccount(15);
        $this->tester->splitBill($aid, 15, ['installments' => $this->installments([3, 2, 10])]);
        $this->assertOriginalPaid($aid, 15);
        $this->assertAgreementBill($aid, 15);
        $this->assertInstallment($aid, 1, 3);
        $this->assertInstallment($aid, 2, 2);
        $this->assertInstallment($aid, 3, 10);
    }

    // case 23 - single installment array
    public function testSplitIntoSingleInstallment()
    {
        $aid = $this->debtAccount(15);
        $this->tester->splitBill($aid, 15, ['installments' => $this->installments([15])]);
        $this->assertOriginalPaid($aid, 15);
        $this->assertAgreementBill($aid, 15);
        $this->assertInstallment($aid, 1, 15);
        $this->tester->verifyCollectionCount('bills', 1, [
            'aid' => $aid, 'method' => 'installment_agreement',
            'payment_agreement.installment_index' => ['$exists' => true],
        ]);
    }

    // case 24 - installments array + first_due/charge dates -> contradiction
    public function testSplitContradictoryParamsFails()
    {
        $aid = $this->debtAccount(15);
        $resp = $this->tester->splitBill($aid, 15, [
            'installments' => $this->installments([3, 2, 10]),
            'first_due_date' => date('Y-m-d', strtotime('next month')),
            'first_charge_date' => date('Y-m-d', strtotime('next month')),
        ]);
        $this->assertResponseContains($resp, 'Passed parameters in contradiction');
        $this->assertDebtUntouched($aid, 15);
    }

    // case 25 - amount > sum of installments
    public function testSplitAmountGreaterThanInstallmentSumFails()
    {
        $aid = $this->debtAccount(15);
        $resp = $this->tester->splitBill($aid, 50, ['installments' => $this->installments([3, 2, 10])]);
        $this->assertResponseContains($resp, 'Sum of amounts in installments array must be equal to total amount');
        $this->assertDebtUntouched($aid, 15);
    }

    // case 26 - amount < sum of installments
    public function testSplitAmountLessThanInstallmentSumFails()
    {
        $aid = $this->debtAccount(15);
        $resp = $this->tester->splitBill($aid, 10, ['installments' => $this->installments([3, 2, 10])]);
        $this->assertResponseContains($resp, 'Sum of amounts in installments array must be equal to total amount');
        $this->assertDebtUntouched($aid, 15);
    }

    // case 27 - installments array without amount
    public function testSplitInstallmentsWithoutAmountFails()
    {
        $aid = $this->debtAccount(15);
        $resp = $this->tester->splitBill($aid, null, ['installments' => $this->installments([3, 2, 10])]);
        $this->assertResponseContains($resp, 'Sum of amounts in installments array must be equal to total amount');
        $this->assertDebtUntouched($aid, 15);
    }

    // case 28 - note passed through (with special characters)
    public function testSplitWithNote()
    {
        $aid = $this->debtAccount(15);
        $note = "b\"la ,.'@\$&bla bla";
        $this->tester->splitBill($aid, 15, ['installments' => $this->installments([3, 2, 10]), 'note' => $note]);
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'method' => 'installment_agreement',
            'amount' => 15, 'due' => -15, 'left' => 0, 'note' => $note,
        ]);
    }

    // case 29 - uf per installment
    public function testSplitWithUserFieldsPerInstallment()
    {
        // uf fields must be whitelisted in config to be persisted on the installments
        $this->tester->overrideConfigValue('payments.offline.uf', ['credit_type', 'payer_address']);
        $aid = $this->debtAccount(15);
        $this->tester->splitBill($aid, 15, ['installments' => [
            ['due_date' => $this->due(1), 'amount' => 3, 'uf' => ['credit_type' => 'leumi1', 'payer_address' => 'blabla1']],
            ['due_date' => $this->due(2), 'amount' => 2, 'uf' => ['credit_type' => 'leumi2', 'payer_address' => 'blabla2']],
            ['due_date' => $this->due(3), 'amount' => 10, 'uf' => ['credit_type' => 'leumi3', 'payer_address' => 'blabla3']],
        ]]);
        foreach ([[1, 3, 'leumi1', 'blabla1'], [2, 2, 'leumi2', 'blabla2'], [3, 10, 'leumi3', 'blabla3']] as [$idx, $amount, $ct, $addr]) {
            $this->tester->verifyCollectionRecord('bills', [
                'aid' => $aid, 'dir' => 'tc', 'method' => 'installment_agreement', 'amount' => $amount,
                'payment_agreement.installment_index' => $idx,
                'uf.credit_type' => $ct, 'uf.payer_address' => $addr,
            ]);
        }
    }

    // case 30 - split a debt that comes from two bills (10 + 5)
    public function testSplitDebtFromTwoBills()
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, 10, 'tc', 'cash');
        $this->tester->payOffline($aid, 5, 'tc', 'cash');
        $this->tester->splitBill($aid, 15, ['installments' => $this->installments([3, 2, 10])]);

        $this->tester->verifyCollectionCount('bills', 2, [
            'aid' => $aid, 'dir' => 'tc', 'method' => 'cash', 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
        $this->assertAgreementBill($aid, 15);
        $this->assertInstallment($aid, 1, 3);
        $this->assertInstallment($aid, 2, 2);
        $this->assertInstallment($aid, 3, 10);
    }

    // ------------------------------------------------------------------
    //  helpers
    // ------------------------------------------------------------------

    private function newAccount($card = self::CARD_OK)
    {
        return (int) $this->tester->createAccountWithCard($card)['aid'];
    }

    /** account carrying a single tc cash debt of $amount */
    private function debtAccount($amount)
    {
        $aid = $this->newAccount();
        $this->tester->payOffline($aid, $amount, 'tc', 'cash');
        return $aid;
    }

    /** build an installments array [{due_date, amount}] from a list of amounts */
    private function installments(array $amounts)
    {
        $out = [];
        foreach ($amounts as $i => $amount) {
            $out[] = ['due_date' => $this->due($i + 1), 'amount' => $amount];
        }
        return $out;
    }

    /** last day of month, $n months ahead, as Y-m-t */
    private function due($n)
    {
        return date('Y-m-t', strtotime("+$n month"));
    }

    private function assertResponseContains($resp, $needle)
    {
        $this->assertStringContainsString($needle, json_encode($resp));
    }

    private function assertDebtUntouched($aid, $amount)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'method' => 'cash', 'amount' => $amount,
            'left_to_pay' => $amount, 'paid' => ['$in' => PaymentStatus::UNPAID],
        ]);
        $this->tester->verifyCollectionCount('bills', 0, [
            'aid' => $aid, 'method' => 'installment_agreement',
        ]);
    }

    private function assertOriginalPaid($aid, $amount)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'method' => 'cash', 'amount' => $amount,
            'left_to_pay' => 0, 'total_paid' => $amount, 'paid' => ['$in' => PaymentStatus::PAID],
        ]);
    }

    private function assertAgreementBill($aid, $total)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'fc', 'method' => 'installment_agreement',
            'amount' => $total, 'due' => -$total, 'left' => 0,
        ]);
    }

    private function assertInstallment($aid, $index, $amount)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'dir' => 'tc', 'method' => 'installment_agreement', 'type' => 'rec',
            'amount' => $amount, 'left_to_pay' => $amount, 'paid' => ['$in' => PaymentStatus::UNPAID],
            'payment_agreement.installment_index' => $index,
        ]);
    }

    /** full split of $total into 3 equal installments */
    private function assertSplitOf($aid, $total, $count, $each)
    {
        $this->assertOriginalPaid($aid, $total);
        $this->assertAgreementBill($aid, $total);
        $this->tester->verifyCollectionCount('bills', $count, [
            'aid' => $aid, 'method' => 'installment_agreement',
            'payment_agreement.installment_index' => ['$exists' => true],
        ]);
        $this->assertInstallment($aid, 1, $each);
    }
}
