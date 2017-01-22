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

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->config = Billrun_Factory::config()->getConfigValue('tax.config',array());
	}
	
	public static function isConfigComplete($config) {
		return true;
	}
	
	public function prepareData($lines) {
		//TODO  query the API  with lines
	}
	
	protected function updateRowTaxInforamtion($line, $subscriber) {
		$singleData = $this->constructRequestData( $this->config['input_mapping'],array( 'row'=> $line,
																						'subscriber'=> $subscriber,
																						'config'=>$this->config) );
		$data = $this->constructRequestData($this->config['request'],array('data'=> array($singleData), 'config'=>$this->config));
		$line['tax_data'] = $this->queryAPIforTaxes($data);
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
		$retTaxData= ['total_amount'=> 0,'total_rate'=> 0,'taxes'=>[]];
		$data = (array) $data;
		foreach($data['tax_data'] as $tax_data) {
			$tax_data = (array) $tax_data;
			if($tax_data['passflag'] == 1) {
				$retTaxData['total_amount'] += $tax_data['taxamount'];
				$retTaxData['total_rate'] += $tax_data['taxrate'];
			}			
			$retTaxData['taxes'][] = array( 'rate'=> $tax_data['taxrate'],
											'amount' => $tax_data['taxamount'] ,
											'type' => $tax_data['taxtype'],
											'description' => $tax_data['descript'],
											'pass_to_customer' => $tax_data['passflag']);
		}
		return $retTaxData;
	}

}

