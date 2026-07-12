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
    private function processToQueue(ApiTester $I)
    {
        $I->processByPath(['type' => $this->fileType, 'path' => 'tests/all/calculators/test_files/calc_e2e.csv']);
    }

    /** Create the account, plan, rate and subscriber a line needs to calculate. */
    private function seedCustomer(ApiTester $I)
    {
        $I->generatePlan(['name' => 'E2E_PLAN', 'from' => '2025-01-01']);
        $plan = json_decode($I->grabResponse(), true)['entity'];
        $I->generateRate([
            'tariff_category' => 'retail', 'key' => 'CALL', 'from' => '2025-01-01',
            'rates' => ['call' => ['BASE' => ['rate' => [[
                'from' => 0, 'to' => 'UNLIMITED', 'interval' => 1, 'price' => 1,
                'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
            ]]]]],
        ]);
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
        $I->assertGreaterThanOrEqual(1, $this->countLines(['type' => $this->fileType, 'aprice' => ['$exists' => true]]),
            'a no-type run must price at least one line (the whole chain ran)');
        // A fully-calculated line reached the last calculator and left the queue.
        $I->assertLessThan($this->csvRows, $this->countQueue(['type' => $this->fileType]),
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
        $I->assertGreaterThanOrEqual(1, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
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
     * processes the lines.
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
        $I->assertGreaterThanOrEqual(1, $this->countLines(['type' => $this->fileType, 'aid' => ['$exists' => true]]),
            'lines of the filtered type should be calculated');
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
}
