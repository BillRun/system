<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class All extends \Codeception\Module
{

    
    public static function cleanDB(){
        $collections = [
            'subscribersCollection',
            'linesCollection',
            'queueCollection',
            'servicesCollection',
            'plansCollection',
            'discountsCollection',
            'billrunCollection',
            'billing_cycleCollection',
            'ratesCollection',
            'billsCollection',
            'operationsCollection',
            'balancesCollection',
            'chargesCollection',
            'collection_stepsCollection',
	    'logCollection',
	    'archiveCollection',
	    'ratesCollection'
        ];

        foreach ($collections as $collectionMethod) {
            $collection = \Billrun_Factory::db()->$collectionMethod();
            if ($collection) {
                $collection->remove(['_id' => ['$exists' => true]]);
            }
        }
    }
}
