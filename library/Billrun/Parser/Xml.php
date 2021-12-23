<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Parser_Xml {

    protected $xml;
    protected $workingArray = [];
    protected $pathes = [];
    protected $parents = [];
    protected $commonPath;
    protected $pathDelimiter = '.';
    protected $headerStructure;
    protected $dataStructure;
    protected $hasHeader = false;
    protected $hasFooter = false;
    protected $pathesBySegment;
    protected $input_array;
    protected $dataRows;
    protected $dataRowsNum = 0;
    protected $headerRows;
    protected $headerRowsNum = 0;
    protected $trailerRows;
    protected $trailerRowsNum = 0;
    protected $name_space_prefix = "";
    protected $name_space = "";

    public function __construct($options) {
        $this->input_array['header'] = isset($options['header_structure']) ? $options['header_structure'] : null;
        $this->input_array['data'] = isset($options['data_structure']) ? $options['data_structure'] : null;
        $this->input_array['trailer'] = isset($options['trailer_structure']) ? $options['trailer_structure'] : null;
        $this->name_space_prefix = ((isset($options['name_space_prefix']) && $options['name_space_prefix'] !== "") ? $options['name_space_prefix'] : $this->name_space_prefix);
        $this->name_space = ((isset($options['name_space']) && $options['name_space'] !== "") ? $options['name_space'] : $this->name_space);
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
        $meta_data = stream_get_meta_data($fp);
        $filename = $meta_data["uri"];
        $totalLines = 0;
        $skippedLines = 0;
        $this->dataRows = array();
        $this->headerRows = array();
        $this->trailerRows = array();

        if ($this->input_array['header'] !== null) {
            $this->hasHeader = true;
        }
        if ($this->input_array['trailer'] !== null) {
            $this->hasFooter = true;
        }
        try {
            $repeatedTags = $this->preXmlBuilding();
            foreach($repeatedTags as $segment => $tags){
                if(strpos($tags['repeatedTag'], '.') !== false){
                    $repeatedTags[$segment]['repeatedTag'] = explode('.', $tags['repeatedTag']);
                }
            }
        } catch (Exception $ex) {
            Billrun_Factory::log('Billrun_Parser_Xml: ' . $ex->getMessage(), Zend_Log::ALERT);
            return;
        }
        $commonPathAsArray = $this->pathAsArray($this->commonPath);
        if ($this->name_space_prefix !== "") {
            $GivenXml = simplexml_load_file($filename, 'SimpleXMLElement', 0, $this->name_space_prefix, TRUE);
        } else {
            $GivenXml = simplexml_load_file($filename);
        }
        if ($GivenXml === false) {
            Billrun_Factory::log('Billrun_Parser_Xml: Couldn\'t open ' . $filename . ' file. No process was made.', Zend_Log::ALERT);
            return;
        }
		
        $GivenXml->registerXPathNamespace($this->name_space_prefix, $this->name_space);
		if(!empty($this->name_space) && empty($this->name_space_prefix)) {
			$xmlAsString = file_get_contents($filename);
			$xmlAsString = str_replace(' xmlns="' . $this->name_space . '"', "", $xmlAsString);
			unset($GivenXml);
			$GivenXml = simplexml_load_string($xmlAsString);
		}
		
        $fixedTag = $commonPathAsArray[(count($commonPathAsArray) - 1)];
        $parentNode = $GivenXml;
        $this->getParentNode($parentNode);
        $headerRowsNum = $dataRowsNum = $trailerRowsNum = 0;

        for($i = 0; $i < count($commonPathAsArray); $i++){
            $parentNode = $this->getChildren($parentNode);
        }
        $dataWasProcessed = $headerWasProcessed = $trailerWasProcessed = 0;
        foreach ($parentNode as $currentChild => $data) {
            if (isset($repeatedTags['header']['repeatedTag'])) {
                if (is_array($repeatedTags['header']['repeatedTag'])){
                    if($currentChild === $repeatedTags['header']['repeatedTag'][0]){
                        if(!$headerWasProcessed){
                            $this->parseNestedLine('header', $repeatedTags, $currentChild, $data);
                            $headerWasProcessed = 1;
                        }
                    }
                }
                else if ($currentChild === $repeatedTags['header']['repeatedTag']) {
                    $this->parseLine('header', $currentChild, $data);
                }
            }
            if (isset($repeatedTags['data']['repeatedTag'])) {
                if (is_array($repeatedTags['data']['repeatedTag'])){
                    if($currentChild === $repeatedTags['data']['repeatedTag'][0]){
                        if(!$dataWasProcessed){
                            $this->parseNestedLine('data', $repeatedTags, $currentChild, $data);
                            $dataWasProcessed = 1;
                        }
                    }
                }
                else if ($currentChild === $repeatedTags['data']['repeatedTag']) {
                    $this->parseLine('data', $currentChild, $data);
                }
            }
            if (isset($repeatedTags['trailer']['repeatedTag'])) {
                if (is_array($repeatedTags['trailer']['repeatedTag'])){
                    if($currentChild === $repeatedTags['trailer']['repeatedTag'][0]){
                        if(!$trailerWasProcessed){
                            $this->parseNestedLine('trailer', $repeatedTags, $currentChild, $data);
                            $trailerWasProcessed = 1;
                        }
                    }
                }
                else if ($currentChild === $repeatedTags['trailer']['repeatedTag']) {
                    $this->parseLine('trailer', $currentChild, $data);
                }
            }
        }
    }
    
     protected function parseNestedLine($segment, $repeatedTags, $currentChild ,$data){
        $repeatedString = implode('.', $repeatedTags[$segment]['repeatedTag']);
        $data = $this->getSegmentFixedTag($segment, $repeatedTags, $currentChild ,$data);
        $numberOfChildren = count($this->getChildren($data));
        $counter = 0;
        foreach($data as $child => $childData){
            ${'Counter'.$child} = 0;
        }
        foreach($data as $child => $childData){
            $counter++;
            if ($counter <= $numberOfChildren){
                if (in_array($child, $repeatedTags[$segment]['repeatedTag'])){
                    $this->{$segment.'RowsNum'}++;
                    for($i = 0; $i < count($this->input_array[$segment]); $i++){
                        $SubPath = trim(str_replace(($this->commonPath . '.' . $repeatedString), "", $this->input_array[$segment][$i]['path']), $this->pathDelimiter);
                        if($this->name_space_prefix === ""){
                            $SubPath = '//' . str_replace(".", "/" , $SubPath);
                        }else{
                            $SubPath = '//' . $this->name_space_prefix . ':' . str_replace(".", "/" . $this->name_space_prefix . ':', $SubPath);
                        }
                        $ReturndValue = $data->xpath($SubPath);
						$value = $this->getValue($ReturndValue, $segment, $i);
                        $this->{$segment.'Rows'}[$this->{$segment.'RowsNum'} - 1][$this->input_array[$segment][$i]['name']] = $value;    
                    }
                }
            }
            ${'Counter'.$child}++;
        }
    }
    
   protected function getSegmentFixedTag($segment, $repeatedTags, $currentChild, $data) {
		foreach ($this->getChildren($data) as $child => $childData) {
			if ($child === $repeatedTags[$segment]['repeatedTag'][array_search($currentChild, $repeatedTags[$segment]['repeatedTag']) + 1]) {
				return $data;
			}
		}
	}

	protected function parseLine($segment, $currentChild, $data) {
        $this->{$segment.'RowsNum'}++;
        for ($i = 0; $i < count($this->input_array[$segment]); $i++) {
			$SubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array[$segment][$i]['path']), $this->pathDelimiter);
			if ($this->name_space_prefix === "") {
				$SubPath = '//' . str_replace(".", "/", $SubPath);
			} else {
				$SubPath = '//' . $this->name_space_prefix . ':' . str_replace(".", "/" . $this->name_space_prefix . ':', $SubPath);
			}
			$ReturndValue = $data->xpath($SubPath);
			$value = $this->getValue($ReturndValue, $segment, $i);
			$this->{$segment . 'Rows'}[$this->{$segment . 'RowsNum'} - 1][$this->input_array[$segment][$i]['name']] = $value;
		}
	}
    
    protected function preXmlBuilding() {
        foreach ($this->input_array as $segment => $indexes) {
			if(!is_null($indexes)) {
				for ($a = 0; $a < count($indexes); $a++) {
					if (isset($this->input_array[$segment][$a])) {
						if (isset($this->input_array[$segment][$a]['path'])) {
							$this->pathes[] = $this->input_array[$segment][$a]['path'];
							$this->pathesBySegment[$segment][] = $this->input_array[$segment][$a]['path'];
						} else {
							throw new Exception("No path for one of the " . $segment . "'s entity. No parse was made.");
						}
					}
				}
			}
		}
        sort($this->pathes);
        if (count($this->pathes) > 1) {
            $commonPrefix = array_shift($this->pathes);  // take the first item as initial prefix
            $length = strlen($commonPrefix);
            foreach ($this->pathes as $item) {
            // check if there is a match; if not, decrease the prefix by one character at a time
                while ($length && substr($item, 0, $length) !== $commonPrefix) {
                    $length--;
                    $commonPrefix = substr($commonPrefix, 0, -1);
                }
                if (!$length) {
                    break;
                }
            }
            $LastPointPosition = strrpos($commonPrefix, $this->pathDelimiter, 0);
            $commonPrefix = substr($commonPrefix, 0, $LastPointPosition);
            $commonPrefix = rtrim($commonPrefix, $this->pathDelimiter);
            $this->parents = explode($this->pathDelimiter, $commonPrefix);
            $this->commonPath = $commonPrefix;
        }
        foreach ($this->pathesBySegment as $segment => $paths) {
            if (count($this->pathesBySegment[$segment]) > 1) {
                sort($this->pathesBySegment[$segment]);
                $commonPrefix = array_shift($this->pathesBySegment[$segment]);  // take the first item as initial prefix
                $length = strlen($commonPrefix);
                foreach ($this->pathesBySegment[$segment] as $item) {
                // check if there is a match; if not, decrease the prefix by one character at a time
                    while ($length && substr($item, 0, $length) !== $commonPrefix) {
                        $length--;
                        $commonPrefix = substr($commonPrefix, 0, -1);
                    }
                    if (!$length) {
                        break;
                    }
                }
                $LastPointPosition = strrpos($commonPrefix, $this->pathDelimiter, 0);
                $commonPrefix = substr($commonPrefix, 0, $LastPointPosition);
                $commonPrefix = rtrim($commonPrefix, $this->pathDelimiter);
                $repeatedPrefix = trim(str_replace($this->commonPath, "", $commonPrefix), $this->pathDelimiter);
                $returnedValue[$segment] = ['repeatedTag' => $repeatedPrefix];
            } else {
                if (count($this->pathesBySegment[$segment]) == 1) {
                    $pathWithNoParents = str_replace($this->commonPath, "", $this->pathesBySegment[$segment][0]);
                    $pathWithNoParents = trim($pathWithNoParents, '.');
                    $firstPointPos = strpos($pathWithNoParents, '.');
                    $repeatedPrefix = substr_replace($pathWithNoParents, "", $firstPointPos);
                    $returnedValue[$segment] = ['repeatedTag' => $repeatedPrefix];
                } else {
                    if ($segment === "data") {
                        throw new Exception("No pathes in " . $segment . " segment. No parse was made.");
                    } else {
                        Billrun_Factory::log('Billrun_Parser_Xml: No pathes in ' . $segment . ' segment.' . $ex, Zend_Log::WARN);
                    }
                }
            }
        }
        return $returnedValue;
    }

    protected function pathAsArray($path) {
        return $pathAsArray = explode($this->pathDelimiter, $path);
    }

    protected function getParentNode(&$parentNode) {
        $Xpath = '/' . str_replace($this->commonPath, '/', $this->pathDelimiter);
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
	
	public function getValue($value, $segment, $field_index) {
		$res = null;
		if ($value) {
			if (!empty($value[0]->attributes()) && !empty($this->input_array[$segment][$field_index]['attribute'])) {
				foreach ($value[0]->attributes() as $attribute_name => $attribute_value) {
					if ($attribute_name == $this->input_array[$segment][$field_index]['attribute']) {
						$res = strval($attribute_value);
					}
				}
				if (is_null($res)) {
					Billrun_Factory::log('Billrun_Parser_Xml: Couldn\'t find attribute: ' . $this->input_array[$segment][$i]['attribute'] . ' in ' . $this->input_array[$segment][$i]['name'] . ' field. Considered as empty.', Zend_Log::WARN);
					$res = '';
				}
			} else {
				$res = strval($value[0]);
			}
		} else {
			$res = '';
		}
		if (is_null($res)) {
			$res = '';
		}
		return $res;
	}

}
