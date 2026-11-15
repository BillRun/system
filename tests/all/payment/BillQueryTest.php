<?php

require_once __DIR__ . '/AbstractPaymentCase.php';

/**
 * Bill-query cases (legacy Paymenttest cases 43-56): the /api/bill read actions
 * query_bills_invoices, get_balances, search_invoice, collection_debt and
 * all_collection_debts.
 *
 * The legacy cases keyed their assertions on fixed account ids and, for some,
 * relied on seeded past-due / rejection bills. Here accounts get dynamic ids and
 * all debt is created via the API, so balance/invoice queries are asserted
 * against the created account's id. Cases that genuinely require pre-existing
 * past-due + rejection fixtures (collection over an aged debt) are skipped with a
 * note — they belong to a collection-workflow suite, not payment.
 */
class BillQueryTest extends AbstractPaymentCase
{
    protected $pinVat = true;
    protected $seedCallRate = true;

    // case 43 - query all invoices of an account
    public function testQueryAllInvoices()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20); // due 23.4
        $this->invoice($aid, 30); // due 35.1
        $resp = $this->tester->billQuery('query_bills_invoices', ['query' => ['aid' => $aid]]);
        $dues = array_map(function ($b) { return round($b['due'], 2); }, $resp['details']);
        sort($dues);
        $this->assertEquals([23.4, 35.1], $dues);
    }

    // case 44 - query one specific invoice (by invoice_id)
    public function testQuerySpecificInvoice()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $this->invoice($aid, 30);
        $invoiceId = $this->tester->grabBill(['aid' => $aid, 'type' => 'inv', 'due_before_vat' => 20])['invoice_id'];
        $resp = $this->tester->billQuery('query_bills_invoices', ['query' => ['invoice_id' => $invoiceId]]);
        $this->assertCount(1, $resp['details']);
        $this->assertEqualsWithDelta(23.4, $resp['details'][0]['due'], 0.001);
    }

    // case 45 - get_balances of a balanced account (invoice + equal refund) -> 0
    public function testGetBalanceZero()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $this->invoice($aid, -20);
        $this->assertBalanceTotal($this->tester->billQuery('get_balances', ['aids' => $aid]), $aid, 0);
    }

    // case 46 - search_invoice of a balanced account -> balance.total 0
    public function testSearchInvoiceBalanceZero()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $this->invoice($aid, -20);
        $resp = $this->tester->billQuery('search_invoice', ['aid' => $aid]);
        $this->assertEqualsWithDelta(0, $resp['details']['balance']['total'], 0.001);
        $this->assertEqualsWithDelta(0, $resp['details']['balance']['total_pending_amount'], 0.001);
    }

    // case 47 - get_balances of an account with unpaid debt -> 23.4
    public function testGetBalanceUnpaidDebt()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $this->assertBalanceTotal($this->tester->billQuery('get_balances', ['aids' => $aid]), $aid, 23.4);
    }

    // case 48 - get_balances of an overpaid account -> -23.4
    public function testGetBalanceOverpaid()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, -20); // refund invoice -> credit
        $this->assertBalanceTotal($this->tester->billQuery('get_balances', ['aids' => $aid]), $aid, -23.4);
    }

    // case 49 - get_balances of 4 accounts in different states
    public function testGetBalancesMultipleAccounts()
    {
        $overpaid = $this->newAccount();
        $this->invoice($overpaid, -20);                       // -23.4
        $balanced = $this->newAccount();
        $this->invoice($balanced, 20);
        $this->invoice($balanced, -20);                       // 0
        $unpaid = $this->newAccount();
        $this->invoice($unpaid, 20);                          // 23.4
        $partial = $this->newAccount();
        $this->invoice($partial, 20);
        $this->tester->payOffline($partial, 10, 'fc', 'cash'); // 13.4 left

        $resp = $this->tester->billQuery('get_balances', ['aids' => "$overpaid,$balanced,$unpaid,$partial"]);
        $this->assertBalanceTotal($resp, $overpaid, -23.4);
        $this->assertBalanceTotal($resp, $balanced, 0);
        $this->assertBalanceTotal($resp, $unpaid, 23.4);
        $this->assertBalanceTotal($resp, $partial, 13.4);
    }

    // case 50 - debt with a future due date is NOT in collection
    public function testFutureDueDebtNotInCollection()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20); // fresh invoice -> due date in the future
        $resp = $this->tester->billQuery('collection_debt', ['aids' => json_encode([$aid])]);
        $this->assertArrayNotHasKey($aid, (array) ($resp['details'] ?? []));
    }

    // case 52 - same: not retrieved while due date is not yet past
    public function testCollectionDebtExcludesNonPastDue()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $resp = $this->tester->billQuery('collection_debt', ['aids' => json_encode([$aid])]);
        $this->assertArrayNotHasKey($aid, (array) ($resp['details'] ?? []));
    }

    // case 56 - all_collection_debts excludes an account whose debt is future-dated
    public function testAllCollectionDebtsExcludesFutureDue()
    {
        $aid = $this->newAccount();
        $this->invoice($aid, 20);
        $resp = $this->tester->billQuery('all_collection_debts');
        $this->assertArrayNotHasKey($aid, (array) ($resp['details'] ?? []));
    }

    // cases 51, 54, 55 - aged (past-due) debt enters collection. A debt is "in
    // collection" purely when its due_date < now AND it is unpaid (no separate
    // rejection/aging threshold: rejection_required is unset, so the debt counts
    // via the !paid && valid_due_date branch). An aged invoice is created with a
    // past invoice_unixtime, so its due_date (= billrun date + due_date_interval)
    // falls before now. NB: all_collection_debts scans the whole bills collection,
    // which cleanDB does NOT wipe, so assertions are scoped to this test's own aids.

    // case 51 - collection_debt of an account with an aged unpaid debt -> 23.4
    public function testCollectionDebtOverAgedDebt()
    {
        $aid = $this->newAccount();
        $this->agedInvoice($aid, 20); // 23.4, past-due
        $resp = $this->tester->billQuery('collection_debt', ['aids' => json_encode([$aid])]);
        $details = (array) ($resp['details'] ?? []);
        $this->assertArrayHasKey($aid, $details, 'aged debt should be in collection');
        $this->assertEqualsWithDelta(23.4, $details[$aid]['total'], 0.001);
        $this->assertEquals($aid, $details[$aid]['aid']);
    }

    // case 54 - all_collection_debts returns each account's aged debt (full + partial)
    public function testAllCollectionDebtsOverAgedDebt()
    {
        $aidFull = $this->newAccount();
        $this->agedInvoice($aidFull, 20);                        // 23.4 fully unpaid
        $aidPartial = $this->newAccount();
        $this->agedInvoice($aidPartial, 20);                     // 23.4 ...
        $this->tester->payOffline($aidPartial, 11.7, 'fc', 'cash'); // ... 11.7 paid -> 11.7 left

        $resp = $this->tester->billQuery('all_collection_debts');
        $details = (array) ($resp['details'] ?? []);
        $this->assertArrayHasKey($aidFull, $details);
        $this->assertEqualsWithDelta(23.4, $details[$aidFull]['total'], 0.001);
        $this->assertArrayHasKey($aidPartial, $details);
        $this->assertEqualsWithDelta(11.7, $details[$aidPartial]['total'], 0.001);
    }

    // case 55 - all_collection_debts includes the debtor, excludes the net-credit account
    public function testAllCollectionDebtsMixedDebtAndCredit()
    {
        $aidDebt = $this->newAccount();
        $this->agedInvoice($aidDebt, 20);   // 23.4 debt -> present
        $aidCredit = $this->newAccount();
        $this->agedInvoice($aidCredit, -20); // net credit -> filtered out (only debts)

        $resp = $this->tester->billQuery('all_collection_debts');
        $details = (array) ($resp['details'] ?? []);
        $this->assertArrayHasKey($aidDebt, $details);
        $this->assertEqualsWithDelta(23.4, $details[$aidDebt]['total'], 0.001);
        $this->assertArrayNotHasKey($aidCredit, $details);
    }

    // ------------------------------------------------------------------

    /** create an immediate invoice of $aprice (pre-VAT); negative => refund/credit */
    private function invoice($aid, $aprice)
    {
        $this->tester->onetimeInvoice($aid, [[
            'aid' => $aid, 'sid' => 0, 'rate' => 'CALL', 'usagev' => 1,
            'type' => 'credit', 'credit_time' => '2021-04-18T17:17:56Z', 'aprice' => $aprice,
        ]], ['step' => 1, 'invoice_unixtime' => time() - ($this->invoiceSeq++ * 3)]);
    }

    /** create an aged (past-due) immediate invoice so it enters collection */
    private function agedInvoice($aid, $aprice)
    {
        $unixtime = time() - 2592000 - ($this->invoiceSeq++ * 86400); // ~30+ days ago, distinct days
        $this->tester->onetimeInvoice($aid, [[
            'aid' => $aid, 'sid' => 0, 'rate' => 'CALL', 'usagev' => 1,
            'type' => 'credit', 'credit_time' => '2021-04-18T17:17:56Z', 'aprice' => $aprice,
        ]], ['step' => 1, 'invoice_unixtime' => $unixtime]);
    }

    private function assertBalanceTotal($resp, $aid, $expected)
    {
        $details = (array) ($resp['details'] ?? []);
        $this->assertArrayHasKey($aid, $details, 'balance present for aid ' . $aid);
        $this->assertEqualsWithDelta($expected, $details[$aid]['total'], 0.001);
    }
}
