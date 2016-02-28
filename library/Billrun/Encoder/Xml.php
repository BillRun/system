<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Encoder_Xml extends Billrun_Encoder_Base {

	public function encode($elem, $params = array()) {
		$addHeader = !isset($params['addHeader']) || $params['addHeader'];
		$root = (isset($params['root']) ? $params['root'] : 'root');
		return $this->arrayToXML((array) $elem, $root, $addHeader);
	}

	/**
	 * Assistance function to convert array to xml
	 * 
	 * @param array $array
	 * @param string $root name of the root node in the xml
	 * @return string xml
	 */
	protected function arrayToXML($array, $root = 'root', $addHeader = true) {
		if ($addHeader) {
			header ("Content-Type:text/xml");
		}
		return '<?xml version="1.0" encoding="UTF-8"?>' . "<" . $root . ">" . $this->getXMLBody($array) . "</" . $root . ">";
	}

	/**
	 * Assistance function to get inner nodes of the xml body
	 * 
	 * @param type $value
	 * @return string
	 */
	protected function getXMLBody($value) {
		if (!is_array($value)) {
			return $value;
		}
		
		$ret = '';
		foreach ($value as $key => $val) {
			$ret .= '<' . $key . '>' . $this->getXMLBody($val) . '</' . $key . '>';
		}
		return $ret;
	}

}
