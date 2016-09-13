<?php

/**
 * Controller for error handling
 * 
 * @package  Controller
 * @since    5
 */
class ErrorController extends Yaf_Controller_Abstract {
	
     /** 
      * you can also call to Yaf_Request_Abstract::getException to get the 
      * un-caught exception.
      */
     public function errorAction(Exception $exception) {	
		$output = array();
		$output['status'] = 0;
		$output['code'] = 500;
		
        /* error occurs */
        switch ($exception->getCode()) {
            case YAF_ERR_NOTFOUND_MODULE:
            case YAF_ERR_NOTFOUND_CONTROLLER:
            case YAF_ERR_NOTFOUND_ACTION:
            case YAF_ERR_NOTFOUND_VIEW:
                $output['data']['message'] = $exception->getMessage() . "\n";
                $output['code'] = 404;
                break;
			case Billrun_Traits_Api_IUserPermissions::NO_PERMISSION_ERROR_CODE:
				$output['data']['message'] = "No permissions\n";
				break;
            default :
                $output['data']['message'] = $exception->getMessage() . "\n";
                break;
        }
		
		// TODO: THIS IS DEBUG CODE!!!!!!!!!!!!!!!!!!
		if(php_sapi_name() != "cli") {
			echo json_encode($output);
		} else {
			echo "Exception: " . $output['data']['message'];
		}
		// TODO: THIS IS DEBUG CODE!!!!!!!!!!!!!!!!!!
		
		Billrun_Factory::log()->logCrash($exception);
     } 
}
