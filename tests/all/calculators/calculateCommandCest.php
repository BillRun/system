<?php

/**
 * BRCD-3562 - end-to-end tests for the --calculate command.
 *
 * Each test builds the queue by PROCESSING a real CSV file, then runs the
 * actual `php public/index.php --calculate` command as a subprocess and asserts
 * the resulting queue/lines state. This exercises the whole path: file parsing
 * -> queue -> CLI -> calculator selection -> the customer/rate/pricing/tax chain.
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
        $I->cleanDB();
        $I->resetBillrunInstances();
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
        $I->generatePlan(['name' => 'E2E_PLAN', 'from' => '2025-01-01']);
        $plan = json_decode($I->grabResponse(), true)['entity'];
        if ($withRate) {
            $I->generateRate([
                'tariff_category' => 'retail', 'key' => 'CALL', 'from' => '2025-01-01',
                'rates' => ['call' => ['BASE' => ['rate' => [[
                    'from' => 0, 'to' => 'UNLIMITED', 'interval' => 1, 'price' => 1,
                    'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                ]]]]],
            ]);
        }
        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'e2e_acct']);
        $acct = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber([
            'from' => '2025-01-01', 'firstname' => $this->subscriberPhone,
            'aid' => $acct['aid'], 'plan' => $plan['name'],
        ]);
    }

    /** Run the real --calculate CLI command as a subprocess. Returns [exitCode, output]. */
    private function runCalculate($type = null, $fileTypes = null)
    {
        $cmd = 'php ' . APPLICATION_PATH . '/public/index.php --calculate';
        if ($type !== null) {
            $cmd .= ' --type ' . $type;
        }
        if ($fileTypes !== null) {
            $cmd .= ' file_types=' . $fileTypes;
        }
        $out = [];
        $code = null;
        exec($cmd . ' 2>&1', $out, $code);
        return [$code, implode("\n", $out)];
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

        list($code) = $this->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        // Whole chain ran: at least one line was priced (customer+rate+pricing).
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'a no-type run must price at least one line (the whole chain ran)');
        // A fully-calculated line reached the last calculator and left the queue.
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType]),
            'a fully-calculated line should be removed from the queue');
    }

    /**
     * With --type=customer only the customer calculator runs: lines get a
     * subscriber but are not rated/priced and none leave the queue.
     */
    public function testCalculateWithTypeRunsOnlyThatCalculator(ApiTester $I): void
    {
        $this->processToQueue($I);
        $this->assertFreshlyQueued($I);
        $this->seedCustomer($I);

        list($code) = $this->runCalculate('customer');
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        // Customer ran: lines were enriched with a subscriber.
        $I->assertEquals($this->csvRows, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'the customer calculator should enrich lines with a subscriber');
        // Nothing downstream ran: no pricing, nothing removed from the queue.
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'pricing must not run in a --type=customer run');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => ['$in' => [false, 'customer']]]),
            'customer is not the last calculator, so no line is removed; every line is still at or before the customer stage (calc_name in {false, customer})');
        // No line advanced past the customer stage.
        $I->assertEquals(0, $this->countQueue(['type' => $this->fileType, 'calc_name' => ['$nin' => [false, 'customer']]]),
            'no line should advance beyond the customer stage');
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
        list($code) = $this->runCalculate(null, 'no_such_type');
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');
        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'no line should be calculated when file_types matches nothing');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => false, 'calc_time' => false]),
            'all lines should be left untouched when file_types matches nothing');

        // Filter by the real type -> the lines are calculated.
        list($code) = $this->runCalculate(null, $this->fileType);
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

        list($code) = $this->runCalculate();
        $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

        $I->assertEquals(0, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'lines with no matching subscriber must not be enriched');
        $I->assertEquals($this->csvRows, $this->countQueue(['type' => $this->fileType, 'calc_name' => false]),
            'unmatched lines must stay in the queue, still uncalculated (calc_name = false), for a later retry');
    }

    /**
     * The rate stage of a no-type run must batch by its own configured limit
     * (Rate_Usage.calculator.limit) - just as customer, pricing and tax batch by
     * theirs in the same run. Regression test: the no-type chain instantiates the
     * rate calculator without its config section, so the stage fell back to the
     * built-in default limit and swallowed the ENTIRE backlog as a single batch
     * (in the field: 255,000 lines at once instead of batches of 5,000).
     *
     * Small-scale repro: 3 lines wait at the rate stage (calc_name = 'customer')
     * and the rate limit is set to 1. The queue puller reads limit + 1 docs (the
     * extra doc is only the exclusive urt boundary of the batch), so a correct
     * run logs "current limit : 2" and marks/loads exactly 1 line, leaving 2
     * untouched, while the buggy run logs "current limit : 3" (the whole
     * backlog) and marks all 3.
     *
     * The lines are parked at the rate stage by processing WITH a subscriber but
     * WITHOUT the rate (see the class comment). Rating also fails during the
     * --calculate run itself, so no line advances past the rate stage and the
     * downstream stages pull nothing - the batch logs asserted below can only
     * come from the rate stage.
     *
     * The rate batch limit is a global setting, restored at the end.
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
            list($code, $output) = $this->runCalculate();
            $I->assertEquals(0, $code, 'the calculate command should exit cleanly');

            // The pull horizon must be capped by the configured limit (+1 boundary
            // doc) - never the whole backlog.
            $I->assertStringNotContainsString('current limit : ' . $backlog, $output,
                'the rate stage must not pull the entire backlog as a single batch');
            // Exactly one line - the configured limit - may be marked and loaded.
            $I->assertStringContainsString('Queue records updated number: 1', $output,
                'the rate stage must mark a batch of exactly its configured limit (1)');
            $I->assertStringNotContainsString('Queue records updated number: ' . $backlog, $output,
                'the rate stage must not mark the entire backlog in one batch');
            $I->assertStringContainsString('Entities loaded: 1', $output,
                'the rate stage must load a batch of exactly its configured limit (1)');
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
                list($code) = $this->runCalculate('customer');
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
                list($code) = $this->runCalculate('customer');
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
}
