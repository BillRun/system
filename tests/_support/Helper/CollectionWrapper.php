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
  
}