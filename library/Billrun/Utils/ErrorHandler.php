<?php

/**
 * Controller for error handling
 * 
 * @package  Controller
 * @since    5
 */
class Billrun_Utils_ErrorHandler {
	
    
        public function __construct() {}
	/** 
	 * you can also call to Yaf_Request_Abstract::getException to get the 
	 * un-caught exception.
	 */
	public function errorAction(Throwable $exception, $hideDetails = false) {
	   // Get exception output
	   $output = $this->getExceptionOutput($exception);

	   // TODO: THIS IS DEBUG CODE!!!!!!!!!!!!!!!!!!
	   if(php_sapi_name() != "cli") {
		   print_r($output);
		   Billrun_Factory::log(print_r($output,1), Zend_Log::ERR);
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
	 * @return json encoded array
	 */
	protected function getExceptionOutput(Throwable $exception, $hideDetails) {
	   // Get exception output
	   if($exception instanceof Billrun_Exceptions_Base) {
		   return $this->billrunExceptionOutput($exception);
	   }

	   return $this->generalExceptionOutput($exception, $hideDetails);
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
	protected function generalExceptionOutput(Throwable $exception, $hideDetails = false) {
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
		if ($hideDetails) {
			$output['data']['message'] = 'Internal error raised';
		}

	   return json_encode($output);
	}
}
