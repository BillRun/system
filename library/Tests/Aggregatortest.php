<?php

 /**
  * @package         Billing
  * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
  * @license         GNU Affero General Public License Version 3; see LICENSE.txt
  */
 /**
  * 
  * @package  calculator
  * @since    0.5
  */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

 define('UNIT_TESTING', 'true');

 class Tests_Aggregator extends UnitTestCase {

     use Tests_SetUp;

     protected $fails;
     protected $ratesCol;
     protected $plansCol;
     protected $linesCol;
     protected $servicesCol;
     protected $discountsCol;
     protected $subscribersCol;
     protected $balancesCol;
     protected $billrunCol;
     protected $BillrunObj;
     protected $returnBillrun;
     public $ids;
     public $message;
     public $label = 'aggregate';
     public $defaultOptions = array(
         "type" => "customer",
         "stamp" => "201806",
         "page" => 0,
         "size" => 100,
         'fetchonly' => true,
         'generate_pdf' => 0,
         "force_accounts" => array()
     );
     public $LatestResults;
     public $sumBillruns;
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span><br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span><br>';
	public function test_cases() {
    
	}

     public function __construct($label = false) {
         parent::__construct("test Aggregatore");
         $this->autoload_tests('aggregatorTestCases');
         $this->ratesCol = Billrun_Factory::db()->ratesCollection();
         $this->plansCol = Billrun_Factory::db()->plansCollection();
         $this->linesCol = Billrun_Factory::db()->linesCollection();
         $this->servicesCol = Billrun_Factory::db()->servicesCollection();
         $this->discountsCol = Billrun_Factory::db()->discountsCollection();
         $this->subscribersCol = Billrun_Factory::db()->subscribersCollection();
         $this->balancesCol = Billrun_Factory::db()->discountsCollection();
	 $this->billingCyclr = Billrun_Factory::db()->billing_cycleCollection();
         $this->billrunCol = Billrun_Factory::db()->billrunCollection();
         $this->construct(basename(__FILE__, '.php'), ['bills','charges', 'billing_cycle', 'billrun', 'counters', 'discounts', 'taxes']);
         $this->setColletions();
         $this->loadDbConfig();
     }

     public function loadDbConfig() {
         Billrun_Config::getInstance()->loadDbConfig();
     }

     /**
      * 
      * @param array $row current test case
      */
     public function aggregator($row) {
         $options = array_merge($this->defaultOptions, $row['test']['options']);
         $aggregator = Billrun_Aggregator::getInstance($options);
         $aggregator->load();
         $aggregator->aggregate();
     }

     /**
      * 
      * @param $query
      * @return billrun objects by query or all if query is null
      */
     public function getBillruns($query = null) {
         return $this->billrunCol->query($query)->cursor();
     }

     /**
      * the function is runing all the test cases  
      * print the test result
      * and restore the original data 
      */
    public function TestPerform()
    {
        $this->tests =  $this->getTestCases($this->tests);
        if (empty($this->test_cases_to_run)) {
            $this->tests = $this->skip_tests($this->tests, 'test.test_number');
          }
         foreach ($this->tests as $key => $row) {

             $aid = $row['test']['aid'];
	     $this->message .= "<span id={$row['test']['test_number']}>test number : " . $row['test']['test_number'] . '</span><br>';
	    if (isset($row['test']['label'])) {
	         $this->message .= '<br>test label :  ' . $row['test']['label'];
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
             // run aggregator
             if (array_key_exists('aid', $row['test'])) {
                 $returnBillrun = $this->runT($row);
             }
             //run tests functios 
             if (isset($row['test']['function'])) {
                 $function = $row['test']['function'];
                 if (!is_array($function)) {
                     $function = array($row['test']['function']);
                 }
                 foreach ($function as $func) {
					$testFail = $this->assertTrue($this->$func($key, $returnBillrun, $row));
					if (!$testFail) {
						$this->fails .= "|---|<a href='#{$row['test']['test_number']}'>{$row['test']['test_number']}</a>";
					}
                 }
             }
             $this->saveLatestResults($returnBillrun, $row);
             $post = (isset($row['postRun']) && !empty($row['postRun'])) ? $row['postRun'] : null;

             // run functions after the test run 
             if (!is_array($post) && isset($post)) {
                 $post = array($row['postRun']);
             }
             if (!is_null($post)) {
                 foreach ($post as $func) {
                     $this->$func($returnBillrun, $row);
                 }
             }
             $this->message .= '<p style="border-top: 1px dashed black;"></p>';
         }
		if ($this->fails) {
			$this->message .= $this->fails;
         }
         print_r($this->message);
        $this->restoreColletions();
     }

     /**
      * run aggregation on current test case and return its billrun object/s
      * @param array $row current test case 
      * @return  Mongodloid_Entity|array $entityAfter billrun object/s 
      */
     protected function runT($row) {
         $id = isset($row['test']['aid']) ? $row['test']['aid'] : 0;
         $billrun = (isset($row['test']['options']['stamp'])) ? $row['test']['options']['stamp'] : $this->defaultOptions['stamp'];
         $this->aggregator($row);
         $query = array('aid' => $id, "billrun_key" => $billrun);
         $entityAfter = $this->getBillruns($query)->current();
         return ($entityAfter);
     }

     /**
      * 
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case 
      * @return boolean true if the test is pass and false if the tast is fail 
      */
     protected function basicCompare($key, $returnBillrun, $row) {
         $passed = TRUE;
         $billrun_key = $row['expected']['billrun']['billrun_key'];
         $aid = $row['expected']['billrun']['aid'];
         $retun_billrun_key = isset($returnBillrun['billrun_key']) ? $returnBillrun['billrun_key'] : false;
         $retun_aid = isset($returnBillrun['aid']) ? $returnBillrun['aid'] : false;
         $jiraLink = isset($row['jiraLink']) ? (array) $row['jiraLink'] : '';
         foreach ($jiraLink as $link) {
			$this->message .= '<br><a target="_blank" href=' . "'" . $link . "'>issus in jira :" . $link . "</a>";
         }
		$this->message .= '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . '<b> Expected: </b><br> ' . '— aid : ' . $aid . '<br> — billrun_key: ' . $billrun_key;
		$this->message .= '<br><b> Result: </b> <br>';
         if (!empty($retun_billrun_key) && $retun_billrun_key == $billrun_key) {
             $this->message .= 'billrun_key :' . $retun_billrun_key . $this->pass;
         } else {
             $passed = false;
             $this->message .= 'billrun_key :' . $retun_billrun_key . $this->fail;
         }
         if (!empty($retun_aid) && $retun_aid == $aid) {
             $this->message .= 'aid :' . $retun_aid . $this->pass;
         } else {
             $passed = false;
             $this->message .= 'aid :' . $retun_aid . $this->fail;
         }
		return $passed;
	}

	public function checkInvoiceId($key, $returnBillrun, $row) {
		$passed = TRUE;
		$invoice_id = $row['expected']['billrun']['invoice_id'] ? $row['expected']['billrun']['invoice_id'] : null;
		$retun_invoice_id = $returnBillrun['invoice_id'] ? $returnBillrun['invoice_id'] : false;
         if (isset($invoice_id)) {
             
             if (!empty($retun_invoice_id) && $retun_invoice_id == $invoice_id) {
                 $this->message .= 'invoice_id :' . $retun_invoice_id . $this->pass;
             } else {
                 $passed = false;
                 $this->message .= 'invoice_id :' . $retun_invoice_id . $this->fail;
             }
         } else {
			if (!empty($retun_invoice_id) && $retun_invoice_id == $this->LatestResults[0][0]['invoice_id'] + 1) {
                 $this->message .= 'invoice_id :' . $retun_invoice_id . $this->pass;
             } else {
                 $passed = false;
                 $this->message .= 'invoice_id :' . $retun_invoice_id . $this->fail;
             }
         }

         return $passed;
     }

     /**
      * check if all subscribers was calculeted
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function sumSids($key, $returnBillrun, $row) {
         $this->message .= "<b> sum sid's :</b> <br>";
         if (count($row['test']['sid']) == count($returnBillrun['subs']) - 1) {
             $this->message .= "subs equle to sum of sid's" . $this->pass;
             return true;
         } else {
             $this->message .= "subs isn't equle to sum of sid's" . $this->fail;
             return FALSE;
         }
     }

     /**
      *  check the price before and after vat
      * 
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function totalsPrice($key, $returnBillrun, $row) {
         $passed = TRUE;
         $this->message .= "<b> total Price :</b> <br>";
         if (Billrun_Util::isEqual($returnBillrun['totals']['after_vat'], $row['expected']['billrun']['total'], 0.00001)) {
             $this->message .= "total after vat is : " . $returnBillrun['totals']['after_vat'] . $this->pass;
         } else {
             $this->message .= "expected total after vat is : {$row['expected']['billrun']['total']} <b>result is </b>: {$returnBillrun['totals']['after_vat']}" . $this->fail;
             $passed = FALSE;
         }
         $vatable = (isset($row['expected']['billrun']['vatable']) ) ? $row['expected']['billrun']['vatable'] : null;
         if ($vatable <> 0) {
             $vat = $this->calcVat($returnBillrun['totals']['before_vat'], $returnBillrun['totals']['after_vat'], $vatable);
             if (Billrun_Util::isEqual($vat, $row['expected']['billrun']['vat'], 0.00001)) {
                 $this->message .= "total befor vat is : " . $returnBillrun['totals']['before_vat'] . $this->pass;
             } else {
                 $this->message .= "expected total befor vat is : {$row['expected']['billrun'] ['vatable']} <b>result is </b>:  {$returnBillrun['totals']['before_vat']}" . $this->fail;
                 $passed = FALSE; /* Percentage of tax */
             }
             $this->message .= "Percentage of tax :$vat %<br>";
         }
         return $passed;
     }

     /* return the percent of the vat */

     /**
      * 
      * @param $beforVat
      * @param $aftetrVat
      * @param $vatable
      * @return vat
      */
     public function calcVat($beforVat, $aftetrVat, $vatable = null) {
         $i = $aftetrVat - $beforVat;
         if (!empty($vatable)) {
             return ($i / $vatable) * 100;
         } else {
             return ($i / $beforVat) * 100;
         }
     }

     /* save Latest 3 Results  */

     /**
      * 
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation is the billrun object of current test after aggregation
      * @param array $row current test case
      */
     public function saveLatestResults($returnBillrun, $row) {
         $lest = array($returnBillrun, $row);
         if (!empty($this->LatestResults[0])) {
             if (!empty($this->LatestResults[1])) {
                 $this->LatestResults[2] = $this->LatestResults[1];
                 $this->LatestResults[1] = $this->LatestResults[0];
             } else {
                 $this->LatestResults[1] = $this->LatestResults[0];
             }
         }
         $this->LatestResults[0] = $lest;
     }

     /**
      * 
      * @param array $row current test case current test case
      * @return array $alllines return all lines  of aid in specific billrun_key 
      */
     public function getLines($row) {
         $stamp = (isset($row['test']['options']['stamp'])) ? $row['test']['options']['stamp'] : $this->defaultOptions['stamp'];
         $query = array('billrun' => $stamp, 'aid' => $row['test']['aid']);
         $allLines = [];
         $linesCollection = Billrun_Factory::db()->linesCollection();
         $lines = $linesCollection->query($query)->cursor();
         foreach ($lines as $line) {
             $allLines[] = $line->getRawData();
         }
         return $allLines;
     }

     /**
      * check if all the lines was created 
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function lineExists($key, $returnBillrun, $row) {
         $passed = true;
         $this->message .= "<b> create lines: </b> <br>";
         $types = $row['expected']['line']['types'];
         $lines = $this->getLines($row);
         $returnTypes = [];
         foreach ($lines as $line) {
             $returnTypes[] = $line['type'];
         }
         $diff = array_diff($types, $returnTypes);
         if (!empty($diff)) {
             $passed = FALSE;
             $this->message .= "these lines aren't created : ";
             foreach ($diff as $dif) {
                 $this->message .= $dif . '<br>';
             }
             $this->message .= $this->fail;
         } elseif (empty($diff) && empty($returnTypes) && !empty($row['expected']['line'])) {
             $this->message .= "no lines created" . $this->fail;
             $passed = FALSE;
         } elseif (empty($diff) && empty($returnTypes) && empty($row['expected']['line'])) {
             /* its for function billrunNotCreated */
         } else {
             $this->message .= "all lines created" . $this->pass;
         }
         $this->numOffLines = count($returnTypes);
         return $passed;
     }

     /**
      * 
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean return pass if the billrun was not created
      */
     public function billrunNotCreated($key, $returnBillrun = null, $row) {
         $passed = true;
         $this->lineExists($key, $returnBillrun = null, $row);
         if ($this->numOffLines > 0) {
             $passed = false;
             $this->message .= "lines was created for account {$row['test']['aid']} But they should not have been formed" . $this->fail;
         } else {
             $this->message .= "lines wasn't created for account {$row['test']['aid']} Because they should not have been created" . $this->pass;
         }
         return $passed;
     }

     /**
      * change and reload Config 
      * @param int $key number of the test case
      * @param array $row current test case
      */
     public function changeConfig($key, $row) {
         $key = $row['test']['overrideConfig']['key'];
         $value = $row['test']['overrideConfig']['value'];
         $data = $this->loadConfig();
         $this->changeConfigKey($data, $key, $value);
         $this->loadDbConfig();
     }

     /**
      * check if created duplicate billruns
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function duplicateAccounts($key, $returnBillrun, $row) {
         $this->message .= "<b>duplicate billruns: </b> <br>";
         $passed = true;
         $query = array(array('aid' => $row['test']['aid'], "billrun_key" => $row['test']['options']['stamp']));
         $sumBllruns = $this->getBillruns($query)->count();
         if ($sumBllruns > 1) {
             $this->message .= "created duplicate billruns" . $this->fail;
             $passed = false;
         } else {
             $this->message .= "no duplicate billruns" . $this->pass;
         }
         return $passed;
     }

     /**
      * confirm specific invoice
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation
      * @param array $row current test case
      */
     public function confirm($returnBillrun, $row) {
         $options['type'] = (string) 'billrunToBill';
         $options['stamp'] = (string) $row['test']['options']['stamp'];
         $options['invoices'] = (string) $returnBillrun['invoice_id'];
         $generator = Billrun_Generator::getInstance($options);
         $generator->load();
         $generator->generate();
     }

     /**
      * check after_vat per sid 
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function subsPrice($key, $returnBillrun, $row) {
         $passed = true;
         $this->message .= "<b> price per sid :</b> <br>";
         $invalidSubs = array();
         foreach ($returnBillrun['subs'] as $sub) {
             if (!Billrun_Util::isEqual($sub['totals']['after_vat'], $row['expected']['billrun']['after_vat'][$sub['sid']], 0.000001)) {
                 $passed = false;
                 $this->message .= "sid {$sub['sid']} has worng price ,<b>result</b> : {$sub['totals']['after_vat']} <b>expected</b> :{$row['expected']['billrun']['after_vat'][$sub['sid']]} " . $this->fail;
             }
         }
         if ($passed) {
             $this->message .= "all sids price are wel" . $this->pass;
         }
         return $passed;
     }

     /**
      * General check for all tests - sum of account lines equals billrun object total
      *  (aprice = before_vat, final_charge - after_vat)
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function linesVSbillrun($key, $returnBillrun, $row) {
         $this->message .= "<b> lines vs billrun :</b> <br>";
         $passed = true;
         $lines = $this->getLines($row);
         $final_charge = 0;
         $aprice = 0;
         $aftreVat = $returnBillrun['totals']["after_vat"];
         $beforeVat = $returnBillrun['totals']["before_vat"];

         foreach ($lines as $line) {
             if (isset($line['aprice'])) {
                 $aprice += $line['aprice'];
             }
             if (isset($line['final_charge'])) {
                 $final_charge += $line['final_charge'];
             }
         }

         if (Billrun_Util::isEqual($final_charge, $aftreVat, 0.000001)) {
             $this->message .= 'sum of "' . 'final_charge" equal to total.after_vat ' . $this->pass;
         } else {
             $passed = false;
             $this->message .= 'sum of "' . 'final_charge" <b>is not equal</b> to total.after_vat ' . $this->fail;
         }

         if (Billrun_Util::isEqual($aprice, $beforeVat, 0.000001)) {
             $this->message .= 'sum of "' . 'aprice" equal to total.before_vat ' . $this->pass;
         } else {
             $passed = false;
             $this->message .= 'sum of "' . 'aprice" <b>is not equal</b> to total.before_vat ' . $this->fail;
         }
         return $passed;
     }

     /**
      * 'totals.after_vat_rounded' is rounding of 'totals.after_vat
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function rounded($key, $returnBillrun, $row) {
         $this->message .= "<b> rounding :</b> <br>";
         $passed = true;
         if (round($returnBillrun['totals']['after_vat_rounded'], 2) == round($returnBillrun['totals']['after_vat'], 2)) {
             $this->message .= "'totals.after_vat_rounded' is rounding of 'totals.after_vat' :" . $this->pass;
         } else {
             $this->message .= "'totals.after_vat_rounded' is<b>not</b>rounding of 'totals.after_vat' :</b>" . $this->fail;
             $passed = false;
         }
         return $passed;
     }

     /**
      * remove billrun and lines for aid in speciphic cycle
      * @param int $key number of the test case
      * @param array $row current test case
      */
     public function removeBillrun($key, $row) {
         $stamp = $row['test']['options']['stamp'];
         $account[] = $row['test']['aid'];
         Billrun_Aggregator_Customer::removeBeforeAggregate($stamp, $account);
     }

     /**
      * check that billrun not run full cycle by checking if aid 54 is run
      * @param int $key  
      * @param array $row 
      * 
      */
     public function billrunExists($key, $row) {
         $aid = $row['test']['fake_aid'];
         $stamp = $row['test']['fake_stamp'];
         $query = array('aid' => $aid, "billrun_key" => $stamp);
         $billrun = $this->getBillruns($query)->count();
         if ($sumBillruns > 0) {
             $this->assertTrue(false);
             $this->message .= '<b style="color:red;">aggregate run full cycle</b>' . $this->fail;
         }
     }

     /**
      * run full cycle number of the test case
      * @param int $key
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function fullCycle($key, $returnBillrun, $row) {
         $passed = true;
         $aid = $row['test']['aid'];
         $stamp = $row['test']['options']['stamp'];
         $query = array('aid' => $aid, "billrun_key" => $stamp);
         $billrun = $this->getBillruns($query)->count();
         if (count($billrun) > 0) {
             $this->message .= '<b>aggregate run full cycle</b>' . $this->pass;
         } else {
             $passed = false;
             $this->message .= '<b>aggregate not run full cycle</b>' . $this->fail;
         }
         return $passed;
     }

     /**
      * check the pagination
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function pagination($key, $returnBillrun, $row) {
         $passed = true;
         $billrun = $this->getBillruns()->count();
         if ($billrun > 0) {
             $passed = false;
             $this->message .= '<b style="color:red;">pagination fail</b>' . $this->fail;
         } else {
             $this->message .= '<b style="color:green;">pagination work well</b>' . $this->pass;
         }
         return $passed;
     }

     /**
      * set charge_included_service to false
      */
     public function charge_included_service($key, $row) {
         Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/charge_included_service.ini');
     }

     /**
      *  set charge_included_service to true
      */
     public function charge_not_included_service($key, $row) {
         Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/charge_not_included_service.ini');
     }

     /**
      * check if invoice was created
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function invoice_exist($key, $returnBillrun, $row) {
         $this->message .= "<b> invoice exist :</b> <br>";
         $passed = true;
         $path = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('invoice_export.export', 'files/invoices/'));
         $path .= $row['test']['options']['stamp'] . '/pdf/' . $row['test']['invoice_path'];
         if (!file_exists($path)) {
             $passed = false;
             $this->message .= 'the invoice is not found' . $this->fail;
         } else {
             $this->message .= 'the invoice created' . $this->pass;
         }
         return $passed;
     }

     /**
      * Check override mode using passthrough_fields 
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
public function passthrough($key, $returnBillrun, $row) {
		$passed = true;
		$accounts = Billrun_Factory::account();
		$this->message .= "<b> passthrough_fields :</b> <br>";
		$account = $accounts->loadAccountForQuery((array('aid' => $row['test']['aid'])));
		$account = $accounts->getCustomerData();
		$address = $account['address'];
		if ($returnBillrun['attributes']['address'] === $address) {
			$this->message .= "passthrough work well" . $this->pass;
		} else {
			$this->message .= "passthrough fill" . $this->fail;
			$passed = false;
		}
		return $passed;
	}


     /**
      *  save invoice_id 
      *  @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      *  @param array $row current test case
      */
     public function saveId($returnBillrun, $row) {
         if (!empty($returnBillrun)) {
             $this->ids[$returnBillrun['aid']] = $returnBillrun['invoice_id'];
         }
     }

     /**
      * chack if reaggregation is overrides_invoice_id
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function overrides_invoice($key, $returnBillrun, $row) {
         $this->message .= "<b> overrides_invoice_id :</b> <br>";
         $passed = true;
         $fail = 0;
         $accounts = $row['test']['options']['force_accounts'];
         $query = array("aid" => array('$in' => $accounts), "billrun_key" => $row['test']['options']['stamp']);
         $allbillruns = $this->getBillruns($query);
         foreach ($allbillruns as $billrunse) {
             $returnBillruns[] = $billrunse->getRawData();
         }
         if (count($returnBillruns) > 10) {
             $passed = false;
             $this->message .= "aggregator wasn't overrides invoice id" . $this->fail;
         } elseif (count($returnBillruns) < 10) {
             $passed = false;
             $this->message .= "aggregator delete and not created  invoice " . $this->fail;
         } else {
             foreach ($returnBillruns as $bill) {
                 if (isset($this->ids[$bill['aid']]) && $this->ids[$bill['aid']] !== $bill['invoice_id']) {
                     $fail++;
                 }
             }
             if ($fail) {
                 $passed = false;
                 $this->message .= "force account with 10 accounts cause to worng  override invoices id" . $this->fail;
             } else {
                 $this->message .= "force account with 10 accounts work well and override the invoices id" . $this->pass;
             }
         }
         return $passed;
     }

     /**
      * check if exepted invoice are created billrun object
      * @param int $key number of the test case
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function expected_invoice($key, $row) {
         $this->message .= "<b> expected_invoice :</b> <br>";
         $passed = true;
         $billrunsBefore = $this->getBillruns()->count();
         $options = array(
             'type' => (string) 'expectedinvoice',
             'aid' => (string) 3,
             'stamp' => (string) '201808'
         );
         $generator = Billrun_Generator::getInstance($options);
         $generator->load();
         $generator->generate();
         $billrunsAfter = $this->getBillruns()->count();
         if ($billrunsAfter > $billrunsBefore) {
             $passed = false;
             $this->message .= "exepted invoice created billrun object" . $this->fail;
         } else {
             $this->message .= "exepted invoice wasn't created billrun object" . $this->pass;
         }
         return $passed;
     }

     /**
      * When an account has multiple revisions in a specific billing cycle,
      *  take the last one when generating the billrun object
       (check subs.attributes.account_name field)
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function takeLastRevision($key, $returnBillrun, $row) {
         $this->message .= "<b> Take last account_name for billrun with many revisions  at a cycle:</b> <br>";
         $passed = true;
         $query = array('aid' => 66, "billrun_key" => '201810');
         $lastRvision = $this->getBillruns($query);
         foreach ($lastRvision as $last) {
             $lastR[] = $last->getRawData();
         }
         if ($lastR[0]['attributes']['firstname'] === $row['expected']['billrun']['firstname']) {
             $this->message .= "The latest revision of the subscriber was taken" . $this->pass;
         } else {
             $passed = false;
             $this->message .= "The version taken is not the last" . $this->fail;
         }
         return $passed;
     }

     /**
      * check if 'plan' filed under sub in billrun object exists
      * @param int $key number of the test case
      * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
      * @param array $row current test case
      * @return boolean true if the test is pass and false if the tast is fail
      */
     public function planExist($key, $returnBillrun, $row) {
         $passed = true;
         $this->message .= "<br><b> plan filed  :</b> <br>";
         $sids = (array) $row['test']['sid'];
         foreach ($sids as $sid) {
             foreach ($returnBillrun['subs'] as $sub) {
                 if ($sid == $sub['sid']) {
                     if (!array_key_exists('plan', $sub)) {
                         $this->message .= "plan filed does NOT exist in billrun object" . $this->fail;
                         $passed = false;
                     } else {
                         $this->message .= "plan filed exists in billrun object" . $this->pass;
                     }
                 }
             }
         }

         return $passed;
     }

	/**
	 *  check if  Foreign Fileds create correctly
	 * 
	 * @param int $key number of the test case
	 * @param Mongodloid_Entity|array $returnBillrun is the billrun object of current test after aggregation 
	 * @param array $row current test case current test case
	 * @return boolean true if the test is pass and false if the tast is fail
	 */
	public function checkForeignFileds($key, $returnBillrun, $row) {
		$passed = TRUE;
		$this->message .= "<b> Foreign Fileds :</b> <br>";
		$entitys = $row['test']['checkForeignFileds'];
		$lines = $this->getLines($row);
		foreach ($lines as $line) {
			if ($line['usaget'] == 'discount') {
				$lines_['discount'][] = $line;
			}
			if ($line['type'] == 'service') {
				$lines_['service'][] = $line;
			}
			if ($line['type'] == 'flat') {
				$lines_['plan'][] = $line;
			}
 }
		$billruns = $this->getBillruns();
		$billruns_ = [];
		foreach ($billruns as $bill) {
			$billruns_[] = $bill->getRawData();
 }
 
		foreach ($row['test']['checkForeignFileds'] as $key => $val) {
			foreach ($val as $path => $value) {
				for ($i = 0; $i <= count($lines_[$key]); $i++) {
					if ($lineValue = Billrun_Util::getIn($lines_[$key][$i], $path)) {
						if ($lineValue == $value) {
							$this->message .= "Foreign Fileds exists  line type $key ,</br> path : " . $path . "</br>value : " . $value . $this->pass;
							continue 2;
						}
			}
			if (!$find) {
				$this->message .= "billrun not crate for aid $aid " . $this->fail;
				$this->assertTrue(0);
			}
		}}}

		//Checks that no  billruns have been created that should not be created
		if (count($billruns_) > count($aids)) {

			$wrongBillrun = array_filter($billruns_, function(array $bill) use ($aids) {
				return !in_array($bill['aid'], $aids);
			});

			foreach ($wrongBillrun as $wrong => $bill) {
				$this->message .= "billrun  crate for aid {$bill['aid']} and was not meant to be formed " . $this->fail;
				$this->assertTrue(0);
			}
		}

		//Checking that invoicing day is correct
		foreach ($billruns_ as $bill) {
			foreach ($aid_and_days as $aid => $day) {
				if ($bill['aid'] == $aid) {
					if ($bill['invoicing_day'] == $day) {
						$this->message .= "billrun  invoicing_day for aid $aid is correct ,day : $day" . $this->pass;
						continue 2;
					} else {
						$this->message .= "Foreign Fileds not created  line type $key ,</br> path : " . $path . "</br>value : " . $value . $this->fail;
						$passed = FALSE;
						continue 2;
					}
				}
			}
		}
		return $passed;
	}

 public function allowPremature($param) {
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/allow_premature_run.ini');
}
	public function notallowPremature($param) {
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/library/Tests/conf/not_allow_premature_run.ini');
	}

	public function testMultiDay($key, $returnBillrun, $row) {
		$passed = true;
		
		$aids = [];
		foreach ($row['expected']['accounts'] as $aid => $day) {
			$aids[] = $aid;
			$aid_and_days[$aid] = $day;
		}

		$billruns = $this->getBillruns();
		$billruns_ = [];
		foreach ($billruns as $bill) {
			$billruns_[] = $bill->getRawData();
		}

		//Checks that all the  billruns  that should have been created were created
		$find = false;
		foreach ($aids as $aid) {
			$find = false;
			foreach ($billruns_ as $bills) {
				if ($bills['aid'] == $aid) {
					$this->message .= "billrun crate for aid $aid  " . $this->pass;
					$find = true;
					continue 2;
				}
			}
			if (!$find) {
				$this->message .= "billrun not crate for aid $aid " . $this->fail;
				$this->assertTrue(0);
			}
		}

		//Checks that no  billruns have been created that should not be created
		if (count($billruns_) > count($aids)) {

			$wrongBillrun = array_filter($billruns_, function(array $bill) use ($aids) {
				return !in_array($bill['aid'], $aids);
			});

			foreach ($wrongBillrun as $wrong => $bill) {
				$this->message .= "billrun  crate for aid {$bill['aid']} and was not meant to be formed " . $this->fail;
				$this->assertTrue(0);
			}
		}

		//Checking that invoicing day is correct
		foreach ($billruns_ as $bill) {
			foreach ($aid_and_days as $aid => $day) {
				if ($bill['aid'] == $aid) {
					if ($bill['invoicing_day'] == $day) {
						$this->message .= "billrun  invoicing_day for aid $aid is correct ,day : $day" . $this->pass;
						continue 2;
					} else {
						$this->message .= "billrun  invoicing_day for aid $aid is not correct ,expected day is  : $day , actual result is{$bill['invoicing_day'] } " . $this->fail;
						$this->assertTrue(0);
					}
				}
			}
		}
	}

	public function removeBillruns() {
		$this->billingCyclr->remove(['billrun_key' => ['$ne' => 'abc']]);
		$this->billrunCol->remove(['billrun_key' => ['$ne' => 'abc']]);
	}

	public function testMultiDayNotallowPremature($key, $returnBillrun, $row) {
		$now = date('d');
		$billruns = $this->getBillruns();
		$billruns_ = [];
		$aid_and_days = $row['expected']['accounts'];
		foreach ($billruns as $bill) {
			$billruns_[] = $bill->getRawData();
		}

		foreach ($billruns_ as $bill) {
			
					if ($bill['invoicing_day'] == $aid_and_days[$bill['aid']]) {
						$this->message .= "billrun  invoicing_day for aid $aid is correct ,day : {$aid_and_days[$bill['aid']]}" . $this->pass;
					} else {
						$this->message .= "billrun  invoicing_day for aid $aid is not correct ,expected day is  :  {$aid_and_days[$bill['aid']]} , actual result is{$bill['invoicing_day'] } " . $this->fail;
						 $this->assertTrue(0);
					}
					if ($bill['invoicing_day'] <= $now) {
						$this->message .= "notallowPrematurun  is corrcet now its  $now  and  invoicing day  is{$aid_and_days[$bill['aid']]} aid $aid " . $this->pass;
					} else {
						$this->message .= "notallowPrematurun  is not  corrcet now its  $now  and  invoicing day  is {$aid_and_days[$bill['aid']]}  aid $aid " . $this->fail;
						$this->assertTrue(0);
					}
					$this->message .= '<br>****************************************************************<br>';

			
			
		}
	}

	public function cleanAfterAggregate($key, $row) {
		$stamp = $row['test']['options']['stamp'];
		$account[] = $row['test']['aid'];
		Billrun_Aggregator_Customer::removeBeforeAggregate($stamp, $account);
	}

	

 
 }
