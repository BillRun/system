<?php

/**
 * DB-backed coverage for the exchange-rate conversion engine (BRCD-2711).
 *
 * Verifies the full round-trip: a stored exchange-rate revision is read back and
 * applied by Billrun_CurrencyConvert_Manager::convert(). This underpins the
 * usage-line, cycle and plan/service currency conversions.
 *
 * Uses the synthetic ISO test currency code "XTS" as the target so real rate
 * data (USD/EUR/ILS) is never touched.
 */
class ExchangeRateConversionTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    const BASE = 'USD';
    const TARGET = 'XTS'; // ISO 4217 "for testing" code
    const RATE = 0.5;

    protected function _before()
    {
        $this->cleanup();
        // store a rate revision covering "now"
        $exchangeRate = new Billrun_ExchangeRate(self::BASE, self::TARGET, self::RATE, time());
        $exchangeRate->save();
    }

    protected function _after()
    {
        $this->cleanup();
    }

    protected function cleanup()
    {
        Billrun_Factory::db()->exchangeratesCollection()->remove([
            'base_currency' => self::BASE,
            'target_currency' => self::TARGET,
        ]);
    }

    // A stored rate is read back and applied to the amount.
    public function testConvertUsesStoredRate()
    {
        $converted = Billrun_CurrencyConvert_Manager::convert(self::BASE, self::TARGET, 100);
        $this->assertEquals(100 * self::RATE, $converted);
    }

    // Converting to the base currency itself with no stored rate returns false (no rate).
    public function testConvertWithoutRateReturnsFalse()
    {
        $converted = Billrun_CurrencyConvert_Manager::convert(self::BASE, 'XBT', 100);
        $this->assertFalse($converted);
    }

    // Reading the rate entity directly returns the stored value.
    public function testExchangeRateGetRate()
    {
        $rate = new Billrun_ExchangeRate(self::BASE, self::TARGET, null, time());
        $this->assertEquals(self::RATE, $rate->getRate());
    }
}
