<?php

/**
 * Coverage for disabling monetary discounts under multi-currency (BRCD-2720).
 *
 * In multi-currency phase 1, monetary (fixed-amount) discounts cannot be applied
 * correctly across currencies - the amount is defined in the system default currency.
 * They are therefore skipped at CDR generation when multi-currency is enabled, while
 * percentage discounts and monetary CHARGES are unaffected.
 *
 * Exercises the pure, config-independent decision
 * Billrun_DiscountManager::isMonetaryDiscountDisabled(); the surrounding cycle/DB
 * application is covered by DiscountTest + integration checks.
 */
class MonetaryDiscountCurrencyTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    // A monetary discount is skipped only when multi-currency is enabled.
    public function testMonetaryDiscountSkippedOnlyUnderMultiCurrency()
    {
        $discount = ['key' => 'D1', 'type' => 'monetary'];
        $this->assertTrue(Billrun_DiscountManager::isMonetaryDiscountDisabled('discounts', $discount, true));
        $this->assertFalse(Billrun_DiscountManager::isMonetaryDiscountDisabled('discounts', $discount, false));
    }

    // Percentage discounts are never skipped.
    public function testPercentageDiscountNeverSkipped()
    {
        $discount = ['key' => 'D2', 'type' => 'percentage'];
        $this->assertFalse(Billrun_DiscountManager::isMonetaryDiscountDisabled('discounts', $discount, true));
        // missing type defaults to percentage
        $this->assertFalse(Billrun_DiscountManager::isMonetaryDiscountDisabled('discounts', ['key' => 'D3'], true));
    }

    // Monetary CHARGES are legitimate and must never be skipped.
    public function testMonetaryChargeNeverSkipped()
    {
        $charge = ['key' => 'C1', 'type' => 'monetary'];
        $this->assertFalse(Billrun_DiscountManager::isMonetaryDiscountDisabled('charge', $charge, true));
    }
}
