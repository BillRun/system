<?php

/**
 * "switch_links" cases (legacy Paymenttest cases 87, 90-97): an invoice that is
 * partially covered by a PENDING gateway bill, and what happens to that pending
 * link when a new real payment (offline / chargeAccount / onetimeinvoice) arrives,
 * toggled by the `bills.switch_links` configuration flag.
 *
 * With switch_links=TRUE the pending (still-unconfirmed) link is detached and the
 * real payment re-links onto the freed debt; with switch_links=FALSE the pending
 * link is left in place. The pending state itself (a file-charge bill awaiting
 * confirmation: pending=true / waiting_for_confirmation=true / cpg_* artifacts) is
 * one the online CreditGuard gateway never emits (hasPendingStatus()=false), so —
 * exactly as the legacy SimpleTest did — it is seeded via seedSwitchLinksFixture()
 * rather than produced through the payment API. Each test seeds against its own
 * dynamic aid (txids derived from the aid) so the suite stays isolated.
 *
 * Fixture per aid (base = '9'+padded aid):
 *   base.'005' tc rec debt 10, paid='2', pending_covering_amount 5,
 *               paid_by=[approved 5, pending 5], waiting_payments=[pending]
 *   base.'006' fc rec approved cash payment 5 (pays the debt)
 *   base.'007' fc rec PENDING automatic gateway payment 5 (pays the debt, pending)
 */
class SwitchLinksTest extends \Codeception\Test\Unit
{
    /** @var \AcceptanceTester */
    protected $tester;

    const CARD_OK = '1022273188555888';

    protected function _before()
    {
        $this->tester->setTimezone('Asia/Jerusalem');
        $this->tester->enableCreditGuardPGWithSettings();
        $this->tester->overrideConfigValue('taxation.default', ['key' => 'DEFAULT_VAT']); // 17% VAT
        $this->tester->cleanDB();
        $this->tester->seedCallRate();
    }

    protected function _after()
    {
        $this->tester->restoreTimezone();
    }

    // ===== switch_links = TRUE: the pending link is detached, the real payment re-links =====

    // case 87 - offline (fc) cash payment of 5
    public function testSwitchLinksOfflinePayment()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', true);

        $this->tester->payOffline($aid, 5, 'fc', 'cash');

        $this->assertPendingDisplaced($aid, $base);
    }

    // case 90 - online charge via chargeAccount. chargeAccount only charges invoices
    // with left_to_pay > 0; the seeded invoice is already fully covered (by the
    // approved + pending payments), so nothing is charged and there is no new real
    // payment to re-link — the pending link therefore stays even with switch_links on
    // (this matches the legacy expectation; contrast the offline/onetimeinvoice cases
    // which DO bring a new real payment).
    public function testSwitchLinksChargeAccount()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', true);

        $resp = $this->tester->chargeAccountApi(['aids' => "$aid"]);
        $this->assertEmpty($resp['details']['responses']); // nothing chargeable

        $this->assertPendingKept($aid, $base);
    }

    // case 91 - new immediate invoice (onetimeinvoice) charge of 5 (aprice 4.27 + VAT)
    public function testSwitchLinksOnetimeInvoice()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', true);

        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 4.27)], ['step' => 2, 'invoice_unixtime' => 'past']);

        $this->assertPendingDisplaced($aid, $base);
    }

    // case 92 - new charge smaller than the pending portion (aprice 2 + VAT = 2.34)
    public function testSwitchLinksPartialApprovedAndPending()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', true);

        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 2)], ['step' => 2, 'invoice_unixtime' => 'past']);

        // the small real payment displaces only part of the pending coverage
        $inv = $this->tester->grabBill(['aid' => $aid, 'txid' => $base . '005']);
        $this->assertGreaterThan(0, $inv['pending_covering_amount']);
        $this->assertLessThan(5, $inv['pending_covering_amount']);
    }

    // case 93 - new charge larger than the pending portion (aprice 10 + VAT = 11.7)
    public function testSwitchLinksPartialPendingOverflow()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', true);

        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 10)], ['step' => 2, 'invoice_unixtime' => 'past']);

        $this->assertPendingDisplaced($aid, $base);
    }

    // ===== switch_links = FALSE: the pending link stays in place =====

    // case 94 - chargeAccount with switch_links off
    public function testNoSwitchLinksChargeAccount()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', false);

        $this->tester->chargeAccountApi(['aids' => "$aid"]);

        $this->assertPendingKept($aid, $base);
    }

    // case 95 - onetimeinvoice with switch_links off
    public function testNoSwitchLinksOnetimeInvoice()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', false);

        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 4.27)], ['step' => 2, 'invoice_unixtime' => 'past']);

        $this->assertPendingKept($aid, $base);
    }

    // case 96 - onetimeinvoice (small) with switch_links off
    public function testNoSwitchLinksPartialApprovedAndPending()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', false);

        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 2)], ['step' => 2, 'invoice_unixtime' => 'past']);

        $this->assertPendingKept($aid, $base);
    }

    // case 97 - onetimeinvoice (large) with switch_links off
    public function testNoSwitchLinksPartialPendingOverflow()
    {
        $aid = $this->newAccount();
        $base = $this->tester->seedSwitchLinksFixture($aid);
        $this->tester->overrideConfigValue('bills.switch_links', false);

        $this->tester->onetimeInvoice($aid, [$this->cdr($aid, 10)], ['step' => 2, 'invoice_unixtime' => 'past']);

        $this->assertPendingKept($aid, $base);
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
            'type' => 'credit', 'credit_time' => '2021-03-18T17:18:56Z', 'aprice' => $aprice,
        ];
    }

    /** switch_links=true: the pending coverage is fully displaced by the real payment. */
    private function assertPendingDisplaced($aid, $base)
    {
        $inv = $base . '005';
        // the approved payment is still linked; the pending coverage is gone
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'txid' => $inv, 'paid_by.0.id' => $base . '006', 'paid_by.0.amount' => 5,
            'pending_covering_amount' => 0,
        ]);
        $this->tester->verifyCollectionCount('bills', 0, [
            'aid' => $aid, 'txid' => $inv, 'paid_by' => ['$elemMatch' => ['pending' => true]],
        ]);
    }

    /** switch_links=false: the pending coverage remains on the invoice. */
    private function assertPendingKept($aid, $base)
    {
        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $aid, 'txid' => $base . '005', 'pending_covering_amount' => 5,
            'paid_by' => ['$elemMatch' => ['id' => $base . '007', 'pending' => true]],
        ]);
    }
}
