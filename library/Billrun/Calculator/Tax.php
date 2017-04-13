<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Tax
 *
 * @author eran
 */
abstract class Billrun_Calculator_Tax extends Billrun_Calculator {

	static protected $type = 'tax';
	
	protected $config = array();
	protected $nonTaxableTypes = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->config = Billrun_Factory::config()->getConfigValue('taxation',array());
		$this->nonTaxableTypes = Billrun_Factory::config('taxation.non_taxable_types', array());
	}

	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$current = $row instanceof Mongodloid_Entity ? $row->getRawData() : $row;
		if( $problemField = $this->isLineDataComplete($current) ) {
			Billrun_Factory::log("Line {$current['stamp']} is missing/has illigeal value in fields ".  implode(',', $problemField). ' For calcaulator '.$this->getType() );
			return FALSE;
		}
		
		$subscriber = new Billrun_Subscriber_Db();
		$subscriber->load(array('sid'=>$current['sid'],'time'=>date('Ymd H:i:sP',$current['urt']->sec)));
		$account = new Billrun_Account_Db();
		$account->load(array('aid'=>$current['aid'],'time'=>date('Ymd H:i:sP',$current['urt']->sec)));
		$newData = $this->updateRowTaxInforamtion($current, $subscriber->getSubscriberData(),$account->getCustomerData());
		
		//If we could not find the taxing information.
		if($newData == FALSE) {
			return FALSE;
		}
		
		if($row instanceof Mongodloid_Entity ) {
			$row->setRawData($newData);
		} else {
			$row = $newData;
		}

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;;
	}

	/**
	 * stab function The  will probably  be no need to prepare data for taxing
	 * @param type $lines
	 * @return nothing
	 */
	public function prepareData($lines) { }

	
	//================================= Static =================================
	/**
	 *  Get  the  total amount with taxes  for a given line
	 * @param type $taxedLine a line *after* taxation  was applied to it.
	 * @return float the  price of the line including taxes
	 */
	public static function addTax($taxedLine) {
		return $taxedLine['aprice'] + $taxedLine['tax_data']['tax_amount'];
	}

	/**
	 *  Remove the taxes from the total amount with taxes for a given line
	 * @param type $taxedLine a line *after* taxation  was applied to it.
	 * @return float the  price of the line including taxes
	 */
	public static function removeTax($taxedPrice, $taxedLine) {
		return $taxedPrice + $taxedLine['tax_data']['tax_amount'];
	}

	//================================ Protected ===============================	

	/**
	 * Retrive all queued lines except from those that are configured not to be retrived.
	 * @return type
	 */
	protected function getLines() {
		return $this->getQueuedLines( array( 'type' => array( '$nin' => $this->nonTaxableTypes ) ) );
	}

	public function getCalculatorQueueType() {
		return 'tax';
	}

	public function isLineLegitimate($line) {
		//Line is legitimate if it has rated usag
		$rate =  Billrun_Rates_Util::getRateByRef( $line instanceof Mongodloid_Entity ? $line->get('arate', true): $line['arate']);
		return !empty($line[Billrun_Calculator_Rate::DEF_CALC_DB_FIELD]) && @$rate['vatable'] ; // all rated lines that are taxable
	}	
	
	protected function isLineDataComplete($line) {
		$missingFields = array_diff( array('aid'), array_keys($line) );
		return empty($missingFields) ? FALSE : $missingFields;
	}

	/**
	 * Update the line/row with it related taxing data.
	 * @param array $line The line to update it data.
	 * @param array $subscriber  the subscriber that is associated with the line
	 * @return array updated line/row with the tax data
	 */
	abstract protected function updateRowTaxInforamtion($line, $subscriber, $account);
}
