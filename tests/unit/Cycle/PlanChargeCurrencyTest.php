<?php

/**
 * Coverage for routing plan/service (flat) charges through the currency-aware
 * Billrun_Plans_Step::getRelativePrice() (BRCD-2714/2715).
 *
 * The plan charge classes were switched from the deprecated
 * Billrun_Plan::getPriceByTariff() to Billrun_Plans_Step::getRelativePrice($currency).
 * This test locks the contract the charge classes rely on:
 *   1. With no target currency the resolved price is identical to the legacy
 *      getPriceByTariff() price (no regression for default-currency accounts).
 *   2. getRelativePrice() always exposes 'price' and 'orig_price'; with no
 *      conversion the two are equal (orig_price is the default-currency value
 *      recorded under original_currency when billing a non-default currency).
 *
 * The actual per-currency override / exchange-rate conversion is exercised by
 * CurrencyConvertTest (override resolution) and the Mongo-backed integration
 * cycle checks for a non-default-currency account.
 */
class PlanChargeCurrencyTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function cases()
    {
        return [
            // [tariff, start, end, activation]
            'full_single_level'        => [['from' => 0, 'to' => 3, 'price' => 10], 0, 1, strtotime('2025-01-01')],
            'full_unlimited'           => [['from' => 0, 'to' => 'UNLIMITED', 'price' => 10], 3, 4, strtotime('2025-01-01')],
            'three_level_top'          => [['from' => 6, 'to' => '12', 'price' => 100], 6.5, 7.5, strtotime('2025-01-15')],
            'partial_month'            => [['from' => 0, 'to' => 'UNLIMITED', 'price' => 10], 0.9677419354838710, 1, strtotime('2025-01-31')],
        ];
    }

    // getRelativePrice without a currency must match the legacy getPriceByTariff price.
    public function testRelativePriceMatchesLegacyWhenNoCurrency()
    {
        foreach ($this->cases() as $name => $case) {
            list($tariff, $start, $end, $activation) = $case;
            $legacy = Billrun_Plan::getPriceByTariff($tariff, $start, $end, $activation);
            $step = new Billrun_Plans_Step($tariff);
            $relative = $step->getRelativePrice($start, $end, $activation, '');

            $this->assertEquals(
                $legacy['price'],
                $relative['price'],
                "price mismatch for {$name}"
            );
        }
    }

    // orig_price is always present and, without conversion, equals the charged price.
    public function testOrigPriceEqualsPriceWhenNoCurrency()
    {
        foreach ($this->cases() as $name => $case) {
            list($tariff, $start, $end, $activation) = $case;
            $step = new Billrun_Plans_Step($tariff);
            $relative = $step->getRelativePrice($start, $end, $activation, '');

            $this->assertArrayHasKey('price', $relative, "missing price for {$name}");
            $this->assertArrayHasKey('orig_price', $relative, "missing orig_price for {$name}");
            $this->assertEquals(
                $relative['price'],
                $relative['orig_price'],
                "orig_price should equal price without conversion for {$name}"
            );
        }
    }
}
