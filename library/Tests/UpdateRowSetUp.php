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
    
    protected $importData = ['plans','services','subscribers','rates'];
    
    public function __construct() {
           
        }
        
    public function setColletions()
    {
        $this -> cleanCollection(array('linesCollection','balancesCollection','plansCollection','ratesCollection','subscribersCollection','servicesCollection'));
        
        foreach ($this -> importData as $file)
        {
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

        //$this -> insertToCol($this -> products,'rates');
    }
    protected function fixDates($jsonAr)
    {
        foreach ($jsonAr as $key => $jsonFile)
        {
            foreach ($jsonFile as  $jsonFiled => $value)
            {
             
                if (gettype($value) == 'string')
                {   
                    $value = explode("*", $value);
                    if ((count($value) == 2) && ($value[0] == 'time'))
                    {
                        $value = new MongoDate(strtotime($value[1]));
                        $jsonAr[$key][$jsonFiled] = $value;
                    }
                }
            }
        }
        return $jsonAr;
    }
    protected function insertToCol($items,$col)
    {
        
        
        foreach ($items as $item)
        {
            Billrun_Factory::db()-> execute('db.'.$col.'.insert('.$item.')');
        }
        //Billrun_Factory::db()-> execute($this -> rates);
    }
    protected function cleanCollection($colNames)
    {
        foreach($colNames as $colName )
        {
            $colName = Billrun_Factory::db()->$colName();
            while ($colName->count() > 0)
                {
                    $entity = $colName->query('{}') -> cursor() -> current();
                    $colName->remove($entity);
                }
        }
    }

}

