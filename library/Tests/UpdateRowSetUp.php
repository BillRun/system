<?php

/*
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for the config module
 *
 * @package         Tests
 * @subpackage      Config
 * @since           4.4
 */

class Tests_UpdateRowSetUp  {
    
	/**
	 * json files names in data dir
	 * each file will be added to the relevant collection.
	 * 
	 * @var array
	 */
	protected $collectionToClean = ['plans', 'services','subscribers','rates','lines','balances'];
    protected $importData = ['plans', 'services','subscribers','rates'];
    protected $backUpData=array();
    public function __construct() {
           
        }
	/**
	 * executes set up for update row test
	 */   
    public function setColletions(){
		$this -> backUpCollection($this -> collectionToClean);
        $this -> cleanCollection($this -> collectionToClean);
		
        foreach ($this -> importData as $file){
            $dataAsText = file_get_contents( dirname(__FILE__).'/data/'.$file.'.json');
            $parsedData = json_decode($dataAsText, true);
            if($parsedData === null) {
                echo('Cannot decode <span style="color:#ff3385; font-style: italic;">' . $file . '.json. </span> <br>');
                continue;
            }
            $data = $this -> fixDates($parsedData['data']);
            $coll = Billrun_Factory::db()->$parsedData['collection']();
            $coll->batchInsert($data);
        }
		
       /*$dir = dirname(__FILE__).'/data';
       $files1 = scandir($dir);*/
    }
	
	public function restoreColletions(){
		$this -> cleanCollection($this -> collectionToClean);
		$this ->restoreCollection();
	}
	
	/**
	 * tranform all fields starts with time* into MongoDate object
	 * @param array $jsonAr 
	 */
    protected function fixDates($jsonAr)
    {
        foreach ($jsonAr as $key => $jsonFile){
            foreach ($jsonFile as  $jsonFiled => $value){
                if (gettype($value) == 'string'){   
                    $value = explode("*", $value);
                    if ((count($value) == 2) && ($value[0] == 'time')){
                        $value = new MongoDate(strtotime($value[1]));
                        $jsonAr[$key][$jsonFiled] = $value;
                    }
                }
            }
        }
        return $jsonAr;
    }
	
	// old function for inserting data
    protected function insertToCol($items,$col){ 
        foreach ($items as $item)
        {
            Billrun_Factory::db()-> execute('db.'.$col.'.insert('.$item.')');
        }
    }
	
	/**
	 * @param array $colNames array of collectins names to clean
	 */
    protected function cleanCollection($colNames){
		
        foreach($colNames as $colName ){
			Billrun_Factory::db()-> execute('db.'.$colName.'.remove({})');
        }
    }
	
	 protected function backUpCollection($colNames){
		foreach($colNames as $colName ){
			$colName=$colName.'Collection';
			$items = iterator_to_array(Billrun_Factory::db()->$colName()->query(array())->getIterator());
			$this -> backUpData[$colName]=array();
			foreach($items as $item){
				array_push($this -> backUpData[$colName],$item -> getRawData());
			}
		}

    }
	
	protected function restoreCollection(){
		foreach($this -> backUpData as $colName=>$items){
			if (count($items)>0)
			{
				Billrun_Factory::db()->$colName()->batchInsert($items);
			}
			
		}
		
	}
	
}

