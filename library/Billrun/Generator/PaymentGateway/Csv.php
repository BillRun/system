<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generator CSV for payment gateways files
 */
class Billrun_Generator_PaymentGateway_Csv {
	
	protected $data = array();
	protected $headers = array();
	protected $trailers = array();
	protected $delimiter;
	protected $fixedWidth = false;
	protected $padDirDef = STR_PAD_LEFT;
	protected $padCharDef = ' ';
	protected $file_name;
	protected $file_path;
	protected $local_dir;
	protected $encoding = 'utf-8';
	protected $transactionsCounter = 0;
	protected $row_separator = "line_break";

	public function __construct($options) {
		$this->fixedWidth = isset($options['type']) && ($options['type'] == 'fixed') ? true : false;
		$this->encoding = isset($options['configByType']['generator']['encoding']) ? $options['configByType']['generator']['encoding'] : $this->encoding;
		$this->forceHeader = $options['force_header']?? false;
		$this->forceFooter = $options['force_footer']?? false;
		if (isset($options['delimiter'])) {
			$this->delimiter = $options['delimiter'];
		} else if ($this->fixedWidth) {
			$this->delimiter = '';
		}
		if (isset($options['local_dir'])) {
			$this->local_dir = $options['local_dir'];
		}
		if (isset($options['file_path'])) {
			$this->file_path = $options['file_path'];
		}
		if (isset($options['file_name'])) {
			$this->file_name = $options['file_name'];
		}
		if (isset($options['headers'])) {
			$this->headers = $options['headers'];
		}
		if (isset($options['data'])) {
			$this->data = $options['data'];
		}
		if (isset($options['trailers'])) {
			$this->trailers = $options['trailers'];
		}
		$row_separator = Billrun_Util::getIn($options, 'row_separator', 'line_break');
		$this->row_separator = $row_separator == 'line_break' ? PHP_EOL : $row_separator;
		$this->validateOptions($options);
	}
        
	/**
	 * validate the config.
	 *
	 * @param  array   $options   Relevant params from the config
	 * @return true in case all the expected config params exist, false otherwise.
	 */
	protected function validateOptions($options) {
		if (isset($options['type']) && !in_array($options['type'], array('fixed', 'separator'))) {
                        throw new Exception("File type isn't fixed/separator. No generate was made.");
		}
		if (!isset($options['local_dir'])) {
                        throw new Exception("File's local_dir is undefined. No generate was made.");
		}
		if ($this->fixedWidth) {
			foreach ($this->data as $dataLine) {
				foreach ($dataLine as $dataObj) {
					if (!isset($dataObj['padding']['length'])) {
                                                throw new Exception("Missing padding length definitions for " . $options['file_type']);
					}
				}
			}
		}
		return true;
	}
	
	public function generate() {
		if (count($this->data) || $this->forceHeader){
			$this->writeHeaders();
		}
		if (count($this->data)) {
			$this->writeRows();
		}
		if (count($this->data)|| $this->forceFooter){
			$this->writeTrailers();
		}
		return;
	}
	
	protected function writeToFile($str) {
        $str = iconv('utf-8', $this->encoding . '//TRANSLIT', $str);
		if (!file_exists($this->local_dir)) {
			mkdir($this->local_dir, 0777, true);
		}
		return file_put_contents($this->file_path, $str, FILE_APPEND);
	}

	protected function writeHeaders() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->headers as $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$fileContents .= $this->getRowContent($entity);
			$fileContents .= $this->row_separator;
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
	
	protected function writeTrailers() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->trailers as $index => $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$fileContents .= $this->getRowContent($entity);			
			if ($index !== count($this->trailers) - 1) {
				$fileContents .= $this->row_separator;
			}
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
		
	protected function writeRows() {
		Billrun_Factory::log()->log("Billrun_Generator_PaymentGateway_Csv::writeRows - start writing rows to file", Zend_Log::DEBUG);
		$fileContents = '';
		$counter = 0;
		foreach ($this->data as $index => $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$fileContents .= $this->getRowContent($entity);
			if ($index < count($this->data) - 1){
				$fileContents.= $this->row_separator;
			}
			if ($counter == 50000) {
				Billrun_Factory::log()->log("Billrun_Generator_PaymentGateway_Csv::writeRows - writing bulk to file", Zend_Log::DEBUG);
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
            $this->transactionsCounter++;
		}
		if (!empty($this->trailers)) {
			$fileContents.= $this->row_separator;
		}
		$this->writeToFile($fileContents);
		Billrun_Factory::log()->log("Billrun_Generator_PaymentGateway_Csv::writeRows - done writing rows to file", Zend_Log::DEBUG);
	}
	
	protected function getRowContent($entity) {
		$rowContents = '';
		$flag = 0;
		foreach ($entity as $entityObj) {
			$padDir = isset($entityObj['padding']['direction']) ? $this->getPadDirection($entityObj['padding']['direction']) : $this->padDirDef;
			$padChar = isset($entityObj['padding']['character']) ? $entityObj['padding']['character'] : $this->padCharDef;
                        if($this->fixedWidth){
                            $length = isset($entityObj['padding']['length']) ? $entityObj['padding']['length'] : strlen($entityObj['value']);
			} else {
				if (isset($entityObj['padding']['length'])) {
                                $length = $entityObj['padding']['length'];
				} else {
                                $length = strlen((isset($entityObj['value']) ? $entityObj['value'] : ''));
                            }
                        }
			if ($this->fixedWidth) {
				$value = str_pad((isset($entityObj['value']) ? $entityObj['value'] : ''), $length, $padChar, $padDir);
				if ($length < strlen($value)) {
					$value = implode("", array_slice(preg_split("//u", $value), 1, $length));
				}
				$rowContents .= $value;
                        }else{
                            if($flag == 0){
					$rowContents .= str_pad((isset($entityObj['value']) ? $entityObj['value'] : ''), $length, $padChar, $padDir);
                                $flag = 1;
                            }else{
					$rowContents .= $this->delimiter . str_pad((isset($entityObj['value']) ? $entityObj['value'] : ''), $length, $padChar, $padDir);
                            }
                        }
			
		}
		return $rowContents;
	}

	protected function getPadDirection($dirStr) {
		switch ($dirStr) {
			case 'left':
				return STR_PAD_LEFT;
			case 'right':
				return STR_PAD_RIGHT;
			default:
				return $this->padDirDef;
		}
	}
	
	protected function getDelimetedLine($rowEntityDef) {
		$rowValues = array_column($rowEntityDef, 'value');
		return implode($this->delimiter, $rowValues);
	}

        public function setFileName($fileName){
            $this->file_name = $fileName;
        }
        
        public function setFilePath($dir){
            $this->file_path = $dir . '/' . $this->file_name;
        }
        
        public function setDataRows($data) {
            $this->data = $data;
        }
    
        public function setHeaderRows($header) {
            $this->headers = $header;
        }
    
        public function setTrailerRows($trailer) {
            $this->trailers = $trailer;
        }
        
        public function getTransactionsCounter (){
            return $this->transactionsCounter;
        }
}
