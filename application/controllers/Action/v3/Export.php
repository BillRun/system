<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';


/**
 * Description of Provide  csv exporting API to raw data in the DB.
 * This is API used in V3 and is here for backward compatibility
 *
 * @author eran
 */
class V3_exportAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();

		$collectionName = $this->getRequest()->get("collection");

		$options = array(
			'collection' => $collectionName,
			'sort' => json_decode($this->getRequest()->get("sort"),JSON_OBJECT_AS_ARRAY),
		);

		// init model
		$this->initModel($collectionName, $options);

		$skip = 1;
		$size =  Billrun_Factory::config()->getConfigValue('api.export.max_export_lines',100000);
		$query = $this->processQuery(json_decode($this->getRequest()->get("query"),JSON_OBJECT_AS_ARRAY));
		$params = array_merge($this->getTableViewParams($query, $skip, $size));
		$this->model->exportCsvFile($params);
	}
	
	/**
	 * method to render table view
	 * 
	 * @param string $table the db table to render
	 * @param array $columns the columns to show
	 * 
	 * @return string the render page (HTML)
	 * @todo refactoring this function
	 */
	protected function getTableViewParams($filter_query = array(), $skip = null, $size = null) {
		if (isset($skip) && !empty($size)) {
			$this->model->setSize($size);
			$this->model->setPage($skip);
		}
		$data = $this->model->getData($filter_query);
		$columns = $this->model->getTableColumns();
		$edit_key = $this->model->getEditKey();
		$pagination = $this->model->printPager();
		$sizeList = $this->model->printSizeList();

		$params = array(
			'data' => $data,
			'columns' => $columns,
			'edit_key' => $edit_key,
			'pagination' => $pagination,
			'sizeList' => $sizeList,
			'offset' => $this->model->offset(),
		);

		return $params;
	}
	
	public function initModel($collection_name, $options = array()) {
	
		$options['page'] =  1;
		$options['size'] =  Billrun_Factory::config()->getConfigValue('admin_panel.lines.limit', 100);

		if (is_null($this->model)) {
			$model_name = ucfirst($collection_name) . "Model";
			if (class_exists($model_name)) {
				$this->model = new $model_name($options);
			} else {
				die("Error loading model");
			}
		}
		return $this->model;
	}
	
	/**
	 * process a compund http parameter (an array)
	 * @param type $param the parameter that was passed by the http;
	 * @return type
	 */
	protected function getCompundParam($param, $retParam = array()) {
		if (isset($param)) {
			$retParam = $param;
			if ($param !== FALSE) {
				if (is_string($param)) {
					$retParam = json_decode($param, true);
				} else {
					$retParam = (array) $param;
				}
			}
		}
		return $retParam;
	}
	
	/**
	 * Process the query and prepere it for usage by the Rates model
	 * @param type $query the query that was recevied from the http request.
	 * @return array containing the processed query.
	 */
	protected function processQuery($query) {
		$retQuery = array();

		if (isset($query)) {
			$retQuery = $this->getCompundParam($query, array());
				if (isset($retQuery['from'])) {
					$retQuery['from'] = $this->intToMongoDate($retQuery['from']);
				}
				if (isset($retQuery['to'])) {
					$retQuery['to'] = $this->intToMongoDate($retQuery['to']);
				}
				if (isset($retQuery['urt'])) {
					$retQuery['urt'] = $this->intToMongoDate($retQuery['urt']);
				}
		}

		return $retQuery;
	}

	/**
	 * Change numeric references to MongoDate object in a given filed in an array.
	 * @param MongoDate $arr 
	 * @param type $fieldName the filed in the array to alter
	 * @return the translated array
	 */
	protected function intToMongoDate($arr) {
		if (is_array($arr)) {
			foreach ($arr as $key => $value) {
				if (is_numeric($value)) {
					$arr[$key] = new MongoDate((int) $value);
				} else if(is_string($value)) {
					$arr[$key] = new MongoDate( strtotime($value) );
				}
			}
		} else if (is_numeric($arr)) {
			$arr = new MongoDate((int) $arr);
		} else if(is_string($value)) {
				$arr = new MongoDate((int) $arr);
		}
		return $arr;
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}
}