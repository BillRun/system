<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Basic class for getting the view params.
 * @package  ViewParams
 * @since    2.8
 */
abstract class Admin_Viewparams_Base implements Admin_Viewparams_I {
	
	/**
	 * Get the query type for this handler.
	 * @return string The query type.
	 */
	protected abstract function getQueryType();
		
	/**
	 * method to render table view
	 * 
	 * @param Model $model - Current model in use.
	 * @param array $columns the columns to show
	 * @param array $filter_query - Query to get data for.
	 * 
	 * @return string the render page (HTML)
	 * @todo refactoring this function
	 */
	public function getTableViewParams($model, $columns=array(), $filter_query = array(), $skip = null, $size = null) {
		if (isset($skip) && !empty($size)) {
			$model->setSize($size);
			$model->setPage($skip);
		}
		
		$queryType = $this->getQueryType();
		$data = $model->fetch($filter_query);
		$edit_key = $model->getEditKey();
		$paramArray = array('queryType' => $queryType);
		$pagination = $model->printPager(false, $paramArray);
		$sizeList = $model->printSizeList(false, $paramArray);

		$params = array(
			'data' => $data,
			'columns' => $columns,
			'edit_key' => $edit_key,
			'pagination' => $pagination,
			'sizeList' => $sizeList,
			'offset' => $model->offset(),
			'query_type' => $queryType,
		);

		return $params;
	}
}
