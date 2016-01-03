<?php

/**
 * A factory class for FTP, provide classes that are used in the Ftp.
 *
 * @author eran
 */
class Zend_Ftp_Factory {

	protected static $registeredTypes = array('parser' => array());

	/**
	 * register a new parser class to corespond to a given parser type.
	 * @param string $type the type of  parser to register the class on.
	 * @param string $class the parser class name. 
	 */
	public static function registerParserType($type, $class) {
		Zend_Ftp_Factory::registerTypes(array('parser' => array($type => $class)));
	}

	/**
	 * register new type  to use when constructing concreate objects.
	 * @param array  an array containing the new types and classes.
	 */
	public static function registerTypes($typesArray) {
		Zend_Ftp_Factory::$registeredTypes = array_merge(Zend_Ftp_Factory::$registeredTypes, $typesArray);
	}

	/**
	 * Retrieve an instace of a praser for a given type.
	 * @param string $type the type of the parser to rerieve
	 * @param mixed $params (Optional) parameters to pass to the parser 
	 * @return mixed a new instance of the requested parser.
	 * @throws Exception in case the parser type wasn't defined
	 */
	public static function getParser($type, $params = array()) {
		return static::getClassInstance('parser', $type, $params);
	}

	/**
	 * register a new iterator class to corespond to a given iterator type.
	 * @param string $type the type of  iterator to register the class on.
	 * @param string $class the iterator class name. 
	 */
	public static function registerInteratorType($type, $class) {
		Zend_Ftp_Factory::registerTypes(array('iterator' => array($type => $class)));
	}

	/**
	 * Retrieve an instace of a iterator for a given type.
	 * @param string $type the type of the parser to rerieve
	 * @param mixed $params (Optional) parameters to pass to the iterator 
	 * @return mixed a new instance of the requested iterator.
	 * @throws Exception in case the parser type wasn't defined
	 */
	public static function getInterator($type, $params = array()) {
		return static::getClassInstance('iterator', $type, $params);
	}

	/**
	 * Retrieve an instace of a praser for a given type.
	 * @param string $type the type of the parser to rerieve
	 * @param mixed $params (Optional) parameters to pass to the parser 
	 * @return mixed a new instance of the requested parser.
	 * @throws Exception in case the parser type wasn't defined
	 */
	public static function getFile($type, $params = array()) {
		return static::getClassInstance('file', $type, $params);
	}

	/**
	 * register a new iterator class to corespond to a given parser type.
	 * @param string $type the type of  parser to register the class on.
	 * @param string $class the parser class name. 
	 */
	public static function registerFileType($type, $class) {
		Zend_Ftp_Factory::registerTypes(array('file' => array($type => $class)));
	}

	/**
	 * register a new iterator class to corespond to a given parser type.
	 * @param string $type the type of  parser to register the class on.
	 * @param string $class the parser class name. 
	 */
	public static function registerDirecotryType($type, $class) {
		Zend_Ftp_Factory::registerTypes(array('directory' => array($type => $class)));
	}

	/**
	 * Retrieve an instace of a praser for a given type.
	 * @param string $type the type of the parser to rerieve
	 * @param mixed $params (Optional) parameters to pass to the parser 
	 * @return mixed a new instance of the requested parser.
	 * @throws Exception in case the parser type wasn't defined
	 */
	public static function getDirecotry($type, $params = array()) {
		return static::getClassInstance('directory', $type, $params);
	}

	/**
	 * Retrieve an instace of a a given family for a given type.
	 * @param string $type the type  of the family to rerieve
	 * @param mixed $params (Optional) parameters to pass to the newly created class 
	 * @return mixed a new instance of the requested class.
	 * @throws Exception in case the parser type wasn't defined
	 */
	public static function getClassInstance($family, $type, $params = array()) {
		if (!$family) {
			throw new Exception("Please provide the family type");
		}
		if (!$type) {
			throw new Exception("Please provide the $family type");
		}

		$className = isset(Zend_Ftp_Factory::$registeredTypes[$family][$type]) ?
			Zend_Ftp_Factory::$registeredTypes[$family][$type] :
			'Zend_Ftp_' . ucfirst($family) . '_' . ucfirst(strtolower($type));

		$class = new ReflectionClass($className);
		return $class->newInstanceArgs($params);
	}

}
