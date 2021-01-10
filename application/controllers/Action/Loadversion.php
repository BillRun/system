<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Recreate invoices action class
 *
 * @package  Action
 * @since    4.2
 */
class LoadversionAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	protected $SAVE_PATH = "exports";

	public static function getVersions($collection) {
		$path = VersionsModel::getVersionsPath($collection);
		if (!file_exists($path) || !is_dir($path)) {
			return array();
		}
		$files = scandir($path);
		$versions = array_diff($files, array('.', '..'));
		return $versions;
	}

	public function execute() {
		$this->allowed();
		Billrun_Factory::log("Execute load version", Zend_Log::INFO);
		if (!AdminController::authorized('admin')) {
			return;
		}
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$fileName = $request['fileName'];
		$collection = $request['collection'];
		$removeNew = ($request['remove_new'] === 'true');
		if (!$entities = $this->getPreviousVersion($fileName, $collection)) {
			Billrun_Factory::log('Loadversion - cannot getPreviousVersion of collection ' . $collection . ' from file ' . $fileName, Zend_Log::ERR);
			return false;
		}

		$collectionName = $collection . 'Collection';
		if (!$coll = Billrun_Factory::db()->{$collectionName}()) {
			Billrun_Factory::log('Loadversion - cannot read collection ' . $collectionName, Zend_Log::ERR);
			return false;
		}
		$idsRestored = array();
		foreach ($entities as $entity) {
			if (!$entityArr = json_decode($entity, JSON_OBJECT_AS_ARRAY)) {
				Billrun_Factory::log('Loadversion - cannot decode entity. Data: ' . print_R($entity, 1), Zend_Log::ERR);
				return false;
			}
			$_mongoId = new MongoId($entityArr['_id']['$id']);
			$this->prepareDataBeforeSave($entityArr);
			if (!$mongoEntity = new Mongodloid_Entity($entityArr)) {
				Billrun_Factory::log('Loadversion - cannot create mongo entity. Data: ' . print_R($entity, 1), Zend_Log::ERR);
				return false;
			}
			$mongoEntity->set('_id', $_mongoId);
			if ($coll->save($mongoEntity) === false) {
				Billrun_Factory::log('Loadversion - cannot save entity to DB. Data: ' . print_R($mongoEntity, 1), Zend_Log::ERR);
				return false;
			}
			$idsRestored[] = $_mongoId;
		}

		if ($removeNew) {
			$query = array(
				'_id' => array('$nin' => $idsRestored)
			);
			$coll->remove($query);
		}
		$this->getController()->setOutput(array(array('status' => 1)));
	}

	protected function getPreviousVersion($fileName, $collection) {
		$path = VersionsModel::getVersionsPath($collection) . '/' . $fileName;
		if (!$fileInput = file_get_contents($path)) {
			return false;
		}
		return explode(VersionsModel::getDelimiter(), $fileInput);
	}

	protected function prepareDataBeforeSave(&$data) {
		unset($data['_id']);
		foreach ($data as $key => &$val) { // handle dates (convert to Mongodloid_Date)
			if (is_array($val) && isset($val['sec'])) {
				$sec = $val['sec'];
				$val = new Mongodloid_Date($sec);
			}
		}
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
