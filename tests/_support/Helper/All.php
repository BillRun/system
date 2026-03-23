<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class All extends \Codeception\Module
{

    
    public static function cleanDB(){

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
        $charges = \Billrun_Factory::db()->chargesCollection();
        $charges->remove(['_id'=>['$exists' => true]]);
        $billruns =\Billrun_Factory::db()->billrunCollection();
        $billruns->remove(['_id'=>['$exists' => true]]);
        $billing_cycleCollection = \Billrun_Factory::db()->billing_cycleCollection();
        $billing_cycleCollection->remove(['_id'=>['$exists' => true]]);
        $bills = \Billrun_Factory::db()->billsCollection();
        $bills->remove(['_id'=>['$exists' => true]]);
        $collection_steps = \Billrun_Factory::db()->collection_stepsCollection();
        $collection_steps->remove(['_id'=>['$exists' => true]]);
    }
}
