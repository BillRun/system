<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Xml Encoder
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.0
 */
class Billrun_Encoder_Xml extends Billrun_Encoder_Base {

	/**
	 * xmlwriter object that helps to generate xml output
	 * @var XmlWriter
	 */
	protected $xmlWriter;

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
			header("Content-Type:text/xml");
		}
		$this->xmlWriter = new XMLWriter();
		$this->xmlWriter->openMemory();
		$this->xmlWriter->startDocument('1.0', 'UTF-8');
		if (is_array($root)) {
			$this->xmlWriter->startElement($root['tag']);
			foreach ($root['attr'] as $attr => $attrVal) {
				$this->xmlWriter->writeAttribute($attr, $attrVal);
			}
		} else {
			$this->xmlWriter->startElement($root);
		}
		$this->getXMLBody($array);
		$this->xmlWriter->endElement();
		$this->xmlWriter->endDocument();
		return $this->xmlWriter->outputMemory(true);
	}

	/**
	 * Assistance function to get inner nodes of the xml body
	 * 
	 * @param xmlwriter $value
	 * @param array $value
	 * @return string
	 */
	protected function getXMLBody($data) {
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$this->xmlWriter->startElement($key);
				$this->getXMLBody($value);
				$this->xmlWriter->endElement();
				continue;
			}
			$this->xmlWriter->writeElement($key, $value);
		}
	}

}
