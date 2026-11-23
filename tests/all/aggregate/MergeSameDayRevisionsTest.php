<?php

/**
 * BRCD-5644 - cycle of a subscriber with 2 revisions on the same day, when one of the revisions
 * has no "services" field and a plugin merges the revisions into one
 * (Billrun_Utils_Cycle::mergeCycleRevisions on beforeConstructSubscriberRecords, like the netplus plugin).
 * Before the fix: PHP 8 - the cycle crashed (TypeError in array_udiff),
 * PHP 7 - the services of the merged revision were not charged.
 */
class MergeSameDayRevisionsTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $epsilon = 0.00001;

    protected static $plugin;

    public $defaultOptions = array(
        "type" => "customer",
        "stamp" => "202512",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
    );

    protected function _before()
    {
        $this->tester->setTimezone('UTC');
        $this->tester->enableDBModeSettings();
        $this->tester->cleanDB();
        // core does not merge revisions by itself - the merge is done by a plugin
        if (empty(self::$plugin)) {
            self::$plugin = new MergeSameDayRevisionsTestPlugin();
            \Billrun_Factory::dispatcher()->attach(self::$plugin);
        }
        self::$plugin->setAvailability(true);
        self::$plugin->merged = 0;
    }

    protected function _after()
    {
        self::$plugin->setAvailability(false);
        $this->tester->restoreTimezone();
    }

    /**
     * the case of the ticket - the service was added in the second revision
     */
    public function testPreviousRevisionWithoutServices()
    {
        $aid = $this->runCycleOnSameDayRevisions(null, ['SERVICE_5644_A']);

        $this->assertServiceLines(['SERVICE_5644_A'], $aid);
        $this->assertTotalBeforeVat(110, $aid);
    }

    public function testNextRevisionWithoutServices()
    {
        $aid = $this->runCycleOnSameDayRevisions(['SERVICE_5644_A'], null);

        $this->assertServiceLines(['SERVICE_5644_A'], $aid);
        $this->assertTotalBeforeVat(110, $aid);
    }

    /**
     * a service that exists in both revisions is charged once
     */
    public function testBothRevisionsWithServices()
    {
        $aid = $this->runCycleOnSameDayRevisions(['SERVICE_5644_A'], ['SERVICE_5644_A', 'SERVICE_5644_B']);

        $this->assertServiceLines(['SERVICE_5644_A', 'SERVICE_5644_B'], $aid);
        $this->assertTotalBeforeVat(120, $aid);
    }

    public function testBothRevisionsWithoutServices()
    {
        $aid = $this->runCycleOnSameDayRevisions(null, null);

        $this->assertServiceLines([], $aid);
        $this->assertTotalBeforeVat(100, $aid);
    }

    /**
     * Create an account with one subscriber that has 2 revisions on the same day (1 second apart)
     * and run the cycle on it. null services = the revision has no "services" field.
     * @return int the aid
     */
    protected function runCycleOnSameDayRevisions($prevServices, $nextServices)
    {
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $aid = json_decode($this->tester->grabResponse(), true)['entity']['aid'];
        $this->tester->generatePlan(['name' => 'PLAN_5644', 'prorated_start' => false, 'prorated_end' => false, 'prorated_termination' => false]);
        $this->tester->generateService(['name' => 'SERVICE_5644_A', 'prorated' => false]);
        $this->tester->generateService(['name' => 'SERVICE_5644_B', 'prorated' => false]);
        $this->tester->generateSubscriber(['aid' => $aid, 'plan' => 'PLAN_5644', 
        'from' => '2025-11-10T10:00:00Z']);

        $subscribersCollection = \Billrun_Factory::db()->subscribersCollection();
        $subscriberQuery = array('aid' => $aid, 'type' => 'subscriber');
        $prev = $subscribersCollection->query($subscriberQuery)->cursor()->current()->getRawData();
        $nextFrom = new Mongodloid_Date($prev['from']->sec + 1);
        $next = $prev;
        unset($next['_id'], $next['services']);
        $next['from'] = $nextFrom;
        // the cycle unifies identical revisions by itself - keep the 2 revisions different
        $next['address'] = 'hshalom 8';
        if ($nextServices !== null) {
            $next['services'] = $this->getSubscriberServices($nextServices, $prev['from']);
        }
        $prevUpdate = array('$set' => array('to' => $nextFrom));
        if ($prevServices !== null) {
            $prevUpdate['$set']['services'] = $this->getSubscriberServices($prevServices, $prev['from']);
        } else {
            $prevUpdate['$unset'] = array('services' => 1);
        }
        $subscribersCollection->update($subscriberQuery, $prevUpdate);
        $subscribersCollection->insert($next);

        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->runCycle($this->defaultOptions);

        $this->assertEquals(1, self::$plugin->merged, 'the 2 revisions were not merged');
        $this->tester->seeNumElementsInCollection('lines', 1, ['billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'name' => 'PLAN_5644', 'aid' => $aid]);
        return $aid;
    }

    protected function getSubscriberServices($names, $from)
    {
        $services = [];
        foreach ($names as $name) {
            $services[] = [
                'name' => $name,
                'from' => $from,
                'to' => new Mongodloid_Date(strtotime('2100-01-01 00:00:00')),
            ];
        }
        return $services;
    }

    protected function assertServiceLines($expectedNames, $aid)
    {
        $names = [];
        foreach (\Billrun_Factory::db()->linesCollection()->query(['billrun' => $this->defaultOptions['stamp'], 'type' => 'service', 'aid' => $aid])->cursor() as $line) {
            $names[] = $line['name'];
        }
        sort($names);
        $this->assertEquals($expectedNames, $names);
    }

    protected function assertTotalBeforeVat($expected, $aid)
    {
        $billrun = $this->tester->grabFromCollection('billrun', ['billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid]);
        $this->assertEqualsWithDelta($expected, $billrun['totals']['before_vat'], $this->epsilon);
    }
}

/**
 * Merges the revisions of a subscriber that start on the same day into one revision - the way the netplus plugin does
 */
class MergeSameDayRevisionsTestPlugin extends Billrun_Plugin_BillrunPluginBase
{
    protected $name = 'mergeSameDayRevisionsTest';

    public $merged = 0;

    public function beforeConstructSubscriberRecords(&$revisionsData, $subscriberAggregator)
    {
        $prevIdx = null;
        foreach (array_keys($revisionsData['history']) as $idx) {
            $next = $revisionsData['history'][$idx];
            $prev = $prevIdx !== null ? $revisionsData['history'][$prevIdx] : null;
            if (!empty($prev) && $prev['sid'] === $next['sid'] && date('Ymd', $prev['from']) === date('Ymd', $next['from'])) {
                $revisionsData['history'][$idx] = Billrun_Utils_Cycle::mergeCycleRevisions($next, [$prev]);
                unset($revisionsData['history'][$prevIdx]);
                $this->merged++;
            }
            $prevIdx = $idx;
        }
    }
}
