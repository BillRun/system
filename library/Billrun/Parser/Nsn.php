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
									'record_length' => array('decimal' => 2),
									'record_type' => array('decimal' => 1),
									'charging_block_size' => array('decimal' => 1),
									'tape_block_type' => array('decimal' => 2),
									'data_length_in_block' => array('decimal' => 2),
									'exchange_id' => array('long' => 10),
									'first_record_number' => array('decimal' => 4),
									'batch_seq_number' => array('decimal' => 4),
									'block_seq_number' => array('decimal' => 2),
									'start_time' =>  array('bcd_encode' => 7),
									'format_version' =>  array('format_ver' => 6),
		);
		
		$this->trailer_structure = array(
									'record_length' => array('decimal' => 2),
									'record_type' => array('decimal' => 1),
									'exchange_id' => array('long' => 10),
									'end_time' => array('bcd_encode' => 7),
									'last_record_number' => array('decimal' => 4),
		);		
		$this->nsnConfig = parse_ini_file(Billrun_Factory::config()->getConfigValue('nsn.config_path'), true);

	}
	
	public function parse() {
		$data = array();
		$line = $this->getLine();

		$data['record_length'] = $this->parseField($line, array('decimal' => 2));
		$line = substr($line, 2);
		$data['record_type'] = $this->parseField($line, array('bcd_encode' => 1));
		$line = substr($line, 1);
		
		if(isset($this->nsnConfig[$data['record_type']])) {
			foreach ($this->nsnConfig[$data['record_type']] as $key => $fieldDesc) {
				if($fieldDesc) {
					if (isset($this->nsnConfig['fields'][$fieldDesc])) {
							$data[$key] = $this->parseField($line, $this->nsnConfig['fields'][$fieldDesc]);
							$line = substr($line, intval(current($this->nsnConfig['fields'][$fieldDesc]), 10));
							//	$this->log->log("Data $key : {$data[$key]}",Zend_log::DEBUG);
					} else {
						throw new Exception("Nsn:parse - Couldn't find field: $fieldDesc  ");
					}
				}
			}
		}
		$this->parsedBytes = $data['record_length'];
		
		return isset($this->nsnConfig[$data['record_type']]) ?  $data : false;
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
		$retValue = '';
		
		switch($type) {
			case 'decimal' :
					$retValue = 0;
					for($i=$length-1; $i >= 0 ; --$i) {
						$retValue = ord($data[$i]) + ($retValue << 8);
					}
				break;
				
			case 'phone_number' :
					$retValue = '';
					for($i=$length-1; $i >= 0 ; --$i) {
						$byteVal = ord($data[$i]);
						$left = $byteVal & 0xF;
						$right =  $byteVal >> 4;
						$digit =  $left == 0xA ? "*" : 
									($left == 0xB ? "#" :
									($left > 0xC ? dechex($left-2) :
									 $left));
						$digit1 =  $right == 0xA ? "*" : 
									($right == 0xB ? "#" :
									($right > 0xC ? dechex($right-2) :
									 $right));
						$retValue .=  $digit . $digit1;
					}
					str_replace('ff','',$retValue);
				break;
				
			case 'long':
					$retValue = 0;
					for($i=$length-1; $i >= 0 ; --$i) {
						$retValue = bcadd(bcmul($retValue , 256 ), ord($data[$i]));
					}
				break;
				
			case 'hex' :
					$retValue ='';
					for($i=$length-1; $i >= 0  ; --$i) {
						$retValue .= dechex(ord($data[$i]));
					}
				break;
				
			case 'datetime':
			case 'bcd_encode' :
					$retValue = '';
					for($i=$length-1; $i >= 0 ;--$i) {
						$byteVal = ord($data[$i]);
						$retValue .=  ((($byteVal >> 4) < 10) ? ($byteVal >> 4) : '' ) . ((($byteVal & 0xF) < 10) ? ($byteVal & 0xF) : "") ;
					}
					break;	
					
			case 'format_ver' :
					$retValue =$data[0]. $data[1].ord($data[2]).'.'.ord($data[3]).'-'.ord($data[4]);
				break;
			
			case 'ascii':
					$retValue = preg_replace("/\W/","",substr($data,0,$length));
				break;
		}
		
		return $retValue;
	}

	
}

?>
