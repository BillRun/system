<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ThirdPartyTax
 *
 * @author eran
 */
class Billrun_Calculator_Tax_Thirdpartytaxing extends Billrun_Calculator_Tax {
	

	protected $taxDataResults = array();
        protected $thirdpartyConfig = array();
        
        public function __construct($options = array()) {
            parent::__construct($options);
            $this->thirdpartyConfig = Billrun_Util::getFieldVal($this->config[$this->config['tax_type']],array());
	}

        public static function isConfigComplete($config) {
		return true;
	}
	
	public function prepareData($lines) {
		//TODO  query the API  with lines
		$queryData = array();
		foreach($lines as $line) {
			if(!$this->isLineLegitimate($line)) { continue; }
			$subscriber = new Billrun_Subscriber_Db();
			$subscriber->load(array('sid'=>$line['sid'],'time'=>date('Ymd H:i:sP',$line['urt']->sec)));
			$account = new Billrun_Account_Db();
			$account->load(array('aid'=>$line['aid'],'time'=>date('Ymd H:i:sP',$line['urt']->sec)));
			
			$singleData = $this->constructSingleRowData($line, $subscriber->getSubscriberData(), $account->getCustomerData());
			$queryData[] = $singleData;
		}
		if(!empty($queryData)) {
			$data = $this->constructRequestData($this->thirdpartyConfig['request'],array('data'=> $queryData, 'config'=>$this->config));
			$this->taxDataResults = $this->queryAPIforTaxes($data);
		}
	}
	
	protected function updateRowTaxInforamtion($line, $subscriber, $account) {
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
		$taxationMapping = $this->mapArrayToStructuredHash($this->config[$this->config['tax_type']], array('file_type','usaget'));
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
			
			if($tax_data['passflag'] == 1 || $this->thirdpartyConfig['apply_optional'] && $tax_data['passflag'] == 0) {
				$retTaxData['total_amount'] += $tax_data['taxamount'];
				$retTaxData['total_tax'] += $calculatedTaxRate;
			}			
			$retTaxData['taxes'][] = array( 'tax'=> $calculatedTaxRate,
                                                        'amount' => $tax_data['taxamount'] ,
                                                        'type' => $tax_data['taxtype'],
                                                        'description' => preg_replace('/[^\w _]/',' ',$tax_data['descript']),//TODO  find a better solution
                                                        'pass_to_customer' => $tax_data['passflag']);

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
                            $apiInputData[$fieldKey] = $this->mapFromArray('$row'.$mapping, $availableData);
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
		Billrun_Factory::log('Failed when quering the taxation API : '. print_r($data->{'error_codes'},1));
		}
		return $data;
	}
	
	protected function getDataFromRate ($row, $rate) {		
		$retData = array();
		//$retData['productcode'] = $rate['tax.product_code'];
		//$retData['servicecode'] = $rate['tax.service_code'];
		if($rate['tax.safe_harbor_override_pct']) {
			$retData['safe_harbor_override_flag'] = 'Y';
			$retData['safe_harbor_override_pct'] = $rate['tax.safe_harbor_override_pct'];
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
	
	protected function mapArrayToStructuredHash($arrayData,$hashKeys) {
		$retHash =array();
		$currentKey = array_shift($hashKeys);
		if(isset($arrayData[0]) && is_array($arrayData)) {
			foreach($arrayData as $data) {
				if(isset($data[$currentKey])) {
					$retHash[$data[$currentKey]] = $this->mapArrayToStructuredHash($data, $hashKeys);
				} else {
					//TODO log error
				}
			}
		} else {
			$retHash = $arrayData;
		}
		return $retHash;
	}

}
