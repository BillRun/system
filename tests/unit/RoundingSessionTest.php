<?php

/**
 * BRCD-5574: a partial charge of a session is rounded against the whole session and charged the delta
 * from the unified line, so the legs sum up to the one-shot charge. Pure array tests, no DB or config.
 */
class RoundingSessionTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    const VAT = 0.081;
    const UP = ['rounding_type' => 'up', 'rounding_decimals' => 0, 'rounding_stage' => 'after_tax'];
    const UP_BEFORE_TAX = ['rounding_type' => 'up', 'rounding_decimals' => 0, 'rounding_stage' => 'before_tax'];

    /** a priced + taxed line with the given final_charge, before rounding */
    protected function line($finalCharge, $rules, $session = [])
    {
        $aprice = $finalCharge / (1 + self::VAT);
        $tax = $finalCharge - $aprice;
        return array_merge([
            'stamp' => 'test',
            'aprice' => $aprice,
            'final_charge' => $finalCharge,
            'tax_data' => [
                'total_amount' => $tax,
                'total_tax' => self::VAT,
                'taxes' => [['tax' => self::VAT, 'amount' => $tax, 'key' => 'DEFAULT_VAT']],
            ],
            'rounding_rules' => $rules,
        ], $session);
    }

    /** the session fields the plugin sets from the unified line: charged so far, and before rounding */
    protected function session($chargedFinalCharge, $beforeRoundingFinalCharge)
    {
        return [
            'session_final_charge' => $chargedFinalCharge,
            'session_before_rounding_final_charge' => $beforeRoundingFinalCharge,
        ];
    }

    protected function roundFinalCharge($line)
    {
        $calc = new RoundingSessionTestCalculator();
        $calc->roundFinalCharge($line);
        return $line;
    }

    protected function roundAprice($line)
    {
        $calc = new RoundingSessionTestCalculator();
        $calc->roundAprice($line);
        return $line;
    }

    protected function assertCharge($expectedFinalCharge, $line, $message = '')
    {
        $this->assertEqualsWithDelta($expectedFinalCharge, $line['final_charge'], 1e-9, "final_charge $message");
        $this->assertEqualsWithDelta($expectedFinalCharge / (1 + self::VAT), $line['aprice'], 1e-9, "aprice $message");
        $this->assertEqualsWithDelta($line['final_charge'] - $line['aprice'], $line['tax_data']['total_amount'], 1e-9, "tax amount $message");
        $this->assertEqualsWithDelta($line['tax_data']['total_amount'], $line['tax_data']['taxes'][0]['amount'], 1e-9, "tax entry amount $message");
    }

    // ---------------------------------------------------------------- after tax (roundingFinalCharge)

    public function testFirstLegRoundsLikeOneShot()
    {
        $line = $this->roundFinalCharge($this->line(0.30, self::UP, $this->session(0, 0)));
        $this->assertCharge(1.0, $line);
    }

    /** 0.30 charged so far as 1.00, this leg 0.30 more: 0.60 still rounds up to 1.00 -> charged 0 */
    public function testLegInsideRoundedTotalIsChargedZero()
    {
        $line = $this->roundFinalCharge($this->line(0.30, self::UP, $this->session(1.0, 0.30)));
        $this->assertCharge(0.0, $line, 'of a leg already covered by the rounded session total');
        $this->assertEqualsWithDelta(0.30, $line['before_rounding']['final_charge'], 1e-9, 'exact charge of the leg is kept for the unified line');
    }

    /** 0.90 charged so far as 1.00, this leg 0.60 more: 1.50 rounds up to 2.00 -> charged 1.00 */
    public function testLegCrossingRoundedTotalIsChargedTheStep()
    {
        $line = $this->roundFinalCharge($this->line(0.60, self::UP, $this->session(1.0, 0.90)));
        $this->assertCharge(1.0, $line);
    }

    public function testLegsSumUpToOneShotCharge()
    {
        $legs = [0.60, 0.30, 0.60];
        $charged = 0;
        $before = 0;
        foreach ($legs as $leg) {
            $line = $this->roundFinalCharge($this->line($leg, self::UP, $this->session($charged, $before)));
            $charged += $line['final_charge'];
            $before += $leg;
        }
        $oneShot = $this->roundFinalCharge($this->line(array_sum($legs), self::UP));
        $this->assertEqualsWithDelta($oneShot['final_charge'], $charged, 1e-9, 'session legs sum up to the one-shot charge');
        $this->assertEqualsWithDelta(2.0, $charged, 1e-9);
    }

    public function testZeroLegIsSkipped()
    {
        $line = $this->roundFinalCharge($this->line(0, self::UP, $this->session(1.0, 0.30)));
        $this->assertEqualsWithDelta(0, $line['final_charge'], 1e-9);
        $this->assertArrayNotHasKey('before_rounding', $line);
    }

    // ---------------------------------------------------------------- before tax (roundingAprice)

    /** same delta logic before tax, on aprice, with the session_aprice pair */
    public function testApriceSessionRounding()
    {
        $inside = $this->roundAprice(['stamp' => 'test', 'aprice' => 0.30, 'rounding_rules' => self::UP_BEFORE_TAX, 'session_aprice' => 1.0, 'session_before_rounding_aprice' => 0.30]);
        $this->assertEqualsWithDelta(0.0, $inside['aprice'], 1e-9, 'leg inside the rounded session total');
        $this->assertEqualsWithDelta(0.30, $inside['before_rounding']['aprice'], 1e-9, 'exact charge of the leg is kept');

        $crossing = $this->roundAprice(['stamp' => 'test', 'aprice' => 0.60, 'rounding_rules' => self::UP_BEFORE_TAX, 'session_aprice' => 1.0, 'session_before_rounding_aprice' => 0.90]);
        $this->assertEqualsWithDelta(1.0, $crossing['aprice'], 1e-9, 'leg crossing the rounded session total');
    }

}

/** exposes the protected rounding methods */
class RoundingSessionTestCalculator extends Billrun_Calculator_Tax_Usage
{
    public function __construct()
    {
    }

    public function roundFinalCharge(&$row)
    {
        $this->roundingFinalCharge($row);
    }

    public function roundAprice(&$row)
    {
        $this->roundingAprice($row);
    }
}
