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

    /**
     * Run the real --calculate CLI command as a subprocess and return its exit
     * code. The log offset is cleared first, so the LogChecker assertions that
     * follow (seeInLogFile & co.) see only what THIS run logged.
     */
    private function runCalculate(ApiTester $I, $type = null, $fileTypes = null, $oneCalc = false)
    {
        $cmd = 'php ' . APPLICATION_PATH . '/public/index.php --calculate';
        if ($type !== null) {
            $cmd .= ' --type ' . $type;
        }
        if ($fileTypes !== null) {
            $cmd .= ' file_types=' . $fileTypes;
        }
        if ($oneCalc) {
            $cmd .= ' one_calc=true';
        }
        $I->clearLogFile();
        $out = [];
        $code = null;
        exec($cmd . ' 2>&1', $out, $code); // output captured only to keep it out of the test report
        // An uncaught exception is logged as CRIT "Crashed When running..." and the
        // CLI still exits 0, so the exit code alone proves nothing - fail right here.
        $I->dontSeeInLogFile('Crashed When running');
        return $code;
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


    /** Current customer-stage queue batch size (customer.calculator.limit), or the ini default. */
    private function getCustomerBatchLimit()
    {
        return Billrun_Factory::config()->getConfigValue('customer.calculator.limit', 5000);
    }

    private function setCustomerBatchLimit($limit)
    {
        $this->writeDbConfigValue(['customer', 'calculator', 'limit'], $limit);
    }

    /** Current rate-stage queue batch size (Rate_Usage.calculator.limit), or the ini default. */
    private function getRateBatchLimit()
    {
        return Billrun_Factory::config()->getConfigValue('Rate_Usage.calculator.limit', 5000);
    }

    private function setRateBatchLimit($limit)
    {
        $this->writeDbConfigValue(['Rate_Usage', 'calculator', 'limit'], $limit);
    }

    /** How long a pulled-but-failed queue line waits before it may be re-pulled. */
    private function getOrphanWait()
    {
        return Billrun_Factory::config()->getConfigValue('queue.calculator.orphan_wait_time', '6 hours');
    }

    private function setOrphanWait($value)
    {
        $this->writeDbConfigValue(['queue', 'calculator', 'orphan_wait_time'], $value);
    }

    /**
     * Write one nested config value as a new DB config revision. --calculate runs
     * as a subprocess, so overrides must live in DB config for it to pick them up.
     * The settings API rejects these low-level paths ("Category not found ..."), so
     * we write the revision directly (the teldas-plugin pattern); mergeConfigs
     * deep-merges it over the ini defaults, changing only the given leaf. Callers
     * MUST restore the original value.
     */
    private function writeDbConfigValue(array $path, $value)
    {
        $model = new ConfigModel();
        $data = $model->getConfig();
        unset($data['_id']);
        $data['urt'] = new Mongodloid_Date();

        $ref = &$data;
        $leaf = array_pop($path);
        foreach ($path as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref[$leaf] = $value;
        unset($ref);

        Billrun_Factory::db()->configCollection(['force' => true])->insert($data);
        if (Billrun_Config::getInstance()->isConfigCacheEnabled() && ($cache = Billrun_Factory::cache())) {
            $cache->remove('db_config', 'config');
        }
        Billrun_Config::getInstance()->refresh();
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
     * fully calculated (priced) and removed from the queue in a single run.
     */
    public function testCalculateWithoutTypeRunsTheWholeChain(ApiTester $I): void
    {
        $this->processToQueue($I);   // uncalculated lines in the queue
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);     // now the data needed to calculate exists

        $code = $this->runCalculate($I);
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        // Whole chain ran: at least one line was priced (customer+rate+pricing).
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'a no-type run must price at least one line (the whole chain ran)');
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

        $code = $this->runCalculate($I, 'customer');
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

        $code = $this->runCalculate($I, 'customer', null, true);
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
        $code = $this->runCalculate($I, null, 'no_such_type');
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'no line should be calculated when file_types matches nothing');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => false, 'calc_time' => false]),
            'all lines should be left untouched when file_types matches nothing');

        // Filter by the real type -> the lines are calculated.
        $code = $this->runCalculate($I, null, $this->fileType);
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

        $code = $this->runCalculate($I);
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
        $this->setRateBatchLimit(1);
        try {
            $code = $this->runCalculate($I);
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
            $this->setRateBatchLimit($originalLimit);
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
        $this->setCustomerBatchLimit(1);   // small batch so each run advances only a few lines
        // A NEGATIVE wait makes a re-queued line eligible again immediately: the
        // eligibility threshold (strtotime "<wait> ago") has whole-second granularity,
        // so with '0 seconds' a line stamped at second S only becomes eligible at
        // wall-clock second S+1 - and the runs are much faster than a second, so
        // most runs would find nothing eligible and the run budget would expire.
        $this->setOrphanWait('-1 second');

        try {
            $stamps = $this->queueStamps(['type' => $this->fileType]);
            $advances = array_fill_keys($stamps, 0);
            $runs = 0;
            $maxRuns = $this->csvRows * $rounds + 10; // generous cap; the loop stops once every line hits the target
            while (min($advances) < $rounds && $runs < $maxRuns) {
                $runs++;
                // one_calc=true: this test circulates the SINGLE customer stage, so the
                // advanced lines must stop at calc_name = 'customer' (not chain onward).
                $code = $this->runCalculate($I, 'customer', null, true);
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
            $this->setCustomerBatchLimit($originalLimit);
            $this->setOrphanWait($originalOrphan);
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
        $this->setCustomerBatchLimit(4);   // small batch so each run handles only a few lines
        // Negative on purpose - a failed line is eligible to re-circulate on the
        // very next run (see the note in the previous test).
        $this->setOrphanWait('-1 second');

        try {
            $stamps = $this->queueStamps(['type' => $this->fileType]);
            $attempts = array_fill_keys($stamps, 0);
            $lastCalcTime = array_fill_keys($stamps, 'fresh');
            $runs = 0;
            $maxRuns = $this->csvRows * $rounds + 10; // generous cap; the loop stops once every line hits the target
            while (min($attempts) < $rounds && $runs < $maxRuns) {
                $runs++;
                $code = $this->runCalculate($I, 'customer');
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
            $this->setCustomerBatchLimit($originalLimit);
            $this->setOrphanWait($originalOrphan);
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
        $this->setCustomerBatchLimit(3);
        try {
            $code = $this->runCalculate($I, 'customer', null, true);
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        } finally {
            $this->setCustomerBatchLimit($originalLimit);
        }
        $I->assertEquals(3, $this->countQueue(['type' => $this->fileType, 'calc_name' => 'customer']),
            'the prep run should park exactly 3 lines at the rate stage');
        $I->assertEquals(3, $this->countQueue(['type' => $this->fileType, 'calc_name' => false]),
            'the prep run should leave exactly 3 lines fresh');

        // One no-type invocation: a single pull takes both groups and finishes them all.
        $code = $this->runCalculate($I);
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

        $code = $this->runCalculate($I);
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
        $this->setOrphanWait('-1 second');
        try {
            $code = $this->runCalculate($I);
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
            $this->setOrphanWait($originalOrphan);
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

        $code = $this->runCalculate($I);
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
        $this->setOrphanWait('-1 second');
        try {
            $code = $this->runCalculate($I, 'rate_Usage');
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
            $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
                'the resumed lines must complete the remaining chain (rate + pricing)');
            $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
                'the resumed lines must leave the queue when the chain completes');
        } finally {
            $this->setOrphanWait($originalOrphan);
        }
    }
}
