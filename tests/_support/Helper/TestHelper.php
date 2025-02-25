<?php
namespace Helper;

use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Current;

class TestHelper extends \Codeception\Module {
    protected $tester;
    /**
     * Verify records in collection (warp to Codeception method seeInCollection)
     * 
     * @param string $collection Name of collection
     * @param array $criteria Search criteria
     * @return void
     */
    public function verifyCollectionRecord($collection, array $criteria) {
        $this->getModule('MongoDb')->seeInCollection($collection, $criteria);
    }

    public static function CurrentTime(){
       return  new \MongoDb\BSON\UTCDateTime(time() * 1000);
    }

    public function removeCollectionRecord($collection, array $criteria){

        $collection = \Billrun_Factory::db()->getCollection($collection);
		if (!($collection instanceof \Mongodloid_Collection)) {
			return false;
		}
		if (empty($criteria)) {
			return;
		}
		$collection->remove($criteria);
	
    }
    
}

