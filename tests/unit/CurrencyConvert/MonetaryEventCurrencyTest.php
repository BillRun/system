<?php

/**
 * Coverage for the monetary-event currency guard (BRCD-2722).
 *
 * In multi-currency phase 1, monetary events (whose condition path targets a cost
 * figure, e.g. balance.cost or balance.groups.<group>.cost) must fire only for the
 * system default currency. A cost threshold is configured in the default currency,
 * so evaluating it against an account billed in a converted currency would compare
 * against the wrong amount.
 *
 * This exercises the pure, config-independent decision
 * Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency(); the surrounding
 * trigger() wiring and the isMultiCurrencyEnabled() config gate are covered by the
 * Mongo-backed integration checks described in the roadmap.
 */
class MonetaryEventCurrencyTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    // A non-monetary (usage) path is never skipped, whatever the currency.
    public function testUsagePathNeverSkipped()
    {
        $this->assertFalse(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('balance.groups.A.usagev', 'EUR', 'USD'));
        $this->assertFalse(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('balance.totals.sms.usagev', 'EUR', 'USD'));
    }

    // A monetary (cost) path is skipped when the account currency is not the default.
    public function testMonetaryPathSkippedForNonDefaultCurrency()
    {
        $this->assertTrue(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('balance.cost', 'EUR', 'USD'));
        $this->assertTrue(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('balance.groups.TY.cost', 'EUR', 'USD'));
    }

    // A monetary path is kept (fires) when the account is billed in the default currency.
    public function testMonetaryPathKeptForDefaultCurrency()
    {
        $this->assertFalse(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('balance.cost', 'USD', 'USD'));
        $this->assertFalse(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('balance.groups.TY.cost', 'USD', 'USD'));
    }

    // With no resolvable currency the event is kept (no skip) - default behaviour.
    public function testMonetaryPathKeptWhenCurrencyUnknown()
    {
        $this->assertFalse(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('balance.cost', '', 'USD'));
    }

    // An empty / missing path is treated as non-monetary.
    public function testEmptyPathNotMonetary()
    {
        $this->assertFalse(Billrun_EventsManager::isMonetaryPathOnNonDefaultCurrency('', 'EUR', 'USD'));
    }
}
