<?php

/**
 * Shared fixture for the payment API tests (tests/all/payment/*Test.php, converted from
 * the legacy library/Tests/Paymenttest.php).
 *
 * _before: snapshot the current config revision, pin the tenant timezone, enable the
 * mockup CreditGuard gateway, optionally pin VAT / clear bills / seed the CALL rate, and
 * wipe the test collections. _after: re-insert the snapshot as the newest config revision,
 * so every override a test made (timezone, gateway, taxation, switch_links, uf whitelist)
 * is undone and no configuration state leaks into the suites that run afterwards.
 */
abstract class AbstractPaymentCase extends \Codeception\Test\Unit
{
    /** @var \AcceptanceTester */
    protected $tester;

    /** mockup CreditGuard card tokens: 16 chars -> approved (000), 10 chars -> rejected (033) */
    const CARD_OK = '1022273188555888';
    const CARD_REJECT = '1022273188';
    /** credit_time of onetime-invoice CDRs; any date covered by DEFAULT_VAT works */
    const CREDIT_TIME = '2021-04-18T17:17:56Z';
    /** the legacy expected amounts/dates were computed in this timezone */
    const TIMEZONE = 'Asia/Jerusalem';

    /** pin taxation.default to DEFAULT_VAT (17%) so invoice amounts are deterministic */
    protected $pinVat = false;
    /** seed the CALL rate referenced by onetime-invoice CDRs (cleanDB wipes rates) */
    protected $seedCallRate = false;
    /** also wipe the bills collection (cleanDB leaves it) - for tests doing global charge sweeps */
    protected $clearBills = false;
    /** distinct invoice timestamps so same-account invoices don't share a billrun stamp */
    protected $invoiceSeq = 0;

    /** @var array|null raw config revision to re-insert in _after */
    private $configSnapshot;

    protected function _before()
    {
        $this->configSnapshot = $this->tester->snapshotConfig();
        $this->tester->setTimezone(self::TIMEZONE);
        $this->tester->enableCreditGuardPGWithSettings();
        if ($this->pinVat) {
            $this->tester->overrideConfigValue('taxation.default', ['key' => 'DEFAULT_VAT']);
        }
        $this->tester->cleanDB();
        if ($this->clearBills) {
            $this->tester->clearBills();
        }
        if ($this->seedCallRate) {
            $this->tester->seedCallRate();
        }
        $this->invoiceSeq = 0;
    }

    protected function _after()
    {
        $this->tester->restoreTimezone();
        if ($this->configSnapshot) {
            $this->tester->restoreConfig($this->configSnapshot);
            $this->configSnapshot = null;
        }
    }

    /** create an account with the mockup CreditGuard card, return its (int) aid */
    protected function newAccount($card = self::CARD_OK)
    {
        return (int) $this->tester->createAccountWithCard($card)['aid'];
    }

    /** build a single pre-priced credit CDR for an immediate invoice of $aprice (before VAT) */
    protected function cdr($aid, $aprice, $creditTime = null)
    {
        return [
            'aid' => $aid, 'sid' => 0, 'rate' => 'CALL', 'usagev' => 1,
            'type' => 'credit', 'credit_time' => $creditTime ?: static::CREDIT_TIME, 'aprice' => $aprice,
        ];
    }
}
