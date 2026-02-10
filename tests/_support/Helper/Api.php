<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Configuration;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\TestInterface;

class Api extends \Codeception\Module
{

    public static function cleanDB() {

        $subs = \Billrun_Factory::db()->subscribersCollection();
        $subs->remove(['_id'=>['$exists' => true]]);
        $lines = \Billrun_Factory::db()->linesCollection();
        $lines->remove(['_id'=>['$exists' => true]]);
        $queue = \Billrun_Factory::db()->queueCollection();
        $queue->remove(['_id'=>['$exists' => true]]);
        $services = \Billrun_Factory::db()->servicesCollection();
        $services->remove(['_id'=>['$exists' => true]]);
        $plans = \Billrun_Factory::db()->plansCollection();
        $plans->remove(['_id'=>['$exists' => true]]);
        $discounts = \Billrun_Factory::db()->discountsCollection();
        $discounts->remove(['_id'=>['$exists' => true]]);
        $billruns =\Billrun_Factory::db()->billrunCollection();
        $billruns->remove(['_id'=>['$exists' => true]]);
        $billing_cycleCollection = \Billrun_Factory::db()->billing_cycleCollection();
        $billing_cycleCollection->remove(['_id'=>['$exists' => true]]);
        $rates = \Billrun_Factory::db()->ratesCollection();
        $rates->remove(['_id'=>['$exists' => true]]);
    }

}
