<?php
namespace Helper;
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
}

