<?php

/**
 * Unit coverage for multi-currency price conversion logic (BRCD-2711).
 *
 * Focuses on the deterministic, config/DB-independent parts of the conversion
 * engine: per-currency price overrides on a rate step (spec conversion modes
 * "constant value" and "rate markup") and customer currency resolution.
 *
 * The exchange-rate DB lookup (Billrun_CurrencyConvert_Base::convert) and the
 * config-save flow (Config::updateExchangeRates / checkRemovedCurrencies) are
 * covered by the integration checks described in the roadmap, as they require
 * the Mongo-backed docker test environment.
 */
class CurrencyConvertTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * Build a rate step carrying the given per-currency overrides.
     */
    protected function buildStep(array $currencyRates = [], $price = 10)
    {
        return new Billrun_Rate_Step([
            'from' => 0,
            'to' => 'UNLIMITED',
            'interval' => 1,
            'price' => $price,
            'currency_rates' => $currencyRates,
        ]);
    }

    /**
     * Expose the protected override resolver for testing.
     */
    protected function callOverride(Billrun_Rate_Step $step, $price, $targetCurrency)
    {
        $converter = new Billrun_CurrencyConvert_Base();
        $method = new ReflectionMethod(Billrun_CurrencyConvert_Base::class, 'getOverrideCurrencyPrice');
        $method->setAccessible(true);
        return $method->invoke($converter, $step, $price, $targetCurrency);
    }

    // Mode 3: user-defined constant price for the currency.
    public function testOverrideWithConstantValue()
    {
        $step = $this->buildStep([['currency' => 'EUR', 'value' => 9.99]]);
        $this->assertSame(9.99, $this->callOverride($step, 10, 'EUR'));
    }

    // Mode 2: base price multiplied by a per-currency rate (markup).
    public function testOverrideWithRateMultiplier()
    {
        $step = $this->buildStep([['currency' => 'EUR', 'rate' => 1.2]]);
        $this->assertSame(12.0, $this->callOverride($step, 10, 'EUR'));
    }

    // Constant value takes precedence over rate when both are present.
    public function testConstantValueWinsOverRate()
    {
        $step = $this->buildStep([['currency' => 'EUR', 'value' => 7.5, 'rate' => 1.2]]);
        $this->assertSame(7.5, $this->callOverride($step, 10, 'EUR'));
    }

    // No override entry for the requested currency -> caller falls back to conversion.
    public function testNoOverrideForUnlistedCurrency()
    {
        $step = $this->buildStep([['currency' => 'ILS', 'value' => 5]]);
        $this->assertFalse($this->callOverride($step, 10, 'EUR'));
    }

    // Empty currency_rates -> no override.
    public function testNoOverrideWhenEmpty()
    {
        $step = $this->buildStep([]);
        $this->assertFalse($this->callOverride($step, 10, 'EUR'));
    }

    // Matching currency entry without value/rate -> no usable override.
    public function testMatchingCurrencyWithoutValueOrRate()
    {
        $step = $this->buildStep([['currency' => 'EUR']]);
        $this->assertFalse($this->callOverride($step, 10, 'EUR'));
    }

    // Customer with an explicit currency keeps it (account-level currency).
    public function testCustomerCurrencyExplicit()
    {
        $this->assertSame('EUR', Billrun_CurrencyConvert_Manager::getCustomerCurrency(['currency' => 'EUR']));
    }

    // Customer without a currency falls back to the system default currency.
    public function testCustomerCurrencyFallsBackToDefault()
    {
        $this->assertSame(
            Billrun_CurrencyConvert_Manager::getDefaultCurrency(),
            Billrun_CurrencyConvert_Manager::getCustomerCurrency([])
        );
    }

    // BRCD-2715: plan/service percentage override picks the per-currency percentage.
    public function testPlanServiceOverridePercentagePerCurrency()
    {
        $tariff = [
            'percentage' => 100,
            'currency_rates' => [
                ['currency' => 'EUR', 'percentage' => 90],
                ['currency' => 'ILS', 'percentage' => 80],
            ],
        ];
        $this->assertSame(90.0, Billrun_Rate_Tariff::pickCurrencyPercentage($tariff, 'EUR'));
        $this->assertSame(80.0, Billrun_Rate_Tariff::pickCurrencyPercentage($tariff, 'ILS'));
    }

    // Falls back to the default percentage when the currency has no specific override.
    public function testPlanServiceOverridePercentageFallback()
    {
        $tariff = [
            'percentage' => 100,
            'currency_rates' => [['currency' => 'EUR', 'percentage' => 90]],
        ];
        // currency not listed -> default percentage
        $this->assertSame(100.0, Billrun_Rate_Tariff::pickCurrencyPercentage($tariff, 'GBP'));
        // default currency ('') -> default percentage
        $this->assertSame(100.0, Billrun_Rate_Tariff::pickCurrencyPercentage($tariff, ''));
        // no currency_rates at all -> default percentage
        $this->assertSame(100.0, Billrun_Rate_Tariff::pickCurrencyPercentage(['percentage' => 100], 'EUR'));
    }
}
