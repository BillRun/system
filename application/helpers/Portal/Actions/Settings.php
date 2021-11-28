<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

class Portal_Actions_Settings extends Portal_Actions {
    
    public function get($params = []) {
        $categories = $params['categories'] ?? [];

        if (empty($categories)) {
                throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "categories"');
        }
        $allow_categories = $this->params['allow_categories'] ?? [];
        foreach ($categories as $category){
            //check if category allow
            if(in_array($category, $allow_categories)){         
                $res[$category] = Billrun_Factory::config()->getConfigValue($category);
            }else{
                throw new Portal_Exception('permission_denied', '', 'Permission denied to get category : ' . $category);
            }
        }
        return $res;
    }
    
    
    /**
    * Authorize the request.
    * 
    * @param  string $action
    * @param  array $params
    * @return boolean
    */
    protected function authorize($action, &$params = []) {
        return true;
    }
}