<?php

namespace Helper;

/**
 * CollectionWrapper Helper for MongoDB operations in Codeception tests
 */
use Exception;
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

    public function enableCahce() {
      $this->insertToConfig(['cache' => [
      'Core',
      'File',
      [
        'cache_id_prefix' => 'Billrun',
        "lifetime" => 14400,
        "cache_dir" => "./cache/"
      ]
    ]]);
    }
  
    /**
     * Function to insert data to billrun config object
     * @param array $data - data to insert, structure should be [$key_in_config => $value (can be string/number/array)]
     * @param int $add_unique_object_to_array - will be set as true if the added data is an object in array of objects (e.g pg, input processor etc). Object will be uniquely added
     * @param string $unique_field - unique field name - must be set if $add_unique_object_to_array is true - by this field the object will be uniquely added
     */
  public function insertToConfig($data, $add_unique_object_to_array = 0, $unique_field = null) {
    $config = \Billrun_Factory::db()->configCollection();
		$lc = $config->query()
			->cursor()
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		unset($lc['_id']);
    $merge = true;
    $object_exists = false;
    if ($add_unique_object_to_array) {
      if (is_null($unique_field)) {
        throw new Exception("Insert unique object to config without defining the field that determines its uniqueness");
      }
      $data_key = current(array_keys($data));
      if (isset($lc[$data_key])) {
        foreach($lc[$data_key] as $index => $object) {
          if ($object[$unique_field] == $data[$data_key][$unique_field]) {
            $lc[$data_key][$index] = $data[$data_key];
            $object_exists = true;
            $merge = false;
            break;
          }
        }
      }
      if (!$object_exists) {
        array_push($lc[$data_key], $data[$data_key]);
        $merge = false;
      }
    }
    if ($merge) {
      $lc = array_merge($lc, $data);
    }
    $config->insert($lc);
    \Billrun_Config::getInstance()->loadDbConfig();
  }

}
