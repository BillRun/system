<?php

/**
 * @package         Billing
 */

/**
 * Send action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       
 */
class Send_fileAction extends Action_Base {
	
	public function execute() {
		
		$possibleOptions = array(
            'type' => false,
            'stamp' => true,
        );

        if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
            return;
        }

        $this->_controller->addOutput("Loading files sender..");

        $extraParams = $this->_controller->getParameters();
        if (!empty($extraParams)) {
            $options = array_merge($extraParams, $options);
        }
		
		try{
			$sender_details = $this->getSenderDetails($options, $extraParams);
			if(!$sender_details) {
				$this->_controller->addOutput("Something went wrong while building the sender. Nothing was sent.");
				return;
			}
			foreach ($sender_details['connections'] as $connection) {
				$this->_controller->addOutput("Move to sender {$connection['name']} - start");
				$sender = Billrun_Sender::getInstance($connection);
				if (!$sender) {
					$this->_controller->addOutput("Cannot get sender. details: " . print_R($connections, 1));
					return;
				}
				$this->_controller->addOutput("Sender loaded");
				$this->_controller->addOutput("Sending files from : " . $extraParams['local_path']);
				$this->_controller->addOutput("Starting to send the files. This action can take a while...");
				$local_path = rtrim($extraParams['local_path'], DIRECTORY_SEPARATOR);
				foreach ($sender_details['file_names'] as $file_name) {
					if (!$sender->send($local_path . DIRECTORY_SEPARATOR . $file_name)) {
						$this->_controller->addOutput("Move to sender {$connection['name']} - failed!");
						return;
					} else {
						$this->_controller->addOutput("Move to sender {$connection['name']} - done");
					}
				}
			}
		} catch(Exception $ex){
            $this->_controller->addOutput($ex->getMessage());
            $this->_controller->addOutput('Something went wrong while building the sender. Nothing was sent.');
            return;
        }
		
        $this->_controller->addOutput("Finished sending.");

 	}
	
	public function getSenderDetails($options, $extraParams) {
		$this->_controller->addOutput("Loading file type connections..");
		$res['connections'] = $this->getConfiguredConnections($options, $extraParams);
		$this->_controller->addOutput("Loading file names from the local directory..");
		$res['file_names'] = $this->getFileNames($extraParams['local_path']);
		return ($res['file_names'] && $res['connections']) ? $res : false; 
	}
	
	public function getConfiguredConnections($options, $extraParams){
		if(!isset($options['type']) || !isset($extraParams['name']) || !isset($extraParams['local_path'])) {
			throw new Exception("Missing type/name/local_path in the send file command..");
		}
		$data_type_config = Billrun_Factory::config()->getConfigValue($options['type'], []);
		$file_type_name = $extraParams['name'];
		if(empty($data_type_config)) {
			$this->_controller->addOutput('Didn\'t find configuration type : ' . $options['type']);
			return false;
		}
		$this->_controller->addOutput("Pulled " . $options['type'] . " configuration..");
		$file_type = current(array_filter($data_type_config, function($file_type) use($file_type_name) {
			return $file_type['name'] == $file_type_name;
		}));
		$this->_controller->addOutput("Pulled " . $file_type['name'] . " file type configuration..");
		return !empty($file_type['senders']['connections']) ? $file_type['senders']['connections'] : [];
	}

	public function getFileNames($files_path) {
		$res = array_slice(scandir($files_path), 2);
		if ($res === false) {
			$this->_controller->addOutput("Something went wrong while scanning the data directory");
			return false;
		} else {
			if (is_array($res)) {
				if (count($res) == 0) {
					$this->_controller->addOutput("0 items were scanned from : " . $files_path);
					return false;
				}
				$this->_controller->addOutput("Found " . count($res) . " files to send..");
			} else {
				$this->_controller->addOutput("The directory's scanning result isn't an array/boolean, something is wrong");
				return false;
			}
		}
		return $res;
	}

}
