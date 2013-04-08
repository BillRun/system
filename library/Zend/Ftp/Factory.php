<?php


/**
 * A factory class for FTP, provide classes that are used in the Ftp.
 *
 * @author eran
 */
class Zend_Ftp_Factory {
	
		protected static $registeredTypes = array('parsers'=> array());
		
		/**
		 * register a new parser class to corespond to a given parser type.
		 * @param string $type the type of  parser to register the class on.
		 * @param string $class the parser class name. 
		 */
		public static function registerParserType($type,$class) {			
			Zend_Ftp_Factory::registerTypes( array('parsers' => array($type => $class)) );
		}
		
		/**
		 * register new type  to use when constructing concreate objects.
		 * @param array  an array containing the new types and classes.
		 */
		public static function registerTypes($typesArray) {			
			Zend_Ftp_Factory::$registeredTypes = array_merge(Zend_Ftp_Factory::$registeredTypes, $typesArray );
		}
		
		/**
		 * Retrieve an instace of a praser for a given type.
		 * @param string $type the type of the parser to rerieve
		 * @param mixed $params (Optional) parameters to pass to the parsers 
		 * @return mixed a new instance of the requested parser.
		 * @throws Exception in case the parser type wasn't defined
		 */
		public static function getParser($type, $params = null) {
		if(!$type) {
			throw new Exception("Please provide the parser type");
		}
		
		$class = isset(Zend_Ftp_Factory::$registeredTypes['parsers'][$type]) ? 
									Zend_Ftp_Factory::$registeredTypes['parsers'][$type] : 
									'Zend_Ftp_Parser_'.ucfirst(strtolower($type));
		
		return new $class($params);
	}
}

?>
