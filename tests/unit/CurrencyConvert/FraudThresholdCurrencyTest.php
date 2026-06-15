<?php

/**
 * Coverage for currency normalization of monetary fraud/event thresholds (BRCD-2722).
 *
 * In multi-currency mode, monetary thresholds must be summed in the default currency
 * (via the per-line original_currency snapshot) so thresholds defined in the default
 * currency are not compared against customer-currency amounts.
 */
class FraudThresholdCurrencyTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    // Non-monetary threshold is summed as-is regardless of currency mode.
    public function testUsagevNotNormalized()
    {
        $this->assertSame('$usagev', Billrun_FraudManager::buildThresholdSumExpression('usagev', true));
        $this->assertSame('$usagev', Billrun_FraudManager::buildThresholdSumExpression('usagev', false));
    }

    // Monetary threshold (aprice) is normalized to default currency only in multi-currency mode.
    public function testApriceNormalizedOnlyInMultiCurrency()
    {
        $this->assertSame('$aprice', Billrun_FraudManager::buildThresholdSumExpression('aprice', false));
        $this->assertSame(
            ['$ifNull' => ['$original_currency.aprice', '$aprice']],
            Billrun_FraudManager::buildThresholdSumExpression('aprice', true)
        );
    }

    // Other monetary thresholds also use the original_currency snapshot with fallback.
    public function testFinalChargeNormalizedInMultiCurrency()
    {
        $this->assertSame(
            ['$ifNull' => ['$original_currency.final_charge', '$final_charge']],
            Billrun_FraudManager::buildThresholdSumExpression('final_charge', true)
        );
    }
}
