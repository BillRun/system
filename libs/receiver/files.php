<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Files receiver class
 *
 * @package  Billing
 * @since    1.0
 */
 class receiver_files extends receiver {
	//HACK remove! get this from configuration!!
	protected $hardcodedProviders = array(
 				'012' ,
				'013',
 				"014",
 				'015',
 				'018',
				);
	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	 public function receive(){
		//TODO get from config...
		foreach($this->hardcodedProviders as $type) {
			if(!file_exists($this->workPath . DIRECTORY_SEPARATOR . $type)) {
				print("NOTICE : SKIPPING $type !!! directory ".$this->workPath . DIRECTORY_SEPARATOR . $type." not found!!");
				continue;
			}
			$files = scandir($this->workPath . DIRECTORY_SEPARATOR . $type );
			foreach($files as $file) {
				$path = $this->workPath . DIRECTORY_SEPARATOR . $type. DIRECTORY_SEPARATOR .$file;
				if(is_dir($path) || $this->isFileProcessed($file,$type) ) {continue;}

				$this->processFile($path,$type);
			}
		}
	 }

	/**
	 * Process an ILD file
	 * @param $filePath  Path to the filethat needs processing.
	 * @param $type  the type of the ILD.
	 */
	private function processFile($filePath,$type) {

		$options = array(
			'type' => $type,
			'file_path' => $filePath,
			'parser' => parser::getInstance(array('type'=>'fixed')),
			'db' => $this->db,
		);

		$processor = processor::getInstance($options);
		if ($processor) {
			$ret = $processor->process();
		} else {
			echo "error with loading processor" . PHP_EOL;
		}
		//print result
		print "type: " . $type . PHP_EOL
			. "file path: " . $filePath . PHP_EOL
			. "import lines: " . count($processor->getData()) . PHP_EOL;
	}

	/**
	 *
	 */
	private function isFileProcessed($filename,$type) {
		$log = $this->db->getCollection(self::log_table);
		$resource = $log->query()->equals('type',$type)->equals('file',$filename);
		return $resource->count() > 0;
	}
}
