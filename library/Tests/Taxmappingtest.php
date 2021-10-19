<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * Billing calculator for finding tax  of lines
 *
 * @package  calculator
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_TaxMappingTest extends UnitTestCase {

	use Tests_SetUp;

	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
	protected $aggregator;
	protected $message = '';
	protected $defaultOptions = array(
		"type" => "customer",
		"stamp" => "201905",
		"page" => 0,
		"size" => 100,
		'fetchonly' => true,
		'generate_pdf' => 0,
		"force_accounts" => array()
	);
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';
	protected $tests = [
		//Test num 1 a1
		//non tax rate																    
		array('functions' => ['compareCdr'], 'type' => 'cdr', 'row' => array('stamp' => '2a59f077692f3247811d50598b64e892'),
			'expected' => array('aid' => 1, 'sid' => '2', 'tax_data' => ['total_amount' => 0, 'total_tax' => 0, 'taxes' => []])),
		//rate use default tax
		array('functions' => ['compareCdr'], 'type' => 'cdr', 'row' => array('stamp' => '0f721f8984107a5cc3e3e3a6b06babd2'),
			'expected' => array('aid' => 1, 'sid' => '2',
				'tax_data' => ['total_amount' => 17, 'total_tax' => 0.17, 'taxes' => [["tax" => 0.17, "amount" => 17, "key" => "DEFAULT_VAT"]]])),
		//rate use global tax
		array('functions' => ['compareCdr'], 'type' => 'cdr', 'row' => array('stamp' => '54c97046cd6cfcd877f99783e1f48d2c'),
			'expected' => array('aid' => 1, 'sid' => '2',
				'tax_data' => ['total_amount' => 17, 'total_tax' => 0.17, 'taxes' => [["tax" => 0.17, "amount" => 17, "key" => "DEFAULT_VAT"]]])),
		//rate overide global tax mppping 
		array('functions' => ['compareCdr'], 'type' => 'cdr', 'row' => array('stamp' => '7d9ea6298c17a9336410592f09fb1c65'),
			'expected' => array('aid' => 1, 'sid' => '2',
				'tax_data' => ['total_amount' => 1, 'total_tax' => 0.1, 'taxes' => [["tax" => 0.1, "amount" => 1, "key" => "A"]]])),
		//rate fallback  -Apply if tax rate not found via global mapping rules *** use fallback ***
		array('functions' => ['compareCdr'], 'type' => 'cdr', 'row' => array('stamp' => '169788ab8db858b08659e74db90185f5'),
			'expected' => array('aid' => 1, 'sid' => '2',
				'tax_data' => ['total_amount' => 1, 'total_tax' => 0.1, 'taxes' => [["tax" => 0.1, "amount" => 1, "key" => "A"]]])),
	//rate fallback  - Apply if tax rate not found via global mapping rules *** use global ***
		array('functions' => ['compareCdr'], 'type' => 'cdr', 'row' => array('stamp' => '7d9ea6298c17a9336410592f09fb1c66'),
			'expected' => array('aid' => 1, 'sid' => '3',
				'tax_data' => ['total_amount' => 1, 'total_tax' => 0.1, 'taxes' => [["tax" => 0.1, "amount" => 1, "key" => "A"]]])),
   		//cycle
		//subscriber with non tax plan service discount 
		array('functions' => ['compareCycleLines'], 'type' => 'cycle', 'test' => array('aid' => 1, 'options' => ['stamp' => '201905']),
			'expected' => array('aid' => 1, 'sid' => '2',
				'lines' => [
					'credits' => ['NOT_VAT_PLANANDSERVICE' => ['tax_data' => ['total_amount' => 0, 'total_tax' => 0]]],
					'flat' => ['NONTAX_PLAN' => ['tax_data' => ['total_amount' => 0, 'total_tax' => 0]]],
					'services' => ['NONTAX_SERVICE' => ['tax_data' => ['total_amount' => 0, 'total_tax' => 0]]],
				]
			)),
		//subscriber with "use global" plan service discount 
		array('functions' => ['compareCycleLines'], 'type' => 'cycle', 'test' => array('aid' => 9, 'options' => ['stamp' => '201905']),
			'expected' => array('aid' => 9, 'sid' => '10',
				'lines' => [
					'credits' => ['USE_GLOBAL_PLANANDSERVICE' => ['tax_data' => ['total_amount' => -1.7, 'total_tax' => 0.17]]],
					'flat' => ['USE_GLOBAL_TAX_PLAN' => ['tax_data' => ['total_amount' => 17, 'total_tax' => 0.17]]],
					'services' => ['USE_GLOBAL_TAX_SERVICE' => ['tax_data' => ['total_amount' => 1.7, 'total_tax' => 0.17]]],
				]
			)),
		//subscriber with "OVERRIDE_GLOBAL" plan service discount - the discount has 2 taxes 1 plan and second for service with diffrent taxes
		array('functions' => ['compareCycleLines'], 'type' => 'cycle', 'test' => array('aid' => 11, 'options' => ['stamp' => '201905']),
			'expected' => array('aid' => 11, 'sid' => '12',
				'lines' => [
					'credits' => ['OVERRIDE_GLOBAL_TAX_PLANANDSERVICE' => ['tax_data' => ['total_amount' => -1.5, 'total_tax' => 0.15,
						'taxes' => [["tax" => 0.1, "amount" => -0.5, "key" => "A"],["tax" => 0.2, "amount" => -1, "key" => "B"]]]]],
					'flat' => ['OVERRIDE_GLOBAL_TAX_PLAN' => ['tax_data' => ['total_amount' => 10, 'total_tax' => 0.1]]],
					'services' => ['OVERRIDE_GLOBAL_TAX_SERVICE' => ['tax_data' => ['total_amount' => 2, 'total_tax' => 0.2]]],
				]
			)),
		//subscriber with "use fallback" plan service 
		array('functions' => ['compareCycleLines'], 'type' => 'cycle', 'test' => array('aid' => 13, 'options' => ['stamp' => '201905']),
			'expected' => array('aid' => 13, 'sid' => '14',
				'lines' => [
					'flat' => ['FALLBACK_TAX_PLAN' => ['tax_data' => ['total_amount' => 10, 'total_tax' => 0.1]]],
					'services' => ['FALLBACK_TAX_SERVICE' => ['tax_data' => ['total_amount' => 2, 'total_tax' => 0.2]]],
				]
			)),	
                //Test rounding rules
                //rounding up (without decimals)
                array('functions' => ['checkRounding'], 'type' => 'cdr', 'row' => array('stamp' => '900f025a36c8ce1e32eda0a8b24e9a69'),
			'expected' => array('final_charge' => 2, 'aprice' => 1.7094017094017095, 'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5], 'tax_data' => ['total_amount' => 0.2905982905982906,  'total_amount_before_rounding' => 0.255])),
	];
	

	public function __construct($label = false) {
     
		parent::__construct("test tax");
       
        $request= new Yaf_Request_Http;
        $this->overrideDB = $request->get( 'overrideDB' );
		date_default_timezone_set('Asia/Jerusalem');
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->construct(basename(__FILE__, '.php'), ['discounts', 'taxes']);
		$this->setColletions();
		$this->loadDbConfig();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'tax', 'autoload' => false));
	}

	public function addExpected($expected) {
		foreach ($expected as $k => $v) {
			if (is_array($v)) {
				$this->addExpected($v);
			} else {
				$this->message .= $k . ":" . $v . '<br>';
			}
		}
	}

	public function loadAggregator($row) {
		$options = array_merge($this->defaultOptions, $row['test']['options']);
		$this->aggregator = Billrun_Aggregator::getInstance($options);
		$this->aggregator->load();
		$this->aggregator->aggregate();
	}

	public function RunAggregator() {
		$this->aggregator->aggregate();
	}

	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}
   
	public function TestPerform() {
		foreach ($this->tests as $key => $row) {
			if (!isset($row['expected']['lines'])) {
				$this->message .= '<span style="font: 14px arial; color: rgb(0, 0, 80);"> ' . ($key + 1) . ' </br><b> Expected: </b> ';
				$this->addExpected($row['expected']);
				$aid = $row['expected']['aid'];
				$this->message .= '<b> Result: </b> <br>';
			}
			// run fenctions before the test begin 
			if (isset($row['preRun']) && !empty($row['preRun'])) {
				$preRun = $row['preRun'];
				if (!is_array($preRun)) {
					$preRun = array($row['preRun']);
				}
				foreach ($preRun as $pre) {
					$this->$pre($key, $row);
				}
			}

			if ($row['type'] == 'cycle') {
				// run aggregator
				if (array_key_exists('aid', $row['test'])) {
					$updatedRow = $this->runCycle($row);
				} else {
					continue;
				}
			} elseif ($row['type'] == 'cdr') {
				$updatedRow = $this->runCdr($row);
			} else {
				continue;
			}
			//run tests functios 
			if (isset($row['functions'])) {
				$function = $row['functions'];
				if (!is_array($function)) {
					$function = array($row['test']['functions']);
				}
				foreach ($function as $func) {
					$this->assertTrue($this->$func($key, $updatedRow, $row));
				}
			}

			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
		}

		print_r($this->message);
		if (!$this->overrideDB) {
			$this->restoreColletions();
		}
	}

	protected function runCycle($row) {
		$id = isset($row['test']['aid']) ? $row['test']['aid'] : 0;
		$billrun = (isset($row['test']['options']['stamp'])) ? $row['test']['options']['stamp'] : $this->defaultOptions['stamp'];
		$this->loadAggregator($row);
		$this->RunAggregator();
		$query = array('aid' => $id, "billrun" => $billrun);
		$entityAfter = $this->getLines($query);
		return ($entityAfter);
	}

	/**
	 * 
	 * @param array $row current test case current test case
	 * @return array $alllines return all lines  of aid in specific billrun_key 
	 */
	public function getLines($query) {
		$allLines = [];

		foreach ($this->linesCol->query($query)->cursor() as $line) {
			$allLines[] = $line->getRawData();
		}
		return $allLines;
	}
    /**
	 * run tax calculator 
	 * @param array $row the test case
	 * @return arrray 
	 */
	protected function runCdr($row) {
		$stamp = $row['row']['stamp'];
		$entity = $this->linesCol->query(array('stamp' => $stamp))->cursor()->current();
		$this->calculator->updateRow($entity);
		$this->calculator->writeLine($entity, '123');
		$entityAfter = $entity->getRawData();
		return ($entityAfter);
	}

	/**
	 * sent cycle line to compareCdr for compare all test details vs line
	 * @param int $key num of test case
	 * @param array $returnRow the line after cusotmer calculator
	 * @param array $row the test details
	 * @return boolean ture if pass ,false if fail
	 */
	public function compareCycleLines($key, $updatedRow, $row) {
		$this->message .= 'sid : '.$row['expected']['sid'].'<br>';
		$lines['credit'] = isset($row['expected']['lines']['credits']) ? $row['expected']['lines']['credits'] : '';
		$lines['flat'] = $row['expected']['lines']['flat'] ? $row['expected']['lines']['flat'] : '';
		$lines['services'] = $row['expected']['lines']['services'] ? $row['expected']['lines']['services'] : '';
		$rowExists = 0;
		foreach ($lines as $type => $line) {
			foreach ($line as $key => $cdr) {
				$newRow['expected'] = $cdr;
				$newRow['expected']['taxes']['key'] = $key;
				$returnUpdateRow = $this->getRow($lines, $type, $key, $updatedRow);
				if ($returnUpdateRow) {
					$this->message .= 'Expected ' . $type . ' line : ' . $key . '<br>' . 'total_amount : ' . $line[$key]['tax_data']['total_amount'] . '<br>' . 'tottal_tax :' . $line[$key]['tax_data']['total_tax'] . '<br>';
					$passed = $this->compareCdr($key, $returnUpdateRow, $newRow);
					if(!$passed)
					$this->assertTrue(false);
				}
			}
		}
		return $passed;
	}
    
	public function getRow($lines, $type, $key, $updatedRow) {
		$updateLine = current(array_filter($updatedRow, function(array $line) use ($lines, $type, $key) {
				if ($line['type'] == $type && isset($line['key']) && $line['key'] == $key || $line['name'] == $key)
					return $line;
			}));
		return $updateLine;
	}

	/**
	 * compare all test details vs line
	 * @param int $key num of test case
	 * @param array $returnRow the line after cusotmer calculator
	 * @param array $row the test details
	 * @return boolean ture if pass ,false if fail
	 */
	protected function compareCdr($key, $returnRow, $row) {
		$passed = true;
		$epsilon = 0.000001;
		Billrun_Util::isEqual($returnRow['tax_data']['total_amount'], $row['expected']['tax_data']['total_amount'], $epsilon);
		if (isset($row['expected']['tax_data'])) {
			if (!Billrun_Util::isEqual($returnRow['tax_data']['total_amount'], $row['expected']['tax_data']['total_amount'], $epsilon)) {
				$passed = false;
				$this->message .= "worng total_amount {$returnRow['tax_data']['total_amount'] } " . $this->fail;
			} else {
				$this->message .= "total_amount is :  {$returnRow['tax_data']['total_amount'] } " . $this->pass;
			}
			if (!Billrun_Util::isEqual($returnRow['tax_data']['total_tax'], $row['expected']['tax_data']['total_tax'], $epsilon)) {
				$passed = false;
				$this->message .= "worng total_tax {$returnRow['tax_data']['total_tax'] } " . $this->fail;
			} else {
				$this->message .= "total_tax is :  {$returnRow['tax_data']['total_tax'] } " . $this->pass;
			}
		}
		if (!empty($row['expected']['tax_data']['taxes'])) {
			foreach ($row['expected']['tax_data']['taxes'] as $index => $taxes){
				$this->message.="Taxes $index : <br>";
			if (!Billrun_Util::isEqual($returnRow['tax_data']['taxes'][$index]['tax'], $taxes['tax'], $epsilon)) {
				$passed = false;
				$this->message .= "worng tax {$returnRow['tax_data']['taxes'][$index]['tax'] } " . $this->fail;
			} else {
				$this->message .= "tax is :  {$returnRow['tax_data']['taxes'][$index]['tax'] } " . $this->pass;
			}
			if (!Billrun_Util::isEqual($returnRow['tax_data']['taxes'][$index]['amount'], $taxes['amount'], $epsilon)) {
				$passed = false;
				$this->message .= "worng amount {$returnRow['tax_data']['taxes'][$index]['amount'] } " . $this->fail;
			} else {
				$this->message .= "amount is : {$returnRow['tax_data']['taxes'][$index]['amount'] } " . $this->pass;
			}
			if ($returnRow['tax_data']['taxes'][$index]['key'] !== $taxes['key']) {
				$passed = false;
				$this->message .= "worng key {$returnRow['tax_data']['taxes'][$index]['key'] } " . $this->fail;
			} else {
				$this->message .= "key is : {$returnRow['tax_data']['taxes'][$index]['key'] } " . $this->pass;
			}
			
				}
		}
		$this->message .= ' </p>';
		return $passed;
	}
        
        
        protected function checkRounding($key, $returnRow, $row) {
		$passed = true;
		$epsilon = 0.000001;

		if (isset($row['expected']['final_charge'])) {
			if (!Billrun_Util::isEqual($returnRow['final_charge'], $row['expected']['final_charge'], $epsilon)) {
				$passed = false;
				$this->message .= "worng final_charge {$returnRow['final_charge'] } " . $this->fail;
			} else {
				$this->message .= "final_charge is :  {$returnRow['final_charge'] } " . $this->pass;
			}
		}
                if (isset($row['expected']['aprice'])) {
			if (!Billrun_Util::isEqual($returnRow['aprice'], $row['expected']['aprice'], $epsilon)) {
				$passed = false;
				$this->message .= "worng aprice {$returnRow['aprice'] } " . $this->fail;
			} else {
				$this->message .= "aprice is :  {$returnRow['aprice'] } " . $this->pass;
			}
		}
                if (isset($row['expected']['before_rounding']['final_charge'])) {
			if (!Billrun_Util::isEqual($returnRow['before_rounding']['final_charge'], $row['expected']['before_rounding']['final_charge'], $epsilon)) {
				$passed = false;
				$this->message .= "worng before rounding final_charge {$returnRow['before_rounding']['final_charge'] } " . $this->fail;
			} else {
				$this->message .= "before rounding final_charge is :  {$returnRow['before_rounding']['final_charge'] } " . $this->pass;
			}
		}
                if (isset($row['expected']['before_rounding']['aprice'])) {
			if (!Billrun_Util::isEqual($returnRow['before_rounding']['aprice'], $row['expected']['before_rounding']['aprice'], $epsilon)) {
				$passed = false;
				$this->message .= "worng before rounding aprice {$returnRow['before_rounding']['aprice'] } " . $this->fail;
			} else {
				$this->message .= "before rounding aprice is :  {$returnRow['before_rounding']['aprice'] } " . $this->pass;
			}
		}
                if (isset($row['expected']['tax_data']['total_amount'])) {
			if (!Billrun_Util::isEqual($returnRow['tax_data']['total_amount'], $row['expected']['tax_data']['total_amount'], $epsilon)) {
				$passed = false;
				$this->message .= "worng tax total_amount {$returnRow['tax_data']['total_amount'] } " . $this->fail;
			} else {
				$this->message .= "tax total_amount is :  {$returnRow['tax_data']['total_amount'] } " . $this->pass;
			}
		}
                if (isset($row['expected']['tax_data']['total_amount_before_rounding'])) {
			if (!Billrun_Util::isEqual($returnRow['tax_data']['total_amount_before_rounding'], $row['expected']['tax_data']['total_amount_before_rounding'], $epsilon)) {
				$passed = false;
				$this->message .= "worng tax total_amount_before_rounding {$returnRow['tax_data']['total_amount_before_rounding'] } " . $this->fail;
			} else {
				$this->message .= "tax total_amount_before_rounding is :  {$returnRow['tax_data']['total_amount_before_rounding'] } " . $this->pass;
			}
		}
		$this->message .= ' </p>';
		return $passed;
	}

}
