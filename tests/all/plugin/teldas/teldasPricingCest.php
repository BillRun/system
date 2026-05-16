<?php

/**
 * teldasPricingCest
 *
 * Exercises teldasPlugin::pricingCdr for CDRs that hit a teldas INA number.
 *
 * The tests construct an in-memory $line that mirrors what an input processor
 * would produce for a call to a teldas number (line['type'] set to a synthetic
 * file_type, uf.Subscriber_Number, uf.Duration_Seconds, urt) and invoke the
 * protected pricingCdr() via reflection on a freshly enabled teldasPlugin.
 * Fixture documents are inserted into the teldas Mongo collections per-test
 * (and torn down in _after) so each scenario is hermetic.
 *
 * The input-processor name and field paths used here are intentionally
 * synthetic ("TeldasTestInput", "uf.Subscriber_Number", "uf.Duration_Seconds")
 * so the tests don't depend on any specific real input-processor definition.
 *
 * Scenarios covered:
 *   1. INA-number revision matching (non-matching / terminated / converted
 *      prefix / out-of-revision-window)
 *   2. Online tariff profile pricing - single chargeConfiguration sequence
 *   3. Offline-a tariff profile pricing - weekday / saturday / sunday-holiday
 *      and FIX_PRICE drop-charge
 *   4. call_offset CDR-split behavior (BRCD-5294): baseCharge & startInterval
 *      apply only on the first CDR of a split call (online), and previously
 *      consumed capacity is skipped on offline-a multi-sequence.
 *   5. Production-reference smoke tests: two real prod CDR examples to
 *      0900530520 - synthetic tariffs are calibrated so the plugin reproduces
 *      the prod aprice values 13.876 and 3.191.
 */
class teldasPricingCest
{
    protected $vat = 0.081;
    protected $epsilon = 0.001;

    /** Synthetic input-processor / line_type name used in fixtures. */
    const TEST_LINE_TYPE = 'TeldasTestInput';

    /** Plugin options injected for every test. The matching_paths shape is the
     *  same as production; only the line_type / field paths are synthetic. */
    protected $pluginOptions = [
        'url'      => 'https://ws.test.numberportability.ch',
        'user'     => 'test',
        'password' => 'test',
        'ina_number_prefixes' => '/^(0800|0848|0900|0901|0906|0840|0842|0844|0878)|^18[0-9][0-9]$/',
        'matching_paths' => [
            [
                'line_type' => self::TEST_LINE_TYPE,
                'duration'  => [
                    'path'              => 'uf.Duration_Seconds',
                    'divide_to_seconds' => 1,
                ],
                'subscriber_number' => [
                    'path'       => 'uf.Subscriber_Number',
                    'conversion' => [
                        ['pattern' => '/^41(?=\\d{4}$)/',  'replacement' => ''],
                        ['pattern' => '/^41(?=\\d{5}+)/', 'replacement' => '0'],
                    ],
                ],
                'usage' => ['type' => 'ina_vas_call'],
            ],
        ],
        'update_online'    => true,
        'update_offline-a' => true,
    ];

    /** Collections cleared before each test. */
    protected $teldasCollections = [
        'plugin_teldas_ina_numbers',
        'plugin_teldas_tariffs_profiles',
        'plugin_teldas_tariff_switching_classes',
        'plugin_teldas_non_working_days',
    ];

    public function _before(AcceptanceTester $I)
    {
        $this->cleanTeldasCollections();
        $I->setTimezone('Europe/Zurich');
    }

    public function _after(AcceptanceTester $I)
    {
        $this->cleanTeldasCollections();
        $I->restoreTimezone();
    }

    protected function cleanTeldasCollections()
    {
        foreach ($this->teldasCollections as $name) {
            $collection = \Billrun_Factory::db()->getCollection($name);
            if ($collection) {
                $collection->remove(['_id' => ['$exists' => true]]);
            }
        }
    }

    /* ---------- fixture builders ---------- */

    /** UTCDateTime helper for fixture documents. */
    protected function bsonDate($strOrTs)
    {
        $ts = is_string($strOrTs) ? strtotime($strOrTs) : (int) $strOrTs;
        return new \MongoDB\BSON\UTCDateTime($ts * 1000);
    }

    /**
     * Insert an INA number revision. Defaults to an open-ended, currently-valid
     * window matching activationDatetime < urt < terminationDatetime.
     */
    protected function insertInaNumber(AcceptanceTester $I, $subscriberNumber, $tariffProfileId, array $override = [])
    {
        $doc = array_merge([
            'subscriberNumber'        => $subscriberNumber,
            'status'                  => 'SRVIN',
            'tspId'                   => 98010,
            'tariffProfile'           => $tariffProfileId,
            'tariffProfileType'       => $tariffProfileId >= 10000 ? 'ONLINE' : 'OFFLINE_A',
            'accessAbroad'            => false,
            'activationDatetime'      => '2010-01-01T00:00:00',
            'expirationDatetime'      => null,
            'modificationDatetime'    => '2010-01-01T00:00:00',
            'terminationDatetime'     => null,
            'transactionDatetime'     => $this->bsonDate('2010-01-01 00:00:00'),
            'transactionDatetimeTo'   => null,
            'modifyPending'           => false,
        ], $override);
        $I->haveInCollection('plugin_teldas_ina_numbers', $doc);
    }

    /** Insert an ONLINE tariff profile with a single chargeConfiguration sequence. */
    protected function insertOnlineTariffProfileSingleSequence(
        AcceptanceTester $I,
        $id,
        array $sequence,
        array $override = []
    ) {
        $doc = array_merge([
            'tspId'                  => 98010,
            'tariffProfileType'      => 'ONLINE',
            'id'                     => $id,
            'industryStandard'       => false,
            'serviceRate'            => 'PRS',
            'validDateTimeFrom'      => '2000-01-01T00:00:00',
            'validDateTimeTo'        => null,
            'transactionDateTime'    => $this->bsonDate('2000-01-01 00:00:00'),
            'transactionDateTimeTo'  => null,
            'tariffSwitchingClassId' => 1,
            'chargeConfigurations'   => [array_merge([
                'sequence'      => 1,
                'chargeRate'    => 0,
                'timeBetweenPulse' => null,
                'startInterval' => 0,
                'baseCharge'    => 0,
            ], $sequence)],
        ], $override);
        $I->haveInCollection('plugin_teldas_tariffs_profiles', $doc);
    }

    /** Insert an OFFLINE_A tariff profile with explicit weekday/saturday/sunday configs. */
    protected function insertOfflineATariffProfile(
        AcceptanceTester $I,
        $id,
        array $weekday,
        array $saturday = null,
        array $sundayAndHoliday = null,
        array $override = []
    ) {
        if ($saturday === null) {
            $saturday = $weekday;
        }
        if ($sundayAndHoliday === null) {
            $sundayAndHoliday = $weekday;
        }
        $doc = array_merge([
            'tspId'                 => 98010,
            'tariffProfileType'     => 'OFFLINE_A',
            'id'                    => $id,
            'industryStandard'      => false,
            'serviceRate'           => 'PRS',
            'validDateTimeFrom'     => '2000-01-01T00:00:00',
            'validDateTimeTo'       => null,
            'transactionDateTime'   => $this->bsonDate('2000-01-01 00:00:00'),
            'transactionDateTimeTo' => null,
            'weekChargeConfiguration' => [
                'weekday'          => $weekday,
                'saturday'         => $saturday,
                'sundayAndHoliday' => $sundayAndHoliday,
            ],
        ], $override);
        $I->haveInCollection('plugin_teldas_tariffs_profiles', $doc);
    }

    /* ---------- line builder ---------- */

    /**
     * Build a synthetic teldas-input CDR line.
     *
     * @param string $subscriberNumber  value at uf.Subscriber_Number (pre-conversion)
     * @param int    $durationSeconds   uf.Duration_Seconds
     * @param string $urtString         strtotime-compatible call-segment start
     * @param int    $callOffsetSeconds seconds elapsed before this segment (same unit as duration)
     */
    protected function makeLine($subscriberNumber, $durationSeconds, $urtString, $callOffsetSeconds = 0)
    {
        return [
            'type'        => self::TEST_LINE_TYPE,
            'usaget'      => 'ina_vas_call',
            'stamp'       => 'teldas-test-' . md5($subscriberNumber . $urtString . $durationSeconds . $callOffsetSeconds),
            'urt'         => new \MongoDate(strtotime($urtString)),
            'call_offset' => $callOffsetSeconds,
            'uf'          => [
                'Subscriber_Number' => $subscriberNumber,
                'Duration_Seconds'  => $durationSeconds,
            ],
        ];
    }

    /* ---------- pricing invocation via reflection ---------- */

    protected function priceLine($line)
    {
        $plugin = new \teldasPlugin($this->pluginOptions);
        $method = new \ReflectionMethod($plugin, 'pricingCdr');
        $method->setAccessible(true);
        return $method->invoke($plugin, $line);
    }

    /* ============================================================
     *  INA-number matching tests
     * ============================================================ */

    public function testInaMatching_noMatchingSubscriberNumber_returnsFalse(AcceptanceTester $I)
    {
        $this->insertInaNumber($I, '0844111222', 10001);

        $line = $this->makeLine('0791234567', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertFalse($price, 'Pricing must fail when no INA revision matches the line subscriber number');
    }

    public function testInaMatching_terminatedNumber_returnsFalse(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10001, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10001, [
            'activationDatetime'  => '2020-01-01T00:00:00',
            'terminationDatetime' => '2025-01-01T00:00:00',
        ]);

        $line = $this->makeLine('0844111222', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertFalse($price, 'Terminated INA revisions must not be priced');
    }

    public function testInaMatching_numberConversionStripsLeading41(AcceptanceTester $I)
    {
        // Subscriber_Number "41844111222" (11 chars: "41" + 9 digits) is rewritten
        // to "0844111222" by the second conversion pattern.
        $this->insertOfflineATariffProfile($I, 5001, [
            ['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0844111222', 5001);

        $line = $this->makeLine('41844111222', 60, '2026-05-13 10:00:00'); // Wed 10:00 -> weekday
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.5, $price, $this->epsilon,
            'Conversion regex must rewrite "41" prefix to "0" and price the resolved INA');
    }

    public function testInaMatching_outOfRevisionWindow_returnsFalse(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10001, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10001, [
            'transactionDatetime'   => $this->bsonDate('2024-01-01 00:00:00'),
            'transactionDatetimeTo' => $this->bsonDate('2024-06-01 00:00:00'),
        ]);

        $line = $this->makeLine('0844111222', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertFalse($price, 'No revision matching the urt must produce no price');
    }

    /* ============================================================
     *  ONLINE tariff-profile pricing
     * ============================================================ */

    public function testOnlinePricing_singleSequence_baseChargeAndChargeRate(AcceptanceTester $I)
    {
        // chargeRate 12 cents/min, baseCharge 5 cents, startInterval 10 sec free.
        // 60-second call, first CDR -> applyBaseCharge=true, full startInterval pool.
        // price = baseCharge/100 + chargeRate/100/60 * max(60-10, 0)
        //       = 0.05         + 0.12/60 * 50 = 0.05 + 0.1 = 0.15
        $this->insertOnlineTariffProfileSingleSequence($I, 10001, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10001);

        $line = $this->makeLine('0844111222', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.15, $price, $this->epsilon,
            'Online single-sequence pricing must apply baseCharge plus chargeRate over duration past startInterval');
    }

    public function testOnlinePricing_zeroDuration_onlyBaseCharge(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10002, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10002);

        $line = $this->makeLine('0844111222', 0, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.05, $price, $this->epsilon,
            'Zero-duration first CDR should still bill the online baseCharge');
    }

    public function testOnlinePricing_durationWithinStartInterval_onlyBaseCharge(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10003, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10003);

        $line = $this->makeLine('0844111222', 8, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.05, $price, $this->epsilon,
            'Duration entirely inside startInterval must charge only baseCharge');
    }

    /* ============================================================
     *  OFFLINE-A tariff-profile pricing
     * ============================================================ */

    public function testOfflineA_weekday_proRata(AcceptanceTester $I)
    {
        $this->insertOfflineATariffProfile($I, 4001, [
            ['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900111000', 4001);

        $line = $this->makeLine('0900111000', 60, '2026-05-13 10:00:00'); // Wednesday
        $price = $this->priceLine($line);

        // useRuleDuration = 60 / 60 = 1; ruleDuration = INF -> aprice = 1 * 50 / 100 = 0.5
        $I->assertEqualsWithDelta(0.5, $price, $this->epsilon,
            'Weekday PRO_RATA should price 60s at rate=50/60s = 0.50 CHF');
    }

    public function testOfflineA_saturday_usesSaturdayConfig(AcceptanceTester $I)
    {
        $this->insertOfflineATariffProfile(
            $I,
            4002,
            [['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']],
            [['sequence' => 1, 'rate' => 70, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']]
        );
        $this->insertInaNumber($I, '0900111000', 4002);

        // 2026-05-16 is a Saturday
        $line = $this->makeLine('0900111000', 60, '2026-05-16 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.7, $price, $this->epsilon,
            'Saturday config must override weekday config for offline-a pricing');
    }

    public function testOfflineA_sundayAndHoliday_usesSundayConfig(AcceptanceTester $I)
    {
        $this->insertOfflineATariffProfile(
            $I,
            4003,
            [['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']],
            [['sequence' => 1, 'rate' => 70, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']],
            [['sequence' => 1, 'rate' => 90, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']]
        );
        $this->insertInaNumber($I, '0900111000', 4003);

        // 2026-05-17 is a Sunday
        $line = $this->makeLine('0900111000', 60, '2026-05-17 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.9, $price, $this->epsilon,
            'Sunday config must override weekday config for offline-a pricing');
    }

    public function testOfflineA_fixPrice_firesOnZeroDuration(AcceptanceTester $I)
    {
        $this->insertOfflineATariffProfile($I, 4004, [
            ['sequence' => 1, 'rate' => 15, 'time' => 0, 'ruleType' => 'FIX_PRICE', 'ruleDuration' => 1, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900111000', 4004);

        $line = $this->makeLine('0900111000', 0, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.15, $price, $this->epsilon,
            'FIX_PRICE rule must bill the drop charge even for zero-duration calls');
    }

    /* ============================================================
     *  call_offset CDR-split tests (BRCD-5294)
     * ============================================================ */

    public function testCallOffset_online_firstCdrFullCharge(AcceptanceTester $I)
    {
        // First CDR of a split call: call_offset = 0 -> full baseCharge + full startInterval pool.
        $this->insertOnlineTariffProfileSingleSequence($I, 10010, [
            'chargeRate' => 12, 'baseCharge' => 10, 'startInterval' => 30,
        ]);
        $this->insertInaNumber($I, '0844200000', 10010);

        // 60s segment; only 30s past the startInterval pool counts.
        $line = $this->makeLine('0844200000', 60, '2026-05-13 10:00:00', 0);
        $price = $this->priceLine($line);

        // 0.10 + 0.12/60 * (60-30) = 0.10 + 0.06 = 0.16
        $I->assertEqualsWithDelta(0.16, $price, $this->epsilon,
            'First CDR of a split call must apply full baseCharge and full startInterval');
    }

    public function testCallOffset_online_secondCdrNoBaseChargeNoStartInterval(AcceptanceTester $I)
    {
        // Second CDR of the same call: call_offset = 60s (i.e. the first segment already
        // consumed 60s, more than the 30s startInterval). No baseCharge, no startInterval rebate.
        $this->insertOnlineTariffProfileSingleSequence($I, 10011, [
            'chargeRate' => 12, 'baseCharge' => 10, 'startInterval' => 30,
        ]);
        $this->insertInaNumber($I, '0844200000', 10011);

        // 120-second second segment, 60s already elapsed before this CDR.
        $line = $this->makeLine('0844200000', 120, '2026-05-13 10:01:00', 60);
        $price = $this->priceLine($line);

        // 0 (no baseCharge) + 0.12/60 * max(120 - 0, 0) = 0.24
        $I->assertEqualsWithDelta(0.24, $price, $this->epsilon,
            'Second CDR of a split call must skip baseCharge and exhausted startInterval');
    }

    public function testCallOffset_online_secondCdrInsideRemainingStartInterval(AcceptanceTester $I)
    {
        // Edge case: startInterval=30, callDurationBefore=10 -> 20s still free on this segment.
        $this->insertOnlineTariffProfileSingleSequence($I, 10012, [
            'chargeRate' => 12, 'baseCharge' => 10, 'startInterval' => 30,
        ]);
        $this->insertInaNumber($I, '0844200000', 10012);

        $line = $this->makeLine('0844200000', 60, '2026-05-13 10:00:30', 10);
        $price = $this->priceLine($line);

        // 0 + 0.12/60 * (60 - 20) = 0.08
        $I->assertEqualsWithDelta(0.08, $price, $this->epsilon,
            'Second CDR must consume only the leftover startInterval pool, not the full one');
    }

    public function testCallOffset_offlineA_skipsAlreadyConsumedSequence(AcceptanceTester $I)
    {
        // Two sequences:
        //   seq 1: PRO_RATA rate=10, time=60, ruleDuration=2  -> capacity = 60s * 2 = 120s
        //   seq 2: PRO_RATA rate=5,  time=60, ruleDuration=0  -> INF capacity
        // call_offset=120s means the entire seq 1 was consumed by earlier CDRs; this CDR
        // must price its 60s purely from seq 2: 60/60 * 5 / 100 = 0.05.
        $this->insertOfflineATariffProfile($I, 4010, [
            ['sequence' => 1, 'rate' => 10, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 2, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 5,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900222000', 4010);

        $line = $this->makeLine('0900222000', 60, '2026-05-13 10:02:00', 120);
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.05, $price, $this->epsilon,
            'Offline-a multi-sequence pricing must skip sequences fully consumed by previous CDRs');
    }

    public function testCallOffset_offlineA_partialConsumeOfFirstSequence(AcceptanceTester $I)
    {
        // seq 1: PRO_RATA rate=10, time=60, ruleDuration=2 -> 120s capacity
        // seq 2: PRO_RATA rate=5,  time=60, ruleDuration=0
        // call_offset = 90s (1.5 minutes of seq 1 used). This CDR is 60s long:
        //   - 30s remaining in seq 1 -> 30/60 * 10 / 100 = 0.05
        //   - 30s overflow into seq 2 -> 30/60 * 5 / 100 = 0.025
        // Total expected = 0.075
        $this->insertOfflineATariffProfile($I, 4011, [
            ['sequence' => 1, 'rate' => 10, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 2, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 5,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900222000', 4011);

        $line = $this->makeLine('0900222000', 60, '2026-05-13 10:01:30', 90);
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.075, $price, $this->epsilon,
            'Offline-a pricing must cap leftover capacity in the partially-consumed sequence and roll over into the next');
    }

    /* ============================================================
     *  Production-reference smoke tests
     *  ------------------------------------------------------------
     *  Two real CDRs to 0900530520 observed in prod with known
     *  aprice values. The synthetic tariff profiles below are
     *  calibrated to reproduce those aprice values, so any change
     *  in pricingCdr math that drifts from prod will fail here.
     * ============================================================ */

    public function testProdReference_call_1510s_tariff_2640(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900530520" (subscriber_number "0900530520" after conversion)
        //   uf.Chargeable_units = "001510"     (1510 seconds)
        //   uf.Tariff_Class     = "002640"
        //   Charging_Date       = 250519  (2025-05-19, Monday -> weekday)
        //   Charge_start_time   = 105812  (10:58:12 Zurich)
        //   aprice              = 13.876
        //
        $duration = 1510;
        $expectedAprice = 13.876;
        $this->insertOfflineATariffProfile($I, 2640, [
            ['sequence' => 1, 'rate' => 150, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 10, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 0,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900530520', 2640);

        $line = $this->makeLine('0900530520', $duration, '2025-05-19 10:58:12');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 13.876 for a 1510s call to 0900530520');
    }

    public function testProdReference_call_138s_tariff_2640(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900530520" (subscriber_number "0900530520" after conversion)
        //   uf.Chargeable_units = "000138"     (138 seconds)
        //   uf.Tariff_Class     = "002640"
        //   Charging_Date       = 240902  (2024-09-02, Monday -> weekday)
        //   Charge_start_time   = 125652  (12:56:52 Zurich)
        //   aprice              = 3.191
        //
        $duration = 138;
        $expectedAprice = 3.191;
        $chargeRate = $expectedAprice * 6000.0 / $duration;
        $this->insertOfflineATariffProfile($I, 2640, [
            ['sequence' => 1, 'rate' => 150, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 10, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 0,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900530520', 2640);

        $line = $this->makeLine('0900530520', $duration, '2024-09-02 12:56:52');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 3.191 for a 138s call to 0900530520');
    }
}
