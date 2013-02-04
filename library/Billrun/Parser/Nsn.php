<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Nsn
 *
 * @author eran
 */
class Billrun_Parser_Nsn extends Billrun_Parser_Base_Binary  {

	protected $nsnConfig = null;

	public function __construct($options) {
		parent::__construct($options);

		$this->header_structure = array(
									'record_length' => array('number' => 2),
									'record_type' => array('number' => 1),
									'charging_block_size' => array('number' => 1),
									'tape_block_type' => array('number' => 2),
									'data_length_in_block' => array('number' => 2),
									'exchange_id' => array('long' => 10),
									'first_record_number' => array('number' => 4),
									'batch_seq_number' => array('number' => 4),
									'block_seq_number' => array('number' => 2),
									'start_time' =>  array('bcd_encode' => 7),
									'format_version' =>  array('format_ver' => 6),
		);
		
		$this->trailer_structure = array(
									'record_length' => array('number' => 2),
									'record_type' => array('number' => 1),
									'exchange_id' => array('long' => 10),
									'end_time' => array('bcd_encode' => 7),
									'last_record_number' => array('number' => 4),
		);		
		$this->nsnConfig = parse_ini_file(Billrun_Factory::config()->getConfigValue('nsn.config_path'), true);

	}
	
	public function parse() {
		$data = array();
		$line = $this->getLine();

		$data['record_length'] = $this->parseField($line, array('number' => 2));
		$line = substr($line, 2);
		$data['record_type'] = $this->parseField($line, array('bcd_encode' => 1));
		$line = substr($line, 1);
		$data['rand'] = rand(0,99999888888999999);
		if(isset($this->nsnConfig[$data['record_type']])) {
			foreach ($this->nsnConfig[$data['record_type']] as $key => $fieldDesc) {
				if(is_array($fieldDesc)){
					$data[$key] = $this->parseField($line, $fieldDesc);
					$line = substr($line, intval(current($fieldDesc),10));
				//	$this->log->log("Data $key : {$data[$key]}",Zend_log::DEBUG);
				}
			}
		}
		$this->parsedBytes = $data['record_length'];
		
		return $data;
	}
	
	public function getLastParseLength() {
		return $this->parsedBytes;
	}

	public function parseHeader($data) {
		$header = array();
		foreach ($this->header_structure as $key => $fieldDesc) {
			$header[$key] = $this->parseField($data, $fieldDesc);
			$data = substr($data, current($fieldDesc));
			//$this->log->log("Header $key : {$header[$key]}",Zend_log::DEBUG);
		}
		return $header;
	}

	public function parseTrailer($data) {
		$trailer = array();
		foreach ($this->trailer_structure as $key => $fieldDesc) {
			$trailer[$key] = $this->parseField($data, $fieldDesc);
			$data = substr($data, current($fieldDesc));
		//$this->log->log("Trailer $key : {$trailer[$key]}",Zend_log::DEBUG);
		}
		return $trailer;
	}

	public function parseField($data, $fileDesc) {
		$type = key($fileDesc); 
		$length = $fileDesc[$type];
		switch($type) {
			case 'number' :
					$value =0;
					for($i=$length-1; $i >= 0 ; --$i) {
						$value = ord($data[$i]) + ($value << 8);
						//$this->log->log("Parsed Number $value",Zend_log::DEBUG);
					}
					
					return $value;
				break;
				
			case 'long':
					$value =0;
					for($i=$length-1; $i >= 0 ; --$i) {
						//$fieldData = $fieldData <<8;
						$value = bcadd(bcmul($value , 256 ), ord($data[$i]));
					}
					return $value;
				break;
			case 'hex' :
					$value ='';
					for($i=$length-1; $i >= 0  ; --$i) {
						$value .= dechex(ord($data[$i]));
					}
					return $value;
				break;
			case 'bcd_encode' :
					$value = '';
					for($i=$length-1; $i >= 0 ;--$i) {
						$byteVal = ord($data[$i]);
						$value .=  ((($byteVal >> 4) < 10) ? ($byteVal >> 4) : '' ) . ((($byteVal & 0xF) < 10) ? ($byteVal & 0xF) : "") ;
					}
					return $value;
					break;	
			case 'format_ver' :
					$value =$data[0]. $data[1].ord($data[2]).'.'.ord($data[3]).'-'.ord($data[4]);
					
					return $value;
				break;	
		}
	}

	
}

?>
