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
	   // Get exception output
	   $output = $this->getExceptionOutput($exception);

	   // TODO: THIS IS DEBUG CODE!!!!!!!!!!!!!!!!!!
	   if(php_sapi_name() != "cli") {
		   print_r($output);
		   $logLevel = Zend_Log::ERR;
		   if(isset($exception->logLevel)) {
			   $logLevel = $exception->logLevel;
		   }
		   Billrun_Factory::log(print_r($output,1), $logLevel);
	   } else {
		   echo "Exception: " . $output;
	   }
	   // TODO: THIS IS DEBUG CODE!!!!!!!!!!!!!!!!!!

	   $logLevel = Zend_Log::CRIT;
	   if(isset($exception->logLevel)) {
		   $logLevel = $exception->logLevel;
	   }
	   Billrun_Factory::log()->logCrash($exception, $logLevel);
	} 

	/**
	 * Get the output from the exception to be displayed to the user
	 * @param Exception $exception
	 * @return json enecoded array
	 */
	protected function getExceptionOutput(Exception $exception) {
	   // Get exception output
	   if($exception instanceof Billrun_Exceptions_Base) {
		   return $this->billrunExceptionOutput($exception);
	   }

	   return $this->generalExceptionOutput($exception);
	}

	/**
	 * Get the exception output for a billrun exception
	 * @param Billrun_Exceptions_Base $exception
	 * @return json encoded array.
	 */
	protected function billrunExceptionOutput(Billrun_Exceptions_Base $exception) {
		return $exception->output();
	}

	/**
	 * Get the output according to a general exception.
	 * @param type $exception
	 * @return string
	 */
	protected function generalExceptionOutput(Exception $exception) {
	   $output = array();
	   $output['status'] = 0;
	   $output['code'] = 500;

	   /* error occurs */
	   switch ($exception->getCode()) {
		   case 999999:
			   $output['data']['message'] = 'Internal error raised';
			   break;
		   case YAF_ERR_NOTFOUND_MODULE:
		   case YAF_ERR_NOTFOUND_CONTROLLER:
		   case YAF_ERR_NOTFOUND_ACTION:
		   case YAF_ERR_NOTFOUND_VIEW:
			   $output['data']['message'] = $exception->getMessage() . "\n";
			   $output['code'] = 404;
			   break;
		   default :
			   $output['data']['message'] = $exception->getMessage() . "\n";
			   break;
	   }

	   return json_encode($output);
	}
}
