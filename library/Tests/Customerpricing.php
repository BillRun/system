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
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');


define('UNIT_TESTING', 'true');



class _MockBillrun_Calculator_CustomerPricing extends Billrun_Calculator_CustomerPricing {
    protected $arate;
    
    public function _setSids($sids) {
        $this->sidsQueuedForRebalance = $sids;
    }
    
    public function _setArate($arate) {
        $this->arate = $arate;
    }
    
    protected function getRateByRef($arate) {
        return $this->arate;
    }
    public function getRowRate($row) {
        if (array_key_exists('$id',$row['arate']))
        {
        return  parent::getRowRate($row);
        }
        else
        {
        $mockRate = new Billrun_AnObj($row['arate']);
        return $mockRate;
        }
        
    }
    /*public function isLineLegitimate($line) {
		$arate = $this->getRateByRef($line->get('arate', TRUE));
        
        if(is_null($arate)) {
            return false;
        }
        
        if(!empty($arate['skip_calc']) && in_array(self::$type, $arate['skip_calc'])) {
            return false;
        }
            
        if(!isset($line['sid']) || !$line['sid']) {
            return false;
        }
        
        if($line['urt']->sec >= $this->billrun_lower_bound_timestamp) {
            return false;
        }
        return true;
	}*/
    
}
class PricingLine extends Billrun_AnObj {
    function __construct($options) {
        parent::__construct($options);
        
        if(isset($this->data['urt'])) {
            if (gettype($this->data['urt'])=="string"){
                $urt = new MongoDate(strtotime($this->data['urt']));  
                }
            else {
                $urt = new MongoDate($this->data['urt']);
                }
            $this->data['urt'] = $urt;
        }   
	}
    
}
class Tests_Customerpricing extends UnitTestCase {
    /**
     *
     * @var _MockBillrun_Calculator_CustomerPricing
     */
	protected $calculator;
	protected $reflectionCalculator;
    /**
     *
     * @var MockBillrun_Calculator_CustomerPricing
     */
	protected $mockCalculator;
	
	protected $sidsQueuedForRebalance = array(
		1,2,3,4,5,6,7,8,9,10
	);
	
	protected $lineLegitimateTests = array(
			// negative tests
            array("msg" => "'pricing' exist in arate=>skip_calc", 'expected' => false, 
            'line' => array('arate'=>array('skip_calc'=>array('pricing')),'sid'=>12345)),
            array("msg" => "sid is missing", 'expected' => false, 
            'line' => array('arate'=>array('skip_calc'=>array()))),
            array("msg" => "sid is false", 'expected' => false, 
            'line' => array('arate'=>array(),'sid'=>false)),
            array("msg" => "failed on urt compare", 'expected' => false, 
                'line' => array('arate'=>array('skip_calc'=>array()),'sid'=>12345,'urt'=> "10 October 1969")),
            // positive tests
            array("msg" => "valid 1: arate- empty array", 'expected' => true, 
                    'line' => array('arate'=>array(),'sid'=>'abc123','urt'=>"10 September 2000")),
            array("msg" => "valid 2", 'expected' => true, 
                    'line' => array('arate'=>array('skip_calc'=>array('price')),'sid'=>'abc123','urt'=>999999)),
            array("msg" => "valid 3", 'expected' => true, 
                'line' => array('arate'=>array('skip_calc'=>array()),'sid'=>12345,'urt'=>999999))
//			array("msg" => "Empty array", 'line' => array(), 'expected' => false),
//			array("msg" => "Null value", 'line' => array(null), 'expected' => false),		
//			array("msg" => "Zero value", 'line' => array(0), 'expected' => false),		
//			array("msg" => "Integer value", 'line' => array(100000), 'expected' => false),		
//			array("msg" => "String value", 'line' => array("Test String"), 'expected' => false),		
//		
//			array("msg" => "Empty array", 'line' => array('arate'), 'expected' => false),
//			array("msg" => "Null value", 'line' => array('arate' => null), 'expected' => false),		
//			array("msg" => "Zero value", 'line' => array('arate' => 0), 'expected' => false),		
//			array("msg" => "Integer value", 'line' => array('arate' => 100000), 'expected' => false),		
//			array("msg" => "String value", 'line' => array('arate' => "Test String"), 'expected' => false)
		);
    protected $getRowRateTests = array(
            array("msg" => "", 'expected' => true,'line' => array('arate' => array('$id'=>"57ff594024e839201a52fbd3",'$ref'=>'rates'))),
            array("msg" => "", 'expected' => true,'line' => array('arate' => array('$ref'=>'123')))
		);
    /**
     * @var array each element consist of error massage 'msg' (string), expected resault 'expected' (boolean)
     * and 'line'(array) which is transferd to the function 
     * Expected variables in 'line':
     * 'call_offset'— int
     * 'arate' — array functions as rate mongo entity.
     * 'usaget' — usage_type
     * ?'usagev'  or 'charging_type': 'plan','type'
     * 
     */
	protected $updateRowTests = array(
            
            array("msg" => "", 'expected' => false,
             'line' => array('call_offset' => 123 ,'usaget'=>'call','usagev'=>60,
                    'arate' => array('tmpField'=>123)))
			// Simple negative tests
//			array("msg" => "Non existing SID", 'line' => array('sid' => 100), 'expected' => false),		
//			array("msg" => "Non integer SID", 'line' => array('sid' => "100"), 'expected' => false),		
//			array("msg" => "Existing SID", 'line' => array('sid' => 1), 'expected' => false),		
//			array("msg" => "Non integer existing SID", 'line' => array('sid' => "1"), 'expected' => false),
//			array("msg" => "Billable false", 'line' => array('sid' => 1, 'billable' => false), 'expected' => false),
//			array("msg" => "Billable true", 'line' => array('sid' => 1, 'billable' => true), 'expected' => false)
		);
	
	public function __construct($label = false) {
		parent::__construct($label);
		$this->calculator = new _MockBillrun_Calculator_CustomerPricing();
        $this->calculator->_setSids($this->sidsQueuedForRebalance);

//		$this->reflectionCalculator = new ReflectionClass('Billrun_Calculator_CustomerPricing');        
//      $funcGetRateByRef = $this->reflectionCalculator->getMethod('getRateByRef');
//		$reflectionInternalSIDs = $this->reflectionCalculator->getProperty('sidsQueuedForRebalance');
//		$reflectionInternalSIDs->setAccessible(true);
//		$reflectionInternalSIDs->setValue($this->calculator, $this->sidsQueuedForRebalance);
	}
    
	public function testGetRowRate() {
		$passed = 0;
		foreach ($this->getRowRateTests as $test) {
			$line = new Billrun_AnObj($test['line']);
			$result = $this->calculator->getRowRate($line);
			if($this->assertEqual($result, $test['expected'], $test['msg'])) {
				$passed++;
			}
		}
		print("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->getRowRateTests) . " cases.<br>");
    }
    
	public function testLineLegitimate() {
		$passed = 0;
		foreach ($this->lineLegitimateTests as $test) {
			$line = new PricingLine($test['line']);
            $arate = null;
            if(isset($test['line']['arate'])) {
                $arate = $test['line']['arate'];
            }
            $this->calculator->_setArate($arate);
			$result = $this->calculator->isLineLegitimate($line);
			if($this->assertEqual($result, $test['expected'], $test['msg'])) {
				$passed++;
			}
		}
		print("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->lineLegitimateTests) . " cases.<br>");
    }
	
	public function testUpdateRow() {
		$passed = 0;
		foreach ($this->updateRowTests as $test) {
			$line = new Billrun_AnObj($test['line']);
			$result = $this->calculator->updateRow($line);
			if($this->assertEqual($result, $test['expected'], $test['msg'])) {
				$passed++;
			}
		}
		print_r("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->updateRowTests) . " cases.<br>");
    }
    
}
