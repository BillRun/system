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
     * Convert a string date to MongoDB UTCDateTime format
     * 
     * @param string $dateString Date string in Y-m-d H:i:s format
     * @return \MongoDB\BSON\UTCDateTime
     */
    public static function stringToMongoDate($dateString) {
        $timestamp = strtotime($dateString);
        return new \MongoDB\BSON\UTCDateTime($timestamp * 1000);
    }

    /*
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

   
    /**
     * Verify records in collection with automatic date conversion
     * 
     * automatically converts the passed date strings
     * to MongoDB format. Useful when your test data uses readable
     * dates like '2023-12-01 14:30:00' instead of MongoDB date objects.
     * 
     * Only converts top-level date strings matching YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
     * 
     * @param string $collection MongoDB collection name
     * @param array $criteria Search criteria (date strings will be auto-converted)
     * 
     * @example
     * $I->verifyCollectionRecordWithDates('bills', [
     *     'urt' => '2023-12-01 10:30:00',  // converted to MongoDB  UTCDateTime
     *     'paid' => true                  // left as-is
     * ]);
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

