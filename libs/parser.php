<?php
/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract parser class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class parser
{

	/**
	 *
	 * @var string the line to parse 
	 */
	protected $line = '';

	/**
	 *
	 * @var string the return type of the parser (object or array)
	 */
	protected $return = 'array';

	public function __construct($options)
	{

		if (isset($options['return']))
		{
			$this->return = $options['return'];
		}
	}

	/**
	 * 
	 * @return string the line that parsed
	 */
	public function getLine()
	{
		return $this->line;
	}

	/**
	 * method to set the line of the parser
	 * 
	 * @param string $line the line to set to the parser
	 * @return Object the parser itself (for concatening methods)
	 */
	public function setLine($line)
	{
		$this->line = $line;
		return $this;
	}

	/**
	 * general function to parse
	 * 
	 * @return mixed
	 */
	abstract public function parse();

	static public function getInstance()
	{
		$args = func_get_args();
		$type = $args[0];
		unset($args[0]);
		
		$file_path = __DIR__ . DIRECTORY_SEPARATOR . 'parser' . DIRECTORY_SEPARATOR . $type . '.php';
		
		if (!file_exists($file_path)) {
			// @todo raise an error
			return false;
		}
		
		require_once $file_path;
		$class = 'parser_' . $type;
		
		if (!class_exists($class)) {			
			// @todo raise an error
			return false;
		}
		
		return new $class($args);
	}

}