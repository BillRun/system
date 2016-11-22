<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the settings.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class LogoAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	/**
	 * The instance of the Grid FS collection.
	 * @var MongoGridFS
	 */
	protected $collection;
	
	/**
	 * Decode the 64 base input.
	 * @param string $name - The name of the value.
	 * @param mixed $request - The request instance.
	 * @param mixed $default - Default value to be returned if no value is fond
	 * @return The decoded value or a default value if none is found in the request.
	 */
	protected function decodeValue($name, $request, $default = array()) {
		$rawValue = $request->get($name);
		if(!$rawValue) {
			return $default;
		}
		$data = json_decode($rawValue, true);
		return $data;
	}
	
	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		$this->allowed();
		$this->constructCollection();
		$request = $this->getRequest();
		$query = $this->decodeValue('query', $request);
		if (!$query) {
			$this->setError('Illegal data', $request->getPost());
			return TRUE;
		}
		
		if(!isset($query['filename'])) {
			$invalidField = new Billrun_DataTypes_InvalidField('filename');
			throw new Billrun_Exceptions_InvalidFields($invalidField);
		}
		
		$metadata = $this->decodeValue('metadata', $request);
		if (!$metadata) {
			$metadata = array();
		}
		
		$action = $request->get('action');
		$success = true;
		$output = array();
		if ($action === 'save') {
			$success = $this->create($query, $metadata);
		} else if ($action === 'read') {
			$success = $this->retrieve($query);
		} else {
			$this->setError("Invalid action.");
		}

		$this->getController()->setOutput(array(array(
				'status' => $success ? 1 : 0,
				'desc' => $success,
				'input' => $request->getPost(),
				'details' => is_bool($output)? array() : $output,
		)));
		return TRUE;
	}

		/**
	 * Constuct the internal collection instance 
	 * @param string $dbName - Database name
	 * @param string $collName - Collection name
	 */
	function constructCollection() {
		$_db = Billrun_Factory::db()->getDb();
		$this->collection = $_db->getGridFS();

		// If the collection was not found
		if(!$this->collection) {
			// TODO: Replace error codes with constants
			throw new Exception("Invalid collection request!", 17);
		}
	}
	
	/**
	 * 
	 * @param string $fileName - The name of the file to create (Taken from the
	 * request data).
	 * @param array $metadata - Metadata to store on the file
	 * @return type
	 */
	public function create($fileName, array $metadata) {
		try {
			$mongoID = $this->collection->storeUpload($fileName, $metadata);
		} catch (MongoGridFSException $e) {
			// TODO: Replace error codes with constants
			Billrun_Factory::log("GRIDFS ERR: " . $e->getMessage());
			throw new Exception("GridFS error!", 409, $e);
		}

		if($mongoID) {
			return "Record created with ID . $mongoID";
		}
		
		return "Record was not created.";
	}
	
	/**
	 * 
	 * @param array $query
	 * @param array $options
	 * @return type
	 */
	public function retrieve(array $query) {
		$gfsFile = $this->collection->findOne($query);
		if(!$gfsFile) {
			Billrun_Factory::log("FSBytes is null.");
			return null;
		}
		
		// Return the bytes array
		$bytes = base64_encode($gfsFile->getBytes());
		if($bytes === false) {
			// TODO: Add an exception to be thrown here.
			Billrun_Factory::log("Invalid file", Zend_Log::WARN);
			return null;
		}
		
		return $bytes;
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}
