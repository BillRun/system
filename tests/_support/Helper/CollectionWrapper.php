<?php

namespace Helper;

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
  
}
