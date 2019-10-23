<?php


/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * CSI taxation plugin, this  plugin add support for CSI USA taxation integration
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.0
 */

class csiPlugin extends Billrun_Plugin_Base {
	
	public function initPlugin($config, &$taxCalacualtor) {
		if($taxCalacualtor->getType() != 'tax') {
			return;
		}
	}
	
	public function onPrepareDataForTaxation($lines, &$taxCalacualtor) {
		if($taxCalacualtor->getType() != 'tax') {
			return;
		}
		$this->config = Billrun_Factory::config()->getConfigValue('taxation', array());
		$this->thirdpartyConfig = Billrun_Util::getFieldVal($this->config[$this->config['tax_type']],array());
		$queryData = array();
		foreach($lines as $line) {
			if(!$taxCalacualtor->isLineTaxable($line)) { continue; }
			$subscriber = Billrun_Factory::subscriber();
			$subscriber->loadSubscriberForQuery(array('sid'=>$line['sid'],'time'=>date('Ymd H:i:sP',$line['urt']->sec)));
			$account = Billrun_Factory::account();
			$account->loadAccount(array('aid'=>$line['aid'],'time'=>date('Ymd H:i:sP',$line['urt']->sec)));
			
			$singleData = $this->constructSingleRowData($line, $subscriber->getSubscriberData(), $account->getCustomerData());
			$queryData[] = $singleData;
		}
		if(!empty($queryData)) {
			$data = $this->constructRequestData($this->thirdpartyConfig['request'],array('data'=> $queryData, 'config'=>$this->config));
			$this->taxDataResults = $this->queryAPIforTaxes($data);
		}
	}
	
	public function onUpdateRowTaxInforamtion(&$line, $subscriber, $account,&$taxCalacualtor) {
		if($taxCalacualtor->getType() != 'tax') {
			return;
		}
		$this->config = Billrun_Factory::config()->getConfigValue('taxation', array());
		$this->thirdpartyConfig = Billrun_Util::getFieldVal($this->config[$this->config['tax_type']],array());
		if(isset($this->taxDataResults[$line['stamp']])) {
			$line['tax_data'] = $this->taxDataResults[$line['stamp']];

		} else {
			$singleData = $this->constructSingleRowData($line, $subscriber, $account);
			$data = $this->constructRequestData($this->thirdpartyConfig['request'],array('data'=> array($singleData), 'config'=>$this->config));
			$taxResults = $this->queryAPIforTaxes($data);
			if(isset($taxResults[$line['stamp']])) {
				$line['tax_data'] = $taxResults[$line['stamp']];
			} else {
				return FALSE;
			}
		}
		
		return $line;
	}
	
	
	public function onAddManualTaxationToRow(&$line, $subscriber, $account,&$taxCalacualtor) {
		if($taxCalacualtor->getType() != 'tax') {
			return;
		}
		$this->config = Billrun_Factory::config()->getConfigValue('taxation', array());
		$this->thirdpartyConfig = Billrun_Util::getFieldVal($this->config[$this->config['tax_type']],array());
		
		foreach(Billrun_Factory::config()->getConfigValue("taxation.{$this->config['tax_type']}.added_manual_taxes", array()) as $title =>  $precent) {
			//$taxRate = Billrun_Factory::db()->query(array_merge(array('key'=>$taxRateKey,"rates.{$line['usaget']}"=>array('$exists'=>1)),  Billrun_Utils_Mongo::getDateBoundQuery($line['urt']->sec)))->cursor()->limit(1)->current();
			$line = $this->addTaxRateToLine($line, $precent, $title);
		}
		return $line;
	}
	
	public function getTaxationReport($cycleKey) {
		if(Billrun_Factory::config()->getConfigValue('taxation.tax_type','') !== 'CSI') {
			return FALSE;
		}
		$this->config = Billrun_Factory::config()->getConfigValue('taxation', array());
		$this->thirdpartyConfig = Billrun_Util::getFieldVal($this->config[$this->config['tax_type']],array());
		$taxedLines = Billrun_Factory::db()->linesCollection()->query(array('billrun'=>(string)$cycleKey,'tax_data'=>array('$exists'=> 1),'final_charge'=>array('$ne'=>0)))->cursor();

		$taxes =array();
		foreach($taxedLines as  $taxedLine) {
			foreach($taxedLine['tax_data']['taxes'] as $tax) {
				if(!empty($tax['dont_report_to_thirdparty'])) { 
					continue; 
				}
				
				foreach($this->thirdpartyConfig['line_fields_for_tax_report'] as $fieldKey) {
					$tax[$fieldKey] = $taxedLine[$fieldKey];
				}
				$fillterTax = array();
				foreach($this->thirdpartyConfig['cycle_report_fields'] as $LineKey =>  $headerKey ) {
					$fillterTax[$headerKey] = $tax[$LineKey];
				}
				$taxes[] = $fillterTax;
			}
		}
		
		return array('headers'=>array_values($this->thirdpartyConfig['cycle_report_fields']), 'data' => $taxes);
	}
	//===================================================================
	
	protected function queryAPIforTaxes($data) {

		foreach(@$this->thirdpartyConfig['transforms']['request'] as $transfom) {
			$data = method_exists($this,$transfom) 
							? $this->{$transfom}($data) 
							: (function_exists($transfom) ? $transfom($data) : $data); 
		}
		
		$client = Billrun_Factory::remoteClient($this->thirdpartyConfig['apiUrl']);
		$response = $client->{$this->thirdpartyConfig['tax_method']}($data);
		foreach(@$this->thirdpartyConfig['transforms']['response'] as $transfom) {
			$response = method_exists($this,$transfom) 
							? $this->{$transfom}($response) 
							: (function_exists($transfom) ? $transfom($response) : $response); 
		}

		return $response;
	}
	/**
	 *  Build a single row tax information data request.
	 * @param type $line
	 * @param type $subscriber
	 * @param type $account
	 * @return type
	 */
	protected function constructSingleRowData($line, $subscriber, $account) {
		$singleData = array();
		$rate = $this->getRateForLine($line);
		$taxationMapping = Billrun_Util::mapFlatArrayToStructuredHash( $this->config[$this->config['tax_type']]['taxation_mapping'], array('file_type','usaget') );
		$availableData = array( 'row'=> $line,
								'account'=> $account,
								'subscriber'=> $subscriber,
								'rate' => $rate,
								'config'=> $this->config,
								'mapping' => $taxationMapping );
		
		$singleData = $this->constructRequestData( $this->thirdpartyConfig['input_mapping'], $availableData );
		$singleData = $this->translateDataForTax($singleData, $availableData);
		
		return $singleData;
	}
	
	protected function constructRequestData($config, $data) {
		$retArr = array();
		
		foreach($config as $key => $mapping) {
			$retArr[$key] = $this->mapFromArray($mapping, $data);
		}
		
		return $retArr;
	}
	
	protected function mapFromArray($mapping,$data) {
		$matches = array();
		preg_match('/\$([\w_.]+)/', $mapping, $matches);
		//remove the full string and only leave the matches
		array_shift($matches);
		//rplace the  place holders  with the actual data
		foreach($matches as $fieldMapping) {
			$value = Billrun_Util::getNestedArrayVal($data,$fieldMapping,'');
			if(is_array($value)) {
				$mapping = $value;
			} else {
				$mapping = str_replace('$'.$fieldMapping, $value, $mapping);
			}
		}
		return $mapping;
	}
	
	protected function translateTaxData($data) {
		//TODO  generalize this
		$retLinesTaxesData = array();
		$data = (array) $data;
		foreach($data['tax_data'] as $tax_data) {
			$tax_data = (array) $tax_data;
			$retTaxData=  isset($retLinesTaxesData[$tax_data['unique_id']]) ? $retLinesTaxesData[$tax_data['unique_id']] : ['total_amount'=> 0,'total_tax'=> 0,'taxes'=>[]];
			$calculatedTaxRate = !empty(0 + $tax_data['initial_charge']) ? ($tax_data['percenttaxable']) * ( ($tax_data['adjusted_tax_base']/$tax_data['initial_charge']) ) * $tax_data['taxrate'] : $tax_data['taxrate'];
			
			if($tax_data['passflag'] == 1 || $this->thirdpartyConfig['apply_optional_charges'] && $tax_data['passflag'] == 0) {
				$retTaxData['total_amount'] += $tax_data['taxamount'];
				$retTaxData['total_tax'] += $calculatedTaxRate;
			}
			$taxDataToSave = $tax_data;
			foreach($this->thirdpartyConfig['unneeded_tax_fields'] as $field) {
				if(isset($taxDataToSave[$field])) {
					unset($taxDataToSave[$field]);
				}
			}

			$retTaxData['taxes'][] = array_merge($taxDataToSave, array( 'tax'=> $calculatedTaxRate,
																'amount' => $tax_data['taxamount'] ,
																'type' => $tax_data['taxtype'],
																'description' => trim(preg_replace('/[^\w _]/',' ',$tax_data['descript'])),//TODO  find a better solution
																'pass_to_customer' => $tax_data['passflag']));

			$retLinesTaxesData[$tax_data['unique_id']]= $retTaxData;
		}
		
		return $retLinesTaxesData;
	}
	/**
         * Translate billrun data to Taxable data ( CSI format)
         * TODO this logic should be done by the configuration.
         * @param type $apiInputData
         * @param type $availableData
         * @return type
         */
	protected function translateDataForTax($apiInputData, $availableData) {
		$rowIsUsage = !in_array($availableData['row']['type'],array('flat','service','credit'));
		
        //Map origination / destinatio for usage
		if( $rowIsUsage ) {                    
			$apiInputData['bill_num'] = $apiInputData['location_a'];
			foreach(@$availableData['mapping'][$availableData['row']['type']][$availableData['row']['usaget']] as $fieldKey => $mapping) {
				if(@$this->thirdpartyConfig['taxation_mapping_override'][$availableData['row']['usaget']][$mapping]) {
					$mapping = $this->thirdpartyConfig['taxation_mapping_override'][$availableData['row']['usaget']][$mapping];
				} else {
					$mapping = '$row.uf.'.$mapping;
				}
				$apiInputData[$fieldKey] = $this->mapFromArray($mapping, $availableData);
			}	
		}
		$apiInputData['record_type'] = !$rowIsUsage ? 'S' : 'C';
		$apiInputData['invoice_date'] = date('Ymd',  Billrun_Billingcycle::getEndTime($availableData['row']['billrun'])+1);
		
		$apiInputData = array_merge($apiInputData, $this->getDataFromRate($availableData['row'], $availableData['rate']) );
		
		$apiInputData['minutes'] = !$rowIsUsage ? '' : round($availableData['row']['usagev']/60);
		
		return $apiInputData;
	}
	
	protected function checkFailure($data) {
		if( $data->{'status'} == 'FAIL') {
		Billrun_Factory::log('Failed when querying the taxation API : '. print_r($data->{'error_codes'},1));
		}
		return $data;
	}
	
	protected function getDataFromRate ($row, $rate) {		
		$retData = array();
		if(@$rate['tax']['safe_harbor_override_pct']) {
			$retData['safe_harbor_override_flag'] = 'Y';
			$retData['safe_harbor_override_pct'] = $rate['tax']['safe_harbor_override_pct'];
		}
			
		return $retData;
	}
	
	protected function getRateForLine($line) {
		$rate = FALSE;
		if(!empty($line['arate'])) {
			$rate = @Billrun_Rates_Util::getRateByRef($line['arate'])->getRawData();
		} else {
			$flatRate = $line['type'] == 'flat' ? 
				new Billrun_Plan(array('name'=> $line['name'], 'time'=> $line['urt']->sec)) : 
				new Billrun_Service(array('name'=> $line['name'], 'time'=> $line['urt']->sec));
			$rate = $flatRate->getData();
		}
		return $rate;			
	}
	
	protected function addTaxRateToLine($line, $addedTaxPercent ,$title) {
		$addTax = $line['aprice'] * $addedTaxPercent;
		
		$line['tax_data']['total_amount'] += $addTax;
		$line['tax_data']['total_rate'] = empty($line['aprice']) ? 0 : $line['tax_data']['total_amount'] / $line['aprice'];
		$line['tax_data']['taxes'][] = array( 'tax'=> $addedTaxPercent,
											'amount' => $addTax ,
											'type' => 'manual',
											'description' => $title,
											'pass_to_customer' => 1,
											'dont_report_to_thirdparty' => 1);
		return $line;
	}
}