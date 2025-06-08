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

    /**
     * Returns the current time as a MongoDB UTCDateTime object.
     *
     * @return \MongoDB\BSON\UTCDateTime The current time in milliseconds since the Unix epoch.
     */
    public static function CurrentTime(){
       return  new \MongoDb\BSON\UTCDateTime(time() * 1000);
    }
    /**
     * Verifies that the specified MongoDB collection contains the expected number of documents
     * matching the given criteria.
     *
     * @param string $collection The name of the MongoDB collection to check.
     * @param int $count The expected number of documents in the collection.
     * @param array $criteria The criteria to filter documents in the collection.
     *
     * @return void
     */
    public function verifyCollectionCount($collection, $count,array $criteria) {
        $this->getModule('MongoDb')->seeNumElementsInCollection($collection,$count, $criteria);
    }

    
}

