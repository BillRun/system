<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

class Portal_Actions_Settings extends Portal_Actions {
   
    
    public function get($params = []) {
        $categorys = $params['categorys'] ?? [];

        if (empty($categorys)) {
                throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "categorys"');
        }
        $allow_categorys = $this->params['allow_categorys'] ?? [];
        foreach ($categorys as $category){
            //check if category allow
            if(in_array($category, $allow_categorys)){         
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