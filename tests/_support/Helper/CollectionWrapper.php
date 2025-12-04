<?php

namespace Helper;
use MongoDB\BSON\UTCDateTime;

/**
 * CollectionWrapper Helper for MongoDB operations in Codeception tests
 */
class CollectionWrapper extends \Codeception\Module
{
    protected function mongodb()
    {
        return $this->getModule('MongoDb');
    }

    /**
     * Grab a single document from a collection
     * 
     * @param string $collection Collection name
     * @param array $criteria Search criteria
     * @return array|null
     */
    public function getFromCollection($collection, $criteria)
    {
        return $this->mongodb()->grabFromCollection($collection, $criteria);
    }

    public function enableCahce()
  {
    $lastConfig = \Billrun_Factory::db()->configCollection();
		$lc = $lastConfig->query()
			->cursor()
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		unset($lc['_id']);
    $lc['cache'] = [
      'Core',
      'File',
      [
        'cache_id_prefix' => 'Billrun',
        "lifetime" => 14400,
        "cache_dir" => "./cache/"
      ]
    ];
    $lastConfig->insert($lc);
    \Billrun_Config::getInstance()->loadDbConfig();
  }

  /**
   * Function to add operation object to db
   * @param string $action - operation action to lock
   * @param mixed $filtration - can be array or string
   * @param $from & $to - DateTime()
   */
  public function addOperationToDb($action, $filtration, $from, $to) {
    $this->mongodb()->haveInCollection('operations', 
                [
                    "action" => $action,
                    "filtration" => $filtration,
                    "start_time" => new UTCDateTime($from),
                    "end_time" => new UTCDateTime($to)
                ]
            );
  }
  
}
