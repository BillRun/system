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
	
	protected $config = array();
	protected $taxDataResults = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->config = Billrun_Factory::config()->getConfigValue('tax.config',array());
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
			
			$availableData = array( 'row'=> $line,
									'account'=>$account->getCustomerData(),
									'subscriber'=> $subscriber->getSubscriberData(),
									'config'=>$this->config);
			$singleData = $this->constructRequestData( $this->config['input_mapping'], $availableData );
			$singleData = $this->translateDataForTax($singleData, $availableData);
			$queryData[] = $singleData;
		}
		if(!empty($queryData)) {
			$data = $this->constructRequestData($this->config['request'],array('data'=> $queryData, 'config'=>$this->config));
			$this->taxDataResults = $this->queryAPIforTaxes($data);
		}
	}
	
	protected function updateRowTaxInforamtion($line, $subscriber, $account) {
		if(isset($this->taxDataResults[$line['stamp']])) {
			$line['tax_data'] = $this->taxDataResults[$line['stamp']];
		} else {
			$availableData = array( 'row'=> $line,
									'account'=>$account,
									'subscriber'=> $subscriber,
									'config'=>$this->config);
			$singleData = $this->constructRequestData( $this->config['input_mapping'], $availableData );
			$singleData = $this->translateDataForTax($singleData, $availableData);
			$data = $this->constructRequestData($this->config['request'],array('data'=> array($singleData), 'config'=>$this->config));
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

		foreach(@$this->config['transforms']['request'] as $transfom) {
			$data = method_exists($this,$transfom) 
							? $this->{$transfom}($data) 
							: (function_exists($transfom) ? $transfom($data) : $data); 
		}
		
		$client = Billrun_Factory::remoteClient($this->config['apiUrl']);
		$response = $client->{$this->config['tax_method']}($data);
		foreach(@$this->config['transforms']['response'] as $transfom) {
			$response = method_exists($this,$transfom) 
							? $this->{$transfom}($response) 
							: (function_exists($transfom) ? $transfom($response) : $response); 
		}

		return $response;
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
			
			if($tax_data['passflag'] == 1 || $this->config['apply_optional'] && $tax_data['passflag'] == 0) {
				$retTaxData['total_amount'] += $tax_data['taxamount'];
				$retTaxData['total_tax'] += $tax_data['taxrate'];
			}			
			$retTaxData['taxes'][] = array( 'tax'=> $tax_data['taxrate'],
											'amount' => $tax_data['taxamount'] ,
											'type' => $tax_data['taxtype'],
											'description' => preg_replace('/[^\w _]/',' ',$tax_data['descript']),//TODO  find a better solution
											'pass_to_customer' => $tax_data['passflag']);
			
			$retLinesTaxesData[$tax_data['unique_id']]= $retTaxData;
		}
		
		return $retLinesTaxesData;
	}
	
	protected function translateDataForTax($apiInputData, $availableData) {
		$rowIsNotUsage = in_array($availableData['row']['type'],array('flat','service','credit'));
		
		//switch destination and origin for incoming calls
		if(!$rowIsNotUsage && strstr($availableData['row']['usaget'],'incoming_') !== FALSE) {
			$apiInputData['bill_num'] = $apiInputData['term_num'];
			$apiInputData['term_num'] = $apiInputData['orig_num'];
			$apiInputData['orig_num'] = $apiInputData['bill_num'];
		}
		$apiInputData['record_type'] = $rowIsNotUsage ? 'S' : 'C';
		$apiInputData['invoice_date'] = date('Ymd',$availableData['row']['urt']->sec);
		if(!$rowIsNotUsage || $availableData['row']['type'] == 'credit') {
			$apiInputData = array_merge($apiInputData,$this->getProductAndServiceForUsage($availableData['row']));
		} else {
			$apiInputData = array_merge($apiInputData,$this->getProductAndServiceForFlat($availableData['row']));
		}
		$apiInputData['minutes'] = $rowIsNotUsage ? '': round($availableData['row']['usagev']/60);
		
		return $apiInputData;
	}
	
	protected function checkFailure($data) {
		if( $data->{'status'} == 'FAIL') {
		Billrun_Factory::log('Failed when quering the taxation API : '. print_r($data->{'error_codes'},1));
		}
		return $data;
	}
	
	protected function getProductAndServiceForFlat ($row) {
		$flatRate = $row['type'] == 'flat' ? 
						new Billrun_Plan(array('name'=> $row['name'], 'time'=> $row['urt']->sec)) : 
						new Billrun_Service(array('name'=> $row['name'], 'time'=> $row['urt']->sec));		
		if(!$flatRate) {
			throw new Exception("Couldn`t find flat  rate  for taxation for name : {$row['name']}");
		}
		$flatData = $flatRate->getData();
		$retData['productcode'] = $flatData['tax.product_code'];
		$retData['servicecode'] = $flatData['tax.service_code'];
			
		return $retData;
	}
	
	protected function getProductAndServiceForUsage ($row) {		
		$rate = Billrun_Rates_Util::getRateByRef($row instanceof \Mongodloid_Entity ? $row->get('arate',true) : $row['arate']);
		
		if(!$rate['tax']) {
			throw new Exception("Couldn`t find rate for taxation for rate : {$row['arate_key']}");
		}
		
		$retData['productcode'] = $rate['tax.product_code'];
		$retData['servicecode'] = $rate['tax.service_code'];
			
		return $retData;
	}

}
