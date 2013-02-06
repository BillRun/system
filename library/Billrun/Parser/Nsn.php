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

		$this->nsnConfig = parse_ini_file(Billrun_Factory::config()->getConfigValue('nsn.config_path'), true);

	}
	
	public function parse() {
		$data = array();
		$offset = 0;
		$line = $this->getLine();
		
		$data['record_length'] = $this->parseField(substr($line, $offset, 2), array('decimal' => 2));
		$offset += 2;	
		$data['record_type'] = $this->parseField(substr($line, $offset, 1), array('bcd_encode' => 1));
		$offset += 1;

		if(isset($this->nsnConfig[$data['record_type']])) {
			foreach ($this->nsnConfig[$data['record_type']] as $key => $fieldDesc) {
				if($fieldDesc) {
					if (isset($this->nsnConfig['fields'][$fieldDesc])) {
							$length = intval(current($this->nsnConfig['fields'][$fieldDesc]), 10);
							$data[$key] = $this->parseField(substr($line,$offset,$length), $this->nsnConfig['fields'][$fieldDesc]);
							$offset += $length;
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
		foreach ($this->nsnConfig['block_header'] as $key => $fieldDesc) {
			$fieldStruct=$this->nsnConfig['fields'][$fieldDesc];
			$header[$key] = $this->parseField($data, $fieldStruct);
			$data = substr($data, current($fieldStruct));
			//$this->log->log("Header $key : {$header[$key]}",Zend_log::DEBUG);
		}
		return $header;
	}

	public function parseTrailer($data) {
		$trailer = array();
		foreach ($this->nsnConfig['block_trailer'] as $key => $fieldDesc) {
			$fieldStruct=$this->nsnConfig['fields'][$fieldDesc];
			$trailer[$key] = $this->parseField($data, $fieldStruct);
			$data = substr($data, current($fieldStruct));
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
					$val = '';
					for($i=0; $i < $length ; ++$i) {
						$byteVal = ord($data[$i]);
						$left = $byteVal & 0xF;
						$right = $byteVal >> 4;
						$digit =  $left == 0xA ? "*" : 
									($left == 0xB ? "#" :
									($left > 0xC ? dechex($left-2) :
									 $left));
						$digitRight =  $right == 0xA ? "*" : 
									($right == 0xB ? "#" :
									($right > 0xC ? dechex($right-2) :
									 $right));
						$val .=  $digit . $digitRight;
					}
					$retValue = str_replace('d','',$val);
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
						$retValue .=  ((($byteVal >> 4) < 10) ? ($byteVal >> 4) : '' ) . ((($byteVal & 0xF) < 10) ? ($byteVal & 0xF) : '') ;
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
