<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Parser_Xml {

    protected $paths = [];
	protected $segment_info = [];
	protected $file_common_path = "";
    protected $pathDelimiter = '.';
    protected $headerStructure;
    protected $dataStructure;
    protected $pathsBySegment;
    protected $input_array;
    protected $dataRows = [];
    protected $dataRowsNum = 0;
    protected $headerRows = [];
    protected $headerRowsNum = 0;
    protected $trailerRows = [];
    protected $trailerRowsNum = 0;
    protected $name_space_prefix = "";
    protected $name_space = "";
	protected $single_fields = [];

    public function __construct($options) {
        $this->input_array['header'] = isset($options['header_structure']) ? $options['header_structure'] : null;
        $this->input_array['data'] = isset($options['data_structure']) ? $options['data_structure'] : null;
        $this->input_array['trailer'] = isset($options['trailer_structure']) ? $options['trailer_structure'] : null;
        $this->name_space_prefix = ((isset($options['name_space_prefix']) && $options['name_space_prefix'] !== "") ? $options['name_space_prefix'] : $this->name_space_prefix);
        $this->name_space = ((isset($options['name_space']) && $options['name_space'] !== "") ? $options['name_space'] : $this->name_space);
		if(isset($options['records_common_path'])) {
			$this->segment_info['data']['common_path'] = $options['records_common_path'];
		}
	}

    public function setDataStructure($structure) {
        $this->dataStructure = $structure;
        return $this;
    }

    /**
     * method to set header structure of the parsed file
     * @param array $structure the structure of the parsed file
     *
     * @return Billrun_Parser_Xml self instance
     */
    public function setHeaderStructure($structure) {
        $this->headerStructure = $structure;
        return $this;
    }

    public function parse($fp) {
        $filename = stream_get_meta_data($fp)["uri"];	
		try {
			$this->preXmlBuilding();
		} catch (Exception $ex) {
			Billrun_Factory::log('Billrun_Parser_Xml: ' . $ex->getMessage(), Zend_Log::ALERT);
			return;
		}
        if (($GivenXml = $this->loadXmlFile($filename)) === false) {
            Billrun_Factory::log('Billrun_Parser_Xml: Couldn\'t open ' . $filename . ' file. No process was made.', Zend_Log::ALERT);
            return;
        }
		Billrun_Factory::dispatcher()->trigger('afterLoadXmlFile', array($this, &$GivenXml));
        $parentNode = $GivenXml;
		
		$this->parseDataSingleFields($GivenXml);
        $parentNode = current($this->getParentNode($parentNode));

        $dataWasProcessed = $headerWasProcessed = $trailerWasProcessed = 0;
        foreach ($parentNode as $currentChild => $data) {
            if (isset($this->segment_info['header']['unique_tag']) && $this->segment_info['header']['unique_tag'] == $currentChild && !$headerWasProcessed) {
                if (is_array($this->segment_info['header']['internal_common_path'])){
					$this->parseNestedSegment('header', $currentChild, $data);
                } else {
                    $this->parseSegment('header', $currentChild, $data);
                }
				$headerWasProcessed = 1;
            }
            if (isset($this->segment_info['data']['unique_tag']) && $this->segment_info['data']['unique_tag'] == $currentChild && !$dataWasProcessed) {
                if (is_array($this->segment_info['data']['internal_common_path'])){
					$this->parseNestedSegment('data', $currentChild, $data);
				} else {
                    $this->parseSegment('data', $currentChild, $data);
                }
				$dataWasProcessed = 1;
            }
            if (isset($this->segment_info['trailer']['unique_tag']) && $this->segment_info['trailer']['unique_tag'] == $currentChild && !$trailerWasProcessed) {
                if (is_array($this->segment_info['trailer']['internal_common_path'])) {
					$this->parseNestedSegment('trailer', $currentChild, $data);
                } else {
                    $this->parseSegment('trailer', $currentChild, $data);
                }
				$trailerWasProcessed = 1;
            }
        }
    }
    
    protected function parseNestedSegment($segment, $currentChild, $data) {
		if (!empty($this->segment_info[$segment]['repeated_tag'])) {
			$path = './/' . implode('/', array_slice($this->segment_info[$segment]['internal_common_path'], 0, -1));
		} else {
			$path = './/' . implode('/', $this->segment_info[$segment]['internal_common_path']);
		}
		$xml_data = current($data->xpath($path));
		foreach ($xml_data as $child => $childData) {
			if (!empty($this->segment_info[$segment]['repeated_tag']) && $child !== $this->segment_info[$segment]['repeated_tag']) {
				continue;
			}
			$this->{$segment . 'RowsNum'} ++;
			foreach ($this->input_array[$segment] as $data) {
				$SubPath = trim(str_replace($this->segment_info[$segment]['common_path'], "", $data['path']), $this->pathDelimiter);
				if ($this->name_space_prefix === "") {
					$SubPath = './/' . str_replace(".", "/", $SubPath);
				} else {
					$SubPath = './/' . $this->name_space_prefix . ':' . str_replace(".", "/" . $this->name_space_prefix . ':', $SubPath);
				}
				$ReturndValue = $childData->xpath($SubPath);
				$value = $this->getValue($ReturndValue, $data);
				$this->{$segment . 'Rows'}[$this->{$segment . 'RowsNum'} - 1][$data['name']] = $value;
			}
			$this->addSingleFieldValues($segment, $this->{$segment . 'RowsNum'} - 1);
		}
	}

	public function addSingleFieldValues($segment, $index){
		if($segment == 'data' && !empty($this->single_fields)) {
			$this->dataRows[$index] = array_merge($this->dataRows[$index], $this->single_fields);
		}
	}
	
	protected function parseSegment($segment, $currentChild, $xml_data) {
        $this->{$segment.'RowsNum'}++;
		foreach ($this->input_array[$segment] as $data) {
			$SubPath = trim(str_replace(($this->file_common_path . '.' . $currentChild), "", $data['path']), $this->pathDelimiter);
			if ($this->name_space_prefix === "") {
				$SubPath = '//' . str_replace(".", "/", $SubPath);
			} else {
				$SubPath = '//' . $this->name_space_prefix . ':' . str_replace(".", "/" . $this->name_space_prefix . ':', $SubPath);
			}
			$ReturndValue = $xml_data->xpath('.' . $SubPath);
			$value = $this->getValue($ReturndValue, $data);
			$this->{$segment . 'Rows'}[$this->{$segment . 'RowsNum'} - 1][$data['name']] = $value;
		}
	}
    
    protected function preXmlBuilding() {
		foreach ($this->input_array as $segment => $indexes) {
			if(!is_null($indexes)) {
				for ($a = 0; $a < count($indexes); $a++) {
					if (isset($this->input_array[$segment][$a]['path'])) {
						$this->paths[] = $this->input_array[$segment][$a]['path'];
						$this->pathsBySegment[$segment][] = $this->input_array[$segment][$a]['path'];
					} else {
						throw new Exception("No path for one of the " . $segment . "'s entity. No parse was made.");
					}
				}
			}
		}
        
		$this->file_common_path = $this->getLongestCommonPath($this->paths);
        foreach ($this->pathsBySegment as $segment => $paths) {
			if (count($this->pathsBySegment[$segment]) > 1) {
				$longest_common_path = isset($this->segment_info[$segment]['common_path']) ? $this->segment_info[$segment]['common_path'] : $this->getLongestCommonPath($paths);
				$unique_tag = $this->getSegmentUniqueTag($longest_common_path);
				$this->segment_info[$segment] = [
					'common_path' => $longest_common_path,
					'unique_tag' => $unique_tag,
					'internal_common_path' => $this->getSegmentInternalPath($longest_common_path, $unique_tag),
					'repeated_tag' => trim(substr($longest_common_path, strrpos($longest_common_path, $this->pathDelimiter)), $this->pathDelimiter)
				];
            } else {
                if (count($this->pathsBySegment[$segment]) == 1) {
					$longest_common_path = current($this->pathsBySegment[$segment]);
					$unique_tag = $this->getSegmentUniqueTag($longest_common_path);
					$this->segment_info[$segment] = [
						'common_path' => $longest_common_path,
						'unique_tag' => $unique_tag,
						'internal_common_path' => $this->getSegmentInternalPath($longest_common_path, $unique_tag),
						'repeated_tag' => trim(substr($longest_common_path, strrpos($longest_common_path, $this->pathDelimiter)), $this->pathDelimiter)
					];
				} else {
                    if ($segment === "data") {
                        throw new Exception("No paths in " . $segment . " segment. No parse was made.");
                    } else {
                        Billrun_Factory::log('Billrun_Parser_Xml: No paths in ' . $segment . ' segment.' . $ex, Zend_Log::WARN);
                    }
                }
            }
        }
    }

	public function getLongestCommonPath($paths) {
        if (count($paths) > 1) {
			sort($paths);
            $common_prefix = array_shift($paths);
            $length = strlen($common_prefix);
            foreach ($paths as $path) {
                while ($length && substr($path, 0, $length) !== $common_prefix) {
                    $length--;
                    $common_prefix = substr($common_prefix, 0, -1);
                }
                if (!$length) {
                    break;
                }
            }
        }
		return rtrim($common_prefix, $this->pathDelimiter);
	}

	public function getSegmentUniqueTag($longest_common_path) {
		$segment_path = trim(substr_replace($longest_common_path, "", 0, strlen($this->file_common_path)), $this->pathDelimiter);
		if(strpos($segment_path, $this->pathDelimiter) !== false) {
			return substr($segment_path, 0, strpos($segment_path, $this->pathDelimiter));
		}
		return $segment_path;
	}

	public function getSegmentInternalPath($longest_common_path, $unique_tag) {
		$val = trim(substr_replace($longest_common_path, "", 0, strlen($this->file_common_path . $this->pathDelimiter . $unique_tag)), $this->pathDelimiter);
		if(strpos($val, $this->pathDelimiter) == false) {
			return $val;
		} else {
			return explode($this->pathDelimiter, $val);
		}
	}

	public function parseDataSingleFields($xml) {
		foreach($this->input_array['data'] as $index => $data){
			if (!preg_match('/^' . $this->segment_info['data']['common_path'] . '/', $data['path'])) {
				$val = $xml->xpath('/' . str_replace($this->pathDelimiter, '/', $data['path']));
				$this->single_fields[$data['name']] = !empty($val) ? strval(current($val)) : "";
				unset($this->input_array['data'][$index]);
			}
		}
	}

    protected function getParentNode(&$parentNode) {
        $Xpath = '/' . str_replace($this->pathDelimiter, '/', $this->file_common_path);
        return $parentNode->xpath($Xpath);
    }

    public function getHeaderRows() {
        return $this->headerRows;
    }

    public function getDataRows() {
        return $this->dataRows;
    }

    public function getTrailerRows() {
        return $this->trailerRows;
    }

    /**
     * method to get a list of node's children
     * @param xmlNode $parentNode
     *
     * @return List of parent's children.
     */
    public function getChildren($parentNode) {
        if ($this->name_space_prefix !== "") {
            return $parentNode->children($this->name_space_prefix, true);
        } else {
            return $parentNode->children();
        }
    }
	
	public function getValue($value, $field_conf, $counter = 0) {
		$res = null;
		if (is_array($value) && isset($value[$counter])) {
			if (!empty($value[$counter]->attributes()) && !empty($field_conf['attribute'])) {
				foreach ($value[$counter]->attributes() as $attribute_name => $attribute_value) {
					if ($attribute_name == $field_conf['attribute']) {
						$res = strval($attribute_value);
					}
				}
				if (is_null($res)) {
					Billrun_Factory::log('Billrun_Parser_Xml: Couldn\'t find attribute: ' . $field_conf . ' in ' . $field_conf['name'] . ' field. Considered as empty.', Zend_Log::WARN);
					$res = '';
				}
			} else {
				$res = strval($value[$counter]);
			}
		} else {
			$res = '';
		}
		if (is_null($res)) {
			$res = '';
		}
		return $res;
	}

	public function loadXmlFile($filename) {
		if ($this->name_space_prefix !== "") {
			$GivenXml = simplexml_load_file($filename, 'SimpleXMLElement', 0, $this->name_space_prefix, TRUE);
		} else {
			$GivenXml = simplexml_load_file($filename);
		}
		if($GivenXml === false){
			return false;
		}
		$GivenXml->registerXPathNamespace($this->name_space_prefix, $this->name_space);
		if (!empty($this->name_space) && empty($this->name_space_prefix)) {
			$xmlAsString = file_get_contents($filename);
			$xmlAsString = str_replace(' xmlns="' . $this->name_space . '"', "", $xmlAsString);
			unset($GivenXml);
			$GivenXml = simplexml_load_string($xmlAsString);
		}
		return $GivenXml;
	}

}
