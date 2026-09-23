<?php

/**
 * BRCD-3562 - end-to-end tests for the --calculate command.
 *
 * Each test builds the queue by PROCESSING a real CSV file, then runs the
 * actual `php public/index.php --calculate` command as a subprocess and asserts
 * the resulting queue/lines state and what the run logged (LogChecker, scoped
 * to the run by runCalculate). This exercises the whole path: file parsing
 * -> queue -> CLI -> calculator selection -> the customer/rate/pricing/tax chain.
 *
 * Chain-mode semantics under test (BRCD-3562): a run fetches the queue ONCE
 * (with no --type the single pull may take lines from different queue stages)
 * and every fetched line runs, one line at a time, through all its remaining
 * calculators in memory; --type starts at that calculator's stage; one_calc=true
 * (only with --type) keeps the previous behavior - one calculator, then the
 * line returns to the queue.
 *
 * Getting lines into the queue *uncalculated*: the calcCpu plugin calculates
 * inline during processing, but only as far as the available data allows. So
 * processing a file BEFORE the subscriber exists leaves the lines fresh
 * (calc_name = false); creating the subscriber/rate afterwards lets the
 * subsequent --calculate run take them through the chain. (Processing with a
 * subscriber but no rate would instead leave them at calc_name = 'customer'.)
 *
 * The CSV has several rows on purpose: the queue puller leaves the current tail
 * line (highest urt) behind on each pass, so a single line would never be
 * pulled - a handful lets the earlier lines flow through while the tail waits.
 */
class calculateCommandCest
{
    public static $settingsLoaded = false;

    /** file (line) type used for these tests */
    private $fileType = 'calc_e2e';

    /** rows in test_files/calc_e2e.csv */
    private $csvRows = 6;

    /** phone that matches the seeded subscriber and the CSV rows */
    private $subscriberPhone = '0531234567';

    /** phone in chain_mixed_fresh.csv that matches no subscriber until seedSubscriber() creates one */
    private $unknownPhone = '0539999999';

    /** plan every seeded subscriber is put on */
    private $planName = 'E2E_PLAN';

    /** CRM mockup subscribers (crm_data/<aid>.json), by the phone the CSV rows carry; all on $crmFixturePlan */
    private $crmFixtures = [
        '0561234567' => ['aid' => 35620001, 'sid' => 3562101],
        '0569999999' => ['aid' => 35620002, 'sid' => 3562102],
    ];
    private $crmFixturePlan = 'PLAN_BULK_E2E';

    /** the phone of every row in bulk_one_phone.csv - the first CRM subscriber above */
    private $crmPhone = '0561234567';

    public function _before(ApiTester $I)
    {
        if (!self::$settingsLoaded) {
            // Upserts this one file type; other tests' file types are untouched.
            $I->setSettings('file_types', $this->inputProcessor);
            $I->setSettings('usage_types', [[
                'usage_type' => 'call', 'label' => 'call', 'property_type' => 'time',
                'invoice_uom' => 'seconds', 'input_uom' => 'seconds',
            ]]);
            self::$settingsLoaded = true;
            Billrun_Config::getInstance()->loadDbConfig();
        }
        $I->cleanDB();
        $I->resetBillrunInstances();
    }

    public function _after(ApiTester $I)
    {
        // Leave a clean slate so this test cannot leak state into later tests.
        // $I->cleanDB();
        // $I->resetBillrunInstances();
    }

    public $inputProcessor = [
        'file_type' => 'calc_e2e',
        'parser' => [
            'type' => 'separator',
            'line_types' => ['H' => '/^none$/', 'D' => '//', 'T' => '/^none$/'],
            'separator' => ',',
            'structure' => [
                ['name' => 'firstname', 'checked' => true],
                ['name' => 'date', 'checked' => true],
                ['name' => 'rate', 'checked' => true],
                ['name' => 'volume', 'checked' => true],
            ],
            'csv_has_header' => true,
            'csv_has_footer' => false,
        ],
        'processor' => ['type' => 'Usage', 'date_field' => 'date', 'default_usaget' => 'call', 'default_unit' => 'seconds', 'default_volume_src' => ['volume']],
        'customer_identification_fields' => ['call' => [[
            'target_key' => 'firstname', 'src_key' => 'firstname',
            'conditions' => [['field' => 'usaget', 'regex' => '/.*/']], 'clear_regex' => '//',
        ]]],
        'rate_calculators' => ['retail' => ['call' => [[[
            'type' => 'match', 'rate_key' => 'key', 'line_key' => 'rate',
        ]]]]],
        'pricing' => ['call' => []],
        'enabled' => true,
        'filters' => [],
        'receiver' => ['type' => 'ftp', 'connections' => [[
            'receiver_type' => 'ftp', 'passive' => false, 'delete_received' => false,
            'user' => 'admin', 'password' => '12345678', 'host' => '127.0.0.1', 'name' => 'a', 'remote_directory' => '/home',
        ]]],
    ];

    // ---------------------------------------------------------------- helpers

    /**
     * Process the CSV into the queue. Called before the subscriber exists, so
     * the lines land in the queue uncalculated (calc_name = false).
     */
    private function processToQueue(ApiTester $I, $csv = 'calc_e2e.csv')
    {
        $I->processByPath(['type' => $this->fileType, 'path' => 'tests/all/calculators/test_files/' . $csv]);
    }

    /**
     * Create the account, plan, rate and subscriber a line needs to calculate.
     * With $withRate = false the rate is not created, so a processed line passes
     * the customer stage but fails rating and parks at calc_name = 'customer'.
     */
    private function seedCustomer(ApiTester $I, $withRate = true)
    {
        $I->generatePlan(['name' => $this->planName, 'from' => '2025-01-01']);
        $plan = json_decode($I->grabResponse(), true)['entity'];
        if ($withRate) {
            $this->generateCallRate($I);
        }
        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'e2e_acct']);
        $acct = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber([
            'from' => '2025-01-01', 'firstname' => $this->subscriberPhone,
            'aid' => $acct['aid'], 'plan' => $plan['name'],
        ]);
    }

    /**
     * Add a subscriber for one more phone, so a line the customer stage could
     * not match starts matching. The plan seedCustomer() created is reused - only
     * the account and the subscriber are added.
     */
    private function seedSubscriber(ApiTester $I, $phone)
    {
        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'e2e_acct_' . $phone]);
        $acct = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber([
            'from' => '2025-01-01', 'firstname' => $phone,
            'aid' => $acct['aid'], 'plan' => $this->planName,
        ]);
    }

    private function countQueue(array $query = [])
    {
        return Billrun_Factory::db()->queueCollection()->query($query)->count();
    }

    private function countLines(array $query = [])
    {
        return Billrun_Factory::db()->linesCollection()->query($query)->count();
    }

    /** Distinct stamps of the queue lines matching $query (as strings, for set comparisons). */
    private function queueStamps(array $query = [])
    {
        $stamps = [];
        foreach (Billrun_Factory::db()->queueCollection()->query($query)->cursor() as $doc) {
            $stamps[] = (string) $doc['stamp'];
        }
        return $stamps;
    }

    /** Map of stamp => calc_time key (sec.usec, or 'fresh' when calc_time is false) for the matching queue lines. */
    private function queueCalcTimes(array $query = [])
    {
        $out = [];
        foreach (Billrun_Factory::db()->queueCollection()->query($query)->cursor() as $doc) {
            $ct = $doc['calc_time'];
            $out[(string) $doc['stamp']] = ($ct instanceof Mongodloid_Date) ? ($ct->sec . '.' . $ct->usec) : 'fresh';
        }
        return $out;
    }

    /** Create the retail rate the CSV rows whose rate column holds $key match. */
    private function generateCallRate(ApiTester $I, $key = 'CALL')
    {
        $I->generateRate([
            'tariff_category' => 'retail', 'key' => $key, 'from' => '2025-01-01',
            'rates' => ['call' => ['BASE' => ['rate' => [[
                'from' => 0, 'to' => 'UNLIMITED', 'interval' => 1, 'price' => 1,
                'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
            ]]]]],
        ]);
    }

    /** The one line whose CSV volume is $volume (rows are told apart by their volume), as a plain array. */
    private function lineByVolume(ApiTester $I, $volume)
    {
        $found = [];
        foreach (Billrun_Factory::db()->linesCollection()->query(['type' => $this->fileType, 'uf.volume' => (string) $volume])->cursor() as $doc) {
            $found[] = $doc->getRawData();
        }
        $I->assertCount(1, $found, 'exactly one line should have volume ' . $volume);
        return $found[0];
    }

    /** The queue row of the given line as a plain array, or null when the line is no longer queued. */
    private function queueRowOf(array $line)
    {
        foreach (Billrun_Factory::db()->queueCollection()->query(['stamp' => $line['stamp']])->cursor() as $row) {
            return $row->getRawData();
        }
        return null;
    }


    /** Any one line of this type as a plain array - for fields every line shares (aid, billrun). */
    private function anyLine(ApiTester $I)
    {
        foreach (Billrun_Factory::db()->linesCollection()->query(['type' => $this->fileType])->cursor()->limit(1) as $doc) {
            return $doc->getRawData();
        }
        $I->fail('no line of type ' . $this->fileType . ' exists');
    }

    /** Current customer-stage queue batch size (customer.calculator.limit), or the ini default. */
    private function getCustomerBatchLimit()
    {
        return Billrun_Factory::config()->getConfigValue('customer.calculator.limit', 5000);
    }

    private function setCustomerBatchLimit(ApiTester $I, $limit)
    {
        $I->overrideConfigValue('customer.calculator.limit', $limit);
    }

    /** Current rate-stage queue batch size (Rate_Usage.calculator.limit), or the ini default. */
    private function getRateBatchLimit()
    {
        return Billrun_Factory::config()->getConfigValue('Rate_Usage.calculator.limit', 5000);
    }

    private function setRateBatchLimit(ApiTester $I, $limit)
    {
        $I->overrideConfigValue('Rate_Usage.calculator.limit', $limit);
    }

    /** Current tax-stage queue batch size (tax.calculator.limit), or the ini default. */
    private function getTaxBatchLimit()
    {
        return Billrun_Factory::config()->getConfigValue('tax.calculator.limit', 5000);
    }

    private function setTaxBatchLimit(ApiTester $I, $limit)
    {
        $I->overrideConfigValue('tax.calculator.limit', $limit);
    }

    /** Current customer.calculator.bulk - the customer stage's bulk subscriber loading (ini default: off). */
    private function getBulkSetting()
    {
        return Billrun_Factory::config()->getConfigValue('customer.calculator.bulk', 0);
    }

    private function setBulkSetting(ApiTester $I, $bulk)
    {
        $I->overrideConfigValue('customer.calculator.bulk', $bulk);
    }

    /** The log line the external subscriber source writes for every request it sends: to the configured (gsd) URL. */
    private function crmRequestMarker()
    {
        return 'Sending request to ' . Billrun_Factory::config()->getConfigValue('subscribers.subscriber.external_url');
    }

    /** How long a pulled-but-failed queue line waits before it may be re-pulled. */
    private function getOrphanWait()
    {
        return Billrun_Factory::config()->getConfigValue('queue.calculator.orphan_wait_time', '6 hours');
    }

    private function setOrphanWait(ApiTester $I, $value)
    {
        $I->overrideConfigValue('queue.calculator.orphan_wait_time', $value);
    }

    /**
     * Precondition check: processing (before any subscriber exists) should have
     * put every CSV row in the queue, fresh and uncalculated.
     */
    private function assertFreshlyQueued(ApiTester $I)
    {
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => false, 'calc_time' => false]),
            'processing should leave every line queued and uncalculated');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'processing before the subscriber exists should not enrich any line');
    }

    // ------------------------------------------------------------------ tests

    /**
     * With no --type the command runs the whole calculator chain, so a line is
     * fully calculated (priced) and removed from the queue in a single run - and,
     * exactly as a line completed inline by the processor is
     * (Billrun_Processor::setFullCalculationTime), stamped full_calculation when
     * lines.reference_fields lists it (a global config, restored at the end).
     */
    public function testCalculateWithoutTypeRunsTheWholeChain(ApiTester $I): void
    {
        $this->processToQueue($I);   // uncalculated lines in the queue
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);     // now the data needed to calculate exists

        $originalFields = Billrun_Factory::config()->getConfigValue('lines.reference_fields', []);
        $I->overrideConfigValue('lines.reference_fields', array_values(array_unique(array_merge($originalFields, ['full_calculation']))));
        try {
            $code = $I->runCalculate();
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
            $I->seeInLogFile('Chain results: ' . $this->csvRows . ' lines completed, 0 advanced and left for retry, 0 left unchanged');
        } finally {
            $I->overrideConfigValue('lines.reference_fields', $originalFields);
        }

        // Whole chain ran: every line was priced (customer+rate+pricing) and stamped as completed.
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'a no-type run must price every line (the whole chain ran)');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'full_calculation' => ['$exists' => true]]),
            'every line that completed its chain must carry full_calculation');
        // A fully-calculated line reached the last calculator and left the queue.
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'a fully-calculated line should be removed from the queue');
    }

    /**
     * With --type but WITHOUT one_calc the fetched lines go through ALL the
     * remaining calculators in memory: a --type=customer run prices the lines
     * and removes them from the queue, in a single queue fetch.
     */
    public function testTypeRunChainsRemainingCalculatorsInMemory(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        $code = $I->runCalculate('customer');
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        // One fetch for the whole run (a single "Entities loaded" line) taking every
        // line, and every fetched line finished its chain in memory.
        $I->seeCountInLogFile('Entities loaded: ', 1);
        $I->seeInLogFile('Entities loaded: ' . $this->csvRows);
        $I->seeInLogFile('Chain results: ' . $this->csvRows . ' lines completed, 0 advanced and left for retry, 0 left unchanged');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'a --type run without one_calc must take the lines through pricing');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'a fully-calculated line should be removed from the queue');
    }

    /**
     * --type customer one_calc=true runs ONLY the customer calculator: the
     * lines get a subscriber, are not priced, and every one of them is back in
     * the queue at the customer stage, released, for the next stage's run.
     */
    public function testOneCalcRunsOnlyTheRequestedCalculator(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        $code = $I->runCalculate('customer', null, true);
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        // The customer calculator ran: every line has a subscriber.
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'the customer calculator should enrich lines with a subscriber');
        // No later calculator ran: no line was priced.
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'pricing must not run in a one_calc=true run');
        // Every line is back in the queue at the customer stage, released (calc_time = false).
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer', 'calc_time' => false]),
            'one_calc=true must leave every line in the queue at the customer stage, released for the next run');
    }

    /**
     * one_calc=true is meaningful only together with --type. Passed alone it is
     * reported and ignored: the run is a plain no-type chain run - one pull, every
     * line through its whole remaining chain, priced and out of the queue.
     */
    public function testOneCalcWithoutTypeIsIgnoredAndTheWholeChainRuns(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        $code = $I->runCalculate(null, null, true);
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        $I->seeInLogFile('one_calc is only relevant together with --type; ignoring it');
        // Ignored means chain mode, exactly as without the flag.
        $I->seeCountInLogFile('Entities loaded: ', 1);
        $I->seeInLogFile('Chain results: ' . $this->csvRows . ' lines completed, 0 advanced and left for retry, 0 left unchanged');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'the ignored flag must not stop the chain: every line must be priced');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'every fully-calculated line must leave the queue');
    }

    /**
     * one_calc=true at a LATER stage: lines waiting at the rate stage (calc_name =
     * 'customer') are rated by a --type rate_Usage one_calc=true run and nothing
     * more - not priced - and go back to the queue at the rate tag, released, for
     * the pricing stage's run. No chain runs at all.
     */
    public function testOneCalcAtALaterStageRunsOnlyThatCalculator(ApiTester $I): void
    {
        $this->seedCustomer($I, false); // subscriber but no rate: processing parks the lines at the rate stage
        $this->processToQueue($I);
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer', 'calc_time' => false]),
            'processing with a subscriber but no rate should leave every line waiting at the rate stage');
        $this->generateCallRate($I);

        $code = $I->runCalculate('rate_Usage', null, true);
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        $I->dontSeeInLogFile('Chain results:');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'arate' => ['$exists' => true]]),
            'the rate calculator must rate every waiting line');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'pricing must not run in a one_calc=true run');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'rate', 'calc_time' => false]),
            'one_calc=true must leave every line in the queue at the rate stage, released for the pricing run');
    }

    /**
     * one_calc=true stage by stage, the pre-BRCD-3562 cron layout (customer,
     * Rate_Usage, customerPricing and tax as separate runs - the names
     * scripts/pseudo_cron_cycle.sh uses): each run advances the lines exactly one
     * stage and returns them to the queue released, and no chain ever runs.
     *
     * The last calculator that actually does work is customerPricing: it applies
     * tax itself (applyTaxToRow) and, when tax directly follows pricing in
     * queue.calculators, tags the lines PAST the tax stage
     * (CustomerPricing::getQueueTagType). With tax as the last stage the pricing
     * run therefore removes the finished lines from the queue itself, and the
     * separate --type tax run of the old layout finds nothing left to do.
     */
    public function testOneCalcStageByStageTheLastCalculatorRemovesTheLines(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        // customer, then rate: one calculator each, lines back in the queue at its tag.
        foreach (['customer' => 'customer', 'Rate_Usage' => 'rate'] as $type => $stage) {
            $code = $I->runCalculate($type, null, true);
            $I->assertEquals(0, $code, "--type $type one_calc=true should exit cleanly");
            $I->dontSeeInLogFile('Chain results:');
            $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => $stage, 'calc_time' => false]),
                "after --type $type one_calc=true every line must wait, released, at the '$stage' tag");
        }
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'no line may be priced before the pricing run');

        // Pricing prices AND taxes, tags the lines past the tax stage and - tax being
        // the last stage - removes them from the queue.
        $code = $I->runCalculate('customerPricing', null, true);
        $I->assertEquals(0, $code, '--type customerPricing one_calc=true should exit cleanly');
        $I->dontSeeInLogFile('Chain results:');
        $I->seeInLogFile('Removing ' . $this->csvRows . ' lines from queue');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'the pricing run must price every line');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'tax_data' => ['$exists' => true]]),
            'the pricing run must tax every line as well');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'the last calculator that does work must remove the finished lines from the queue');

        // The old layout's separate tax run has nothing left to do - and does no harm.
        $code = $I->runCalculate('tax', null, true);
        $I->assertEquals(0, $code, '--type tax one_calc=true should exit cleanly');
        $I->seeInLogFile('Entities loaded: 0');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'tax_data' => ['$exists' => true]]),
            'the tax run must not disturb the already taxed lines');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'the queue must stay empty');
    }

    /**
     * A calculator outside the queue chain - rebalance works off its own
     * rebalance_queue and has no queue stage - must still run on its own with
     * --type rebalance, as before BRCD-3562, instead of being resolved to a chain
     * stage it does not have and doing nothing.
     */
    public function testTypeOutsideTheChainRunsThatCalculatorOnItsOwn(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);

        $code = $I->runCalculate('rebalance');
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        $I->seeInLogFile('Calculator type rebalance is not a queue chain stage; running it on its own');
        $I->seeInLogFile('Execute reset'); // the rebalance calculator itself ran
        $I->dontSeeInLogFile('Chain results:');
        // It works off rebalance_queue: our queue lines are not touched.
        $this->assertFreshlyQueued($I);
    }

    /**
     * --type rebalance end to end: the rebalance calculator - its own write() and
     * removeFromQueue(), working off rebalance_queue rather than the calculators
     * chain - still does its whole job after BRCD-3562. The calculated lines of the
     * account are reset (calculated fields cleared, lines back in the queue fresh),
     * the rebalance_queue entry is marked started and finished, and the next
     * no-type chain run prices every line again.
     *
     * The rebalance is requested through the resetlines API, as the UI does.
     * resetlines.offset (default 1 hour) hides entries requested moments ago and
     * resetlines.process_time_offset (default 15 minutes) hides lines processed
     * moments ago, so both are shortened for the run and restored.
     */
    public function testRebalanceResetsCalculatedLinesAndTheChainRecalculatesThem(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);
        $code = $I->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'precondition: the whole chain priced every line');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'precondition: nothing is left in the queue');

        // Ask for a rebalance of that account's cycle through the resetlines API, as
        // the UI does: it queues a rebalance_queue entry for the run to pick up.
        $line = $this->anyLine($I);
        $response = $I->resetLines($line['aid'], $line['billrun']);
        $I->assertStringContainsString('"status":1', $response, 'the resetlines API must accept the request');
        $rebalanceQueue = Billrun_Factory::db()->rebalance_queueCollection();
        $entryQuery = ['aid' => $line['aid'], 'billrun_key' => $line['billrun']];
        $I->assertEquals(1, $rebalanceQueue->query($entryQuery)->count(),
            'the API must queue exactly one rebalance entry for the account and cycle');

        // The entry was requested moments ago and the lines were processed moments
        // ago: shorten both offsets that would hide them from the run.
        $originalOffset = Billrun_Factory::config()->getConfigValue('resetlines.offset', '1 hour');
        $originalProcessOffset = Billrun_Factory::config()->getConfigValue('resetlines.process_time_offset', '15 minutes');
        $I->overrideConfigValue('resetlines.offset', '-1 second');
        $I->overrideConfigValue('resetlines.process_time_offset', '-1 second');
        try {
            $code = $I->runCalculate('rebalance');
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
            $I->seeInLogFile('Calculator type rebalance is not a queue chain stage; running it on its own');
            $I->seeInLogFile('Success resetting aids ' . $line['aid']);
        } finally {
            $I->overrideConfigValue('resetlines.offset', $originalOffset);
            $I->overrideConfigValue('resetlines.process_time_offset', $originalProcessOffset);
        }

        // The entry is closed and the lines are reset: kept, stripped of their
        // calculation, and queued fresh again.
        $done = null;
        foreach ($rebalanceQueue->query($entryQuery)->cursor() as $doc) {
            $done = $doc->getRawData();
        }
        $I->assertNotNull($done, 'the rebalance entry must still exist');
        $I->assertTrue(isset($done['start_time']) && isset($done['end_time']),
            'the rebalance entry must be marked started and finished');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType]),
            'a reset keeps the lines');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'a reset must clear the calculated price');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'a reset must clear the customer fields');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => false, 'calc_time' => false]),
            'a reset must queue every line fresh, for the whole chain');

        // Full circle: the next chain run recalculates them.
        $code = $I->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        $I->seeInLogFile('Chain results: ' . $this->csvRows . ' lines completed, 0 advanced and left for retry, 0 left unchanged');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'the rebalanced lines must be priced again by the chain');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'the rebalanced lines must leave the queue once recalculated');
    }

    // --------------------------------------------- customer stage in BULK mode
    //
    // customer.calculator.bulk=1 makes the customer stage load the subscribers of
    // the whole batch in ONE lookup (Billrun_Calculator_Customer::prepareData ->
    // loadSubscribers) instead of one lookup per line. The chain feeds the stage
    // the whole pulled batch, so the bulk lookup must cover every line, map each
    // line to ITS OWN subscriber, and treat a line with no subscriber as failed -
    // neither failing the batch nor letting the line through.
    //
    // Bulk is checked where it is countable: with EXTERNAL subscribers (the CRM
    // mockup, fixtures crm_data/3562000{1,2}.json) the external source logs every
    // request it sends, so a bulk batch is exactly ONE gsd request where the
    // per-line path sends one per line. A miss shows up as "Missing subscriber
    // info for line with stamp" (the bulk path) - never as the per-line path's
    // "Error loading subscriber for row". The subscriber source and the bulk flag
    // are global config, restored at the end of each test.

    /**
     * Bulk mode: the customer stage loads the batch's subscribers with ONE gsd
     * request, and every line is enriched with the CRM subscriber and priced.
     */
    public function testBulkModeLoadsTheBatchSubscribersInOneCrmRequest(ApiTester $I): void
    {
        $this->runExternalSubscribersScenario($I, true, 1);
    }

    /**
     * The control for the test above: the same batch with bulk OFF costs one gsd
     * request PER LINE for the same end state - so the single request of the bulk
     * run really is the bulk lookup at work.
     */
    public function testPerLineModeSendsOneCrmRequestPerLine(ApiTester $I): void
    {
        $this->runExternalSubscribersScenario($I, false, $this->csvRows);
    }

    /**
     * Bulk mode with a MIXED batch (bulk_mixed.csv): two phones the CRM knows,
     * each with its own fixture, and one it does not. ONE gsd request must carry
     * the whole batch, give each matched line ITS OWN CRM subscriber, and fail
     * each unmatched line on its own - not enriched with someone else's
     * subscriber, not let through, not failing the batch - so it stays queued
     * for a retry.
     */
    public function testBulkModeMapsEachLineToItsOwnSubscriberInOneCrmRequest(ApiTester $I): void
    {
        $this->processToQueue($I, 'bulk_mixed.csv'); // 2 rows each for 0561234567 and 0569999999 (CRM fixtures) and 0587654321 (unknown)
        $this->assertFreshlyQueued($I);
        $I->generatePlan(['name' => $this->crmFixturePlan, 'from' => '2025-01-01']);
        $this->generateCallRate($I);

        $originalBulk = $this->getBulkSetting();
        $this->setBulkSetting($I, 1);
        $I->enableExternalModeSettings();
        try {
            $code = $I->runCalculate();
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
            $I->seeCountInLogFile($this->crmRequestMarker(), 1);
            $I->seeCountInLogFile('Missing subscriber info for line with stamp', 2); // one per unmatched line, from the bulk path
            $I->dontSeeInLogFile('Error loading subscriber for row'); // the per-line path's miss message
            $I->seeInLogFile('Chain results: 4 lines completed, 0 advanced and left for retry, 2 left unchanged');
        } finally {
            $I->enableDBModeSettings();
            $this->setBulkSetting($I, $originalBulk);
        }

        foreach ($this->crmFixtures as $phone => $crm) {
            $I->assertEquals(2, $this->countLines(['type' => $this->fileType, 'uf.firstname' => $phone, 'aid' => $crm['aid'], 'sid' => $crm['sid']]),
                "the lines of $phone must be enriched with their own CRM subscriber");
        }
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'uf.firstname' => '0587654321', 'aid' => ['$exists' => true]]),
            'a line the CRM does not know must not be enriched - not even with another line\'s subscriber');
        $I->assertEquals(4, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'the matched lines must go on through the chain and be priced');
        $I->assertEquals(2, $this->countQueue(['type' => $this->fileType, 'calc_name' => false]),
            'the unmatched lines must stay queued, uncalculated, for a retry');
    }

    /**
     * Shared body of the two single-phone tests: the CSV lines are queued
     * uncalculated (DB mode, no local subscriber), the plan the CRM subscribers
     * are on and the CALL rate are created locally, then the subscriber source is
     * switched to the CRM mockup for one no-type chain run and the gsd requests
     * it sent are counted. No other stage of the chain queries the subscriber
     * source.
     */
    private function runExternalSubscribersScenario(ApiTester $I, $bulk, $expectedRequests)
    {
        $this->processToQueue($I, 'bulk_one_phone.csv'); // 6 rows, all for the first CRM subscriber's phone
        $this->assertFreshlyQueued($I);
        $I->generatePlan(['name' => $this->crmFixturePlan, 'from' => '2025-01-01']);
        $this->generateCallRate($I);

        $originalBulk = $this->getBulkSetting();
        $this->setBulkSetting($I, $bulk ? 1 : 0);
        $I->enableExternalModeSettings(); // subscribers now come from the CRM mockup - DB config, so the CLI sees it too
        try {
            $code = $I->runCalculate();
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
            $I->seeInLogFile('Entities loaded: ' . $this->csvRows);
            $I->seeCountInLogFile($this->crmRequestMarker(), $expectedRequests);
            $I->seeInLogFile('Chain results: ' . $this->csvRows . ' lines completed, 0 advanced and left for retry, 0 left unchanged');
        } finally {
            $I->enableDBModeSettings();
            $this->setBulkSetting($I, $originalBulk);
        }

        $crm = $this->crmFixtures[$this->crmPhone];
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aid' => $crm['aid'], 'sid' => $crm['sid']]),
            'every line must be enriched with the CRM subscriber');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'every line must go on through the chain and be priced');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'every fully-calculated line should be removed from the queue');
    }

    /**
     * file_types restricts the run to the given line type: filtering by a type
     * that is not in the queue processes nothing; filtering by the real type
     * runs the whole chain (no --type is given), so the lines are priced and
     * leave the queue.
     */
    public function testCalculateFiltersByFileTypes(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        // Filter by a type that is not present -> nothing is pulled.
        $code = $I->runCalculate(null, 'no_such_type');
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'no line should be calculated when file_types matches nothing');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => false, 'calc_time' => false]),
            'all lines should be left untouched when file_types matches nothing');

        // Filter by the real type -> the lines are calculated.
        $code = $I->runCalculate(null, $this->fileType);
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'lines of the filtered type should be calculated');
        // No --type was given, so the whole chain ran: lines are priced and leave the queue.
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'a no-type run must price at least one line (the whole chain ran)');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'a fully-calculated line should be removed from the queue');
    }

    /**
     * A line that cannot be calculated (no matching subscriber) is not lost: it
     * stays in the queue so a later run can retry it.
     */
    public function testUnmatchedLinesStayInQueueForRetry(ApiTester $I): void
    {
        // Process the lines but never create a subscriber for them.
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);

        $code = $I->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'lines with no matching subscriber must not be enriched');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => false]),
            'unmatched lines must stay in the queue, still uncalculated (calc_name = false), for a later retry');
    }

    /**
     * The rate stage's batch limit (Rate_Usage.calculator.limit) must bound a
     * no-type run. Regression guard for a bug specific to the rate stage: the
     * stage was resolved to the type 'rate_Usage' while its config section is
     * 'Rate_Usage' (case-sensitive lookup), so its limit was never read and the
     * whole backlog was swallowed at once (in the field: 255,000 lines instead of
     * batches of 5,000). The other stages' keys match exactly, so only the rate
     * limit can expose it.
     *
     * A no-type pull batches by the MINIMUM among the stages' configured limits;
     * the rate limit is set to 1 (the others default to 500+), so the single
     * cross-stage pull must mark and load exactly 1 of the 3 waiting lines,
     * leaving 2 untouched for the next run.
     *
     * The lines are parked at the rate stage (calc_name = 'customer') by
     * processing WITH a subscriber but WITHOUT the rate (see the class comment).
     * Rating also fails during the --calculate run itself, so the line stays in
     * the queue.
     *
     * The batch limit is a global setting, restored at the end.
     */
    public function testRateStageBatchesByItsConfiguredLimit(ApiTester $I): void
    {
        $backlog = 3; // rows in test_files/rate_batch_limit.csv
        $this->seedCustomer($I, false); // subscriber but no rate: lines park at the rate stage
        $this->processToQueue($I, 'rate_batch_limit.csv');

        $I->assertEquals($backlog, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer', 'calc_time' => false]),
            'processing with a subscriber but no rate should leave every line waiting at the rate stage');

        $originalLimit = $this->getRateBatchLimit();
        $this->setRateBatchLimit($I, 1);
        try {
            $code = $I->runCalculate();
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

            // The single pull must fetch AND mark a batch of exactly the rate stage's
            // limit (1) - never the whole backlog.
            $I->seeInLogFile('Entities loaded: 1');
            $I->seeInLogFile('Queue records updated number: 1');
            $I->dontSeeInLogFile('Queue records updated number: ' . $backlog);
            // The other lines were left untouched, released and fresh for the next run.
            $I->assertEquals($backlog - 1, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer', 'calc_time' => false]),
                'the lines beyond the batch limit must be left untouched');
        } finally {
            $this->setRateBatchLimit($I, $originalLimit);
        }
    }

    /**
     * The no-type batch is the MINIMUM among ALL stages' limits, not the limit of
     * the stage the lines happen to wait at: here every line is fresh (entering at
     * the customer stage) and only the TAX stage's limit is lowered to 1 - the
     * single pull must still take exactly 1 line.
     *
     * The batch limit is a global setting, restored at the end.
     */
    public function testNoTypeBatchIsTheMinimumAcrossAllStages(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        $originalLimit = $this->getTaxBatchLimit();
        $this->setTaxBatchLimit($I, 1);
        try {
            $code = $I->runCalculate();
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

            $I->seeInLogFile('Entities loaded: 1');
            $I->seeInLogFile('Queue records updated number: 1');
            $I->dontSeeInLogFile('Queue records updated number: ' . $this->csvRows);
            $I->seeInLogFile('Chain results: 1 lines completed, 0 advanced and left for retry, 0 left unchanged');
            $I->assertEquals(1, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
                'exactly the one pulled line must complete the chain');
            $I->assertEquals($this->csvRows - 1, $this->countQueue(['type' => $this->fileType, 'calc_name' => false, 'calc_time' => false]),
                'the lines beyond the batch must be left untouched');
        } finally {
            $this->setTaxBatchLimit($I, $originalLimit);
        }
    }

    /**
     * Circulation of the SUCCESS path over many passes: with a subscriber the
     * customer stage succeeds and advances each line, and the queue must keep
     * handing every line its turn over repeated go-rounds - none may starve. The
     * batch is shrunk so each run advances only a few lines; after a line advances
     * we re-queue it at the BACK of the line (calc_name reset to false, calc_time
     * stamped to now) - a stand-in for whatever would put a processed line back in
     * the queue - and make the orphan window negative so it is eligible again next
     * run. We keep running until every line has been circulated at least three times.
     *
     * (Unlike the stuck case a succeeding line does NOT re-circulate on its own -
     * success clears it from the calc_name = false pool - hence the explicit
     * re-queue. Fresh, never-advanced lines keep calc_time = false and so sort ahead
     * of the re-queued ones, which is what keeps the rotation fair.)
     *
     * The batch limit and orphan window are global settings, restored at the end.
     */
    public function testCalculateCirculatesEverySucceedingLineAtLeastThreeTimes(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I); // subscriber exists, so the customer stage succeeds and advances lines

        $rounds = 3;
        $originalLimit = $this->getCustomerBatchLimit();
        $originalOrphan = $this->getOrphanWait();
        $this->setCustomerBatchLimit($I, 1);   // small batch so each run advances only a few lines
        // A NEGATIVE wait makes a re-queued line eligible again immediately: the
        // eligibility threshold (strtotime "<wait> ago") has whole-second granularity,
        // so with '0 seconds' a line stamped at second S only becomes eligible at
        // wall-clock second S+1 - and the runs are much faster than a second, so
        // most runs would find nothing eligible and the run budget would expire.
        $this->setOrphanWait($I, '-1 second');

        try {
            $stamps = $this->queueStamps(['type' => $this->fileType]);
            $advances = array_fill_keys($stamps, 0);
            $runs = 0;
            $maxRuns = $this->csvRows * $rounds + 10; // generous cap; the loop stops once every line hits the target
            while (min($advances) < $rounds && $runs < $maxRuns) {
                $runs++;
                // one_calc=true: this test circulates the SINGLE customer stage, so the
                // advanced lines must stop at calc_name = 'customer' (not chain onward).
                $code = $I->runCalculate('customer', null, true);
                $I->assertEquals(0, $code, "run $runs: the calculate command should exit cleanly");

                // The lines the customer stage advanced this run are now at calc_name = 'customer'.
                $advanced = $this->queueStamps(['type' => $this->fileType, 'calc_name' => 'customer']);
                foreach ($advanced as $stamp) {
                    $advances[$stamp]++;
                }
                // Re-queue them at the back of the line so they circulate again, fairly.
                if (!empty($advanced)) {
                    Billrun_Factory::db()->queueCollection()->update(
                        ['type' => $this->fileType, 'calc_name' => 'customer'],
                        ['$set' => ['calc_name' => false, 'calc_time' => new Mongodloid_Date()]],
                        ['multiple' => true]
                    );
                }
            }

            // Every single line advanced at least $rounds times - the success path keeps
            // circulating each line and none starves over repeated go-rounds.
            $I->assertCount($this->csvRows, $advances, 'every line must be accounted for');
            $I->assertGreaterThanOrEqual($rounds, min($advances),
                "every line must be circulated at least $rounds times (took $runs runs)");
        } finally {
            // Restore the global settings so later tests use the real configuration.
            $this->setCustomerBatchLimit($I, $originalLimit);
            $this->setOrphanWait($I, $originalOrphan);
        }
    }

    /**
     * Circulation over MANY passes: the queue must keep giving EVERY line its turn,
     * pass after pass, not just once - no line may starve however many times we go
     * around. The lines are stuck at the customer stage (no subscriber) so they
     * never leave the queue, the batch is shrunk so each run handles only a few, and
     * the orphan window is made negative so a pulled-but-failed line becomes eligible
     * to be re-pulled on the next run. We then keep running the command until every
     * line has been circulated at least three times.
     *
     * Each pull stamps a fresh calc_time on the lines it takes, so a line's attempt
     * count is how many distinct calc_time values it has carried. For N lines, a
     * batch of B and a target of R rounds this takes roughly ceil(N * R / B) runs
     * (e.g. 6 lines / batch 1 -> 18, / batch 4 -> 5) - but the exact count depends
     * on the batch and how evenly the pull distributes, so we loop until the target
     * is met rather than hard-coding it.
     *
     * The batch limit and orphan window are global settings, restored at the end.
     */
    public function testCalculateCirculatesEveryLineAtLeastThreeTimes(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        // NOTE: no seedCustomer() - there is no subscriber, so every line fails the customer stage.

        $rounds = 3;
        $originalLimit = $this->getCustomerBatchLimit();
        $originalOrphan = $this->getOrphanWait();
        $this->setCustomerBatchLimit($I, 4);   // small batch so each run handles only a few lines
        // Negative on purpose - a failed line is eligible to re-circulate on the
        // very next run (see the note in the previous test).
        $this->setOrphanWait($I, '-1 second');

        try {
            $stamps = $this->queueStamps(['type' => $this->fileType]);
            $attempts = array_fill_keys($stamps, 0);
            $lastCalcTime = array_fill_keys($stamps, 'fresh');
            $runs = 0;
            $maxRuns = $this->csvRows * $rounds + 10; // generous cap; the loop stops once every line hits the target
            while (min($attempts) < $rounds && $runs < $maxRuns) {
                $runs++;
                $code = $I->runCalculate('customer');
                $I->assertEquals(0, $code, "run $runs: the calculate command should exit cleanly");

                // A line was (re-)attempted this run iff its calc_time changed to a new value.
                foreach ($this->queueCalcTimes(['type' => $this->fileType, 'calc_name' => false]) as $stamp => $calcTime) {
                    if ($calcTime !== 'fresh' && $calcTime !== $lastCalcTime[$stamp]) {
                        $attempts[$stamp]++;
                        $lastCalcTime[$stamp] = $calcTime;
                    }
                }
            }

            // Every single line was circulated at least $rounds times - the queue keeps
            // handing each line its turn and none starves over repeated circulation.
            $I->assertCount($this->csvRows, $attempts, 'every line must be accounted for');
            $I->assertGreaterThanOrEqual($rounds, min($attempts),
                "every line must be circulated at least $rounds times (took $runs runs)");
            // The lines really are stuck: none advanced and none was enriched.
            $I->assertEquals(0, $this->countQueue(['type' => $this->fileType, 'calc_name' => ['$ne' => false]]),
                'no line may advance past the customer stage - there is no subscriber to match');
            $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
                'no line may be enriched with a subscriber');
        } finally {
            // Restore the global settings so later tests use the real configuration.
            $this->setCustomerBatchLimit($I, $originalLimit);
            $this->setOrphanWait($I, $originalOrphan);
        }
    }

    /**
     * A no-type run fetches the queue ONCE, and that single pull may take lines
     * from DIFFERENT queue stages: here 3 lines wait at the customer stage and 3
     * at the rate stage, and one invocation fetches all 6 together and takes each
     * through its remaining calculators in memory.
     */
    public function testNoTypeSinglePullTakesLinesFromDifferentStages(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        // Park half the lines at the rate stage: a one_calc customer run with a
        // batch of 3 advances exactly 3 lines and returns them to the queue.
        $originalLimit = $this->getCustomerBatchLimit();
        $this->setCustomerBatchLimit($I, 3);
        try {
            $code = $I->runCalculate('customer', null, true);
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        } finally {
            $this->setCustomerBatchLimit($I, $originalLimit);
        }
        $I->assertEquals(3, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer']),
            'the prep run should park exactly 3 lines at the rate stage');
        $I->assertEquals(3, $this->countQueue(['type' => $this->fileType, 'calc_name' => false]),
            'the prep run should leave exactly 3 lines fresh');

        // One no-type invocation: a single pull takes both groups and finishes them all.
        $code = $I->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        // A single pull took the lines of BOTH stages together, and all of them
        // completed their remaining chain in the same run.
        $I->seeCountInLogFile('Entities loaded: ', 1);
        $I->seeInLogFile('Entities loaded: ' . $this->csvRows);
        $I->seeInLogFile('Chain results: ' . $this->csvRows . ' lines completed');
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'every line, from either stage, must complete the chain');
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'every fully-calculated line should be removed from the queue');
    }

    /**
     * One no-type run over a MIXED queue where every line ends differently: one
     * completes the chain and leaves the queue, one advances a stage and then
     * fails the next (parked further on, claimed), and the others fail their own
     * stage and stay put (claimed). The pull order (urt) deliberately alternates
     * the stages - rate-stage line, fresh line, rate-stage line, fresh line - so
     * the run is shown to start each line at ITS OWN stage, not in stage order.
     *
     * Rows are told apart by their volume:
     *  - 10  parked at 'customer', rate CALL          -> completes (the rate exists by run time)
     *  - 20  fresh, subscriber ok, rate NO_SUCH_RATE  -> advances to 'customer', then fails rating
     *  - 30  parked at 'customer', rate NO_SUCH_RATE  -> fails rating again, stays at 'customer'
     *  - 40  fresh, no such subscriber                -> fails customer, stays fresh
     *
     * Then the missing rate and subscriber are created and the claim is expired:
     * a second run must take the three lines left behind through the rest of the
     * chain, so every line ends up priced and out of the queue.
     */
    public function testMixedPullOneCompletesOneAdvancesTheRestStayPut(ApiTester $I): void
    {
        // Fresh lines: processed before any subscriber exists.
        $this->processToQueue($I, 'chain_mixed_fresh.csv');
        $I->assertEquals(2, $this->countQueue(['type' => $this->fileType, 'calc_name' => false, 'calc_time' => false]),
            'the fresh file should leave its 2 lines queued and uncalculated');
        // Parked lines: processed with the subscriber but no rate -> they wait at the rate stage.
        $this->seedCustomer($I, false);
        $this->processToQueue($I, 'chain_mixed_parked.csv');
        $I->assertEquals(2, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer', 'calc_time' => false]),
            'the parked file should leave its 2 lines waiting at the rate stage');
        // Now the rate exists: only the rows with rate = CALL can be rated.
        $this->generateCallRate($I);

        $code = $I->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        // A single pull took all 4 lines from both stages; one completed, one
        // advanced and two stayed put - all in the same run.
        $I->seeCountInLogFile('Entities loaded: ', 1);
        $I->seeInLogFile('Entities loaded: 4');
        $I->seeInLogFile('Chain results: 1 lines completed, 1 advanced and left for retry, 2 left unchanged');

        // 10: completed the whole chain - priced and gone from the queue.
        $completed = $this->lineByVolume($I, 10);
        $I->assertTrue(isset($completed['aprice']), 'the completed line must be priced');
        $I->assertNull($this->queueRowOf($completed), 'the completed line must leave the queue');

        // 20: passed customer, failed rating - parked at 'customer', claimed.
        $advanced = $this->lineByVolume($I, 20);
        $I->assertTrue(isset($advanced['aid']), 'the advanced line must be enriched by the customer stage');
        $I->assertFalse(isset($advanced['aprice']), 'the advanced line must not be priced');
        $row = $this->queueRowOf($advanced);
        $I->assertNotNull($row, 'the advanced line must stay in the queue');
        $I->assertSame('customer', $row['calc_name'], 'the advanced line must be parked at the stage it completed');
        $I->assertTrue($row['calc_time'] !== false, 'the advanced line must be left claimed');

        // 30: failed rating again - still at 'customer', claimed.
        $stuckAtRate = $this->lineByVolume($I, 30);
        $I->assertFalse(isset($stuckAtRate['aprice']), 'the line failing rating must not be priced');
        $row = $this->queueRowOf($stuckAtRate);
        $I->assertNotNull($row, 'the line failing rating must stay in the queue');
        $I->assertSame('customer', $row['calc_name'], 'the line failing rating must stay at the rate stage');
        $I->assertTrue($row['calc_time'] !== false, 'the line failing rating must be left claimed');

        // 40: failed customer - still fresh, claimed.
        $stuckAtCustomer = $this->lineByVolume($I, 40);
        $I->assertFalse(isset($stuckAtCustomer['aid']), 'the line with no subscriber must not be enriched');
        $row = $this->queueRowOf($stuckAtCustomer);
        $I->assertNotNull($row, 'the line with no subscriber must stay in the queue');
        $I->assertFalse($row['calc_name'], 'the line with no subscriber must stay uncalculated (calc_name = false)');
        $I->assertTrue($row['calc_time'] !== false, 'the line with no subscriber must be left claimed');

        // Give the chain what the three stuck lines were missing - the rate their
        // rows name and a subscriber for the unknown phone - and expire the claim
        // they are holding: the next run must finish all of them.
        $this->generateCallRate($I, 'NO_SUCH_RATE');
        $this->seedSubscriber($I, $this->unknownPhone);
        $originalOrphan = $this->getOrphanWait();
        $this->setOrphanWait($I, '-1 second');
        try {
            $code = $I->runCalculate();
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
            // The second pull takes the three lines the first run left behind, and
            // with the rate and the subscriber in place all of them complete.
            $I->seeInLogFile('Entities loaded: 3');
            $I->seeInLogFile('Chain results: 3 lines completed, 0 advanced and left for retry, 0 left unchanged');
            $nowMatched = $this->lineByVolume($I, 40);
            $I->assertTrue(isset($nowMatched['aid']),
                'the new subscriber must let the previously unmatched line through the customer stage');
            $I->assertEquals(4, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
                'every line of both files must end up priced');
            $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
                'all the lines must exit the queue once the chain completes for each of them');
        } finally {
            $this->setOrphanWait($I, $originalOrphan);
        }
    }

    /**
     * Full circulation in chain mode: a line that fails mid-chain is left in the
     * queue, CLAIMED, at the last stage it completed, and a later run resumes it
     * from there. The resume run uses the concrete calculator name (rate_Usage)
     * to also cover resolving a concrete type to its queue stage.
     */
    public function testChainLeavesFailedLineClaimedAndResumesMidStage(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I, false); // subscriber but NO rate: the rate stage will fail

        $code = $I->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        // Customer succeeded in the chain, rate failed: the lines parked at the
        // customer stage, still claimed (chain mode keeps the claim on failure).
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'the customer stage must enrich the lines before the rate stage fails');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'no line may be priced when rating failed');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer']),
            'a line failing the rate stage must stay queued at the last completed stage');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer', 'calc_time' => ['$ne' => false]]),
            'a line failing mid-chain must be left claimed (calc_time set) until the orphan window');

        // Now the rate exists and the claim is expired: a rate_Usage run must
        // resume the lines from the rate stage and finish the chain.
        $this->generateCallRate($I);
        $originalOrphan = $this->getOrphanWait();
        $this->setOrphanWait($I, '-1 second');
        try {
            $code = $I->runCalculate('rate_Usage');
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
            $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
                'the resumed lines must complete the remaining chain (rate + pricing)');
            $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
                'the resumed lines must leave the queue when the chain completes');
        } finally {
            $this->setOrphanWait($I, $originalOrphan);
        }
    }
}
