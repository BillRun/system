<?php
namespace Helper;

use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Current;

class TestHelper extends \Codeception\Module {
    protected $tester;
    protected $defaultConfigTimezone = null;
    protected $defaultSystemTimezone = null;
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
       return  new \MongoDB\BSON\UTCDateTime(time() * 1000);
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

    public function overrideConfigValue($key, $value)
    {
        $config = \Billrun_Factory::db()->configCollection();
		$data = $config->query()
			->cursor()
			->sort(array('urt'=> -1, '_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		unset($data['_id']);
		\Billrun_Util::setIn($data, $key,$value);
        $data['urt'] = new \MongoDB\BSON\UTCDateTime();
		$config->insert($data);
		\Billrun_Config::getInstance()->loadDbConfig();
        // Drop the Billrun_Base singleton cache so the next getInstance()
        // call constructs a fresh object that reads the just-changed config
        // (calculators latch options like bulk at construction time).
        $this->resetBillrunInstances();
    }

    public function setTimezone($timezone)
    {
        if (empty($timezone)) {
            return;
        }
        // Load config so we can store original values and override timezone.
        \Billrun_Factory::config();
        if ($this->defaultConfigTimezone === null) {
            $this->defaultConfigTimezone = $this->getCurrentConfigTimezone();
        }
        if ($this->defaultSystemTimezone === null) {
            $this->defaultSystemTimezone = date_default_timezone_get();
        }

        $currentConfigTimezone = $this->getCurrentConfigTimezone();
        if ($currentConfigTimezone !== $timezone) {
            $this->overrideConfigValue('billrun.timezone', [
                'v' => $timezone,
                't' => 'Timezone',
            ]);
        }

        if (date_default_timezone_get() !== $timezone) {
            date_default_timezone_set($timezone);
        }
    }

    public function restoreTimezone()
    {
        \Billrun_Factory::config();

        if (!empty($this->defaultConfigTimezone)) {
            $currentConfigTimezone = $this->getCurrentConfigTimezone();
            if ($currentConfigTimezone !== $this->defaultConfigTimezone) {
                $this->overrideConfigValue('billrun.timezone', [
                    'v' => $this->defaultConfigTimezone,
                    't' => 'Timezone',
                ]);
            }
        }

        if (!empty($this->defaultSystemTimezone) && date_default_timezone_get() !== $this->defaultSystemTimezone) {
            date_default_timezone_set($this->defaultSystemTimezone);
        }

        $this->defaultConfigTimezone = null;
        $this->defaultSystemTimezone = null;
    }

    protected function getCurrentConfigTimezone()
    {
        $timezone = \Billrun_Factory::config()->getConfigValue('billrun.timezone');
        if (is_array($timezone) && isset($timezone['v'])) {
            return $timezone['v'];
        }

        $timezoneV = \Billrun_Factory::config()->getConfigValue('billrun.timezone.v');
        if (!empty($timezoneV)) {
            return $timezoneV;
        }

        return $timezone;
    }

    /**
     * Clear the Billrun_Base::$instance singleton cache via reflection.
     *
     * Billrun_Base::getInstance() caches every instance it constructs by a
     * hash of its constructor args, so tests that change config a calc reads
     * at construction time (e.g. customer.calculator.bulk, a freshly added
     * file_type) need to drop the cache — otherwise the next getInstance()
     * returns the stale instance built with the old config.
     */
    public function resetBillrunInstances()
    {
        $instances = new \ReflectionProperty('Billrun_Base', 'instance');
        $instances->setAccessible(true);
        $instances->setValue(null, []);

        // Billrun_Factory keeps its OWN process-wide singletons that survive
        // Billrun_Base::$instance clearing — most notably $subscriber and
        // $accounts. These latch on subscribers.subscriber.type at first
        // access, so once a Db subscriber is cached, every subsequent test
        // gets the same Db instance even after the config flips to external.
        // Clear them so the next factory call rebuilds against fresh config.
        foreach (['subscriber', 'accounts'] as $prop) {
            $factoryProp = new \ReflectionProperty('Billrun_Factory', $prop);
            $factoryProp->setAccessible(true);
            $factoryProp->setValue(null, null);
        }
    }

    /**
     * Drive the file processor over the given path, which may be either a
     * single file or a directory of files. A directory is iterated in one
     * processor run so any cached calculators are reused across files (the
     * production behaviour the receiver -> processor pipeline relies on).
     *
     * @param array $options at least 'type' and 'path'
     */
    public function processByPath(array $options)
    {
        $processor = \Billrun_Processor::getInstance($options);
        return $processor->processorByPath($options);
    }

}
