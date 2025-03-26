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

    public static function CurrentTime(){
       return  new \MongoDb\BSON\UTCDateTime(time() * 1000);
    }
    /**
     * Convert a string date to MongoDB UTCDateTime format
     * 
     * @param string $dateString Date string in Y-m-d H:i:s format
     * @return \MongoDB\BSON\UTCDateTime
     */
    public static function stringToMongoDate($dateString) {
        $timestamp = strtotime($dateString);
        return new \MongoDB\BSON\UTCDateTime($timestamp * 1000);
    }
    
    /**
     * Verify records in collection with date fields
     * Automatically converts string dates to MongoDB UTCDateTime format
     * 
     * @param string $collection Name of collection
     * @param array $criteria Search criteria
     * @return void
     */
    public function verifyCollectionRecordWithDates($collection, array $criteria) {
        // Process criteria to convert any date strings to MongoDB format
        $processedCriteria = [];
        
        foreach ($criteria as $key => $value) {
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                // This looks like a date string, convert it
                $processedCriteria[$key] = self::stringToMongoDate($value);
            } else {
                $processedCriteria[$key] = $value;
            }
        }
        
        $this->getModule('MongoDb')->seeInCollection($collection, $processedCriteria);
    }
}

