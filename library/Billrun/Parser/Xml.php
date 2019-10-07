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
    protected $headerRows;
    protected $trailerRows;
    protected $name_space = "";

    public function __construct($options) {
        $this->input_array['header'] = isset($options['header_structure']) ? $options['header_structure'] : null;
        $this->input_array['data'] = isset($options['data_structure']) ? $options['data_structure'] : null;
        $this->input_array['trailer'] = isset($options['trailer_structure']) ? $options['trailer_structure'] : null;
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
        } catch (Exception $ex) {
            Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: ' . $ex->getMessage(), Zend_Log::ALERT);
            return;
        }
        $commonPathAsArray = $this->pathAsArray($this->commonPath);
        if($this->name_space !== ""){
            $GivenXml = simplexml_load_file($filename, 'SimpleXMLElement', 0, $this->name_space , TRUE);
        }else{
                $GivenXml = simplexml_load_file($filename);
        }
        if ($GivenXml === false) {
            Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: Couldn\'t open ' . $filename . ' file. Might missing \'<\' or \'/\'. Please check, and reprocess.' , Zend_Log::ALERT);
            return;
        }
        
        
        $xmlAsString = file_get_contents($filename);
        
        $fixedTag = $commonPathAsArray[(count($commonPathAsArray) - 1)];
        $parentNode = $GivenXml;
        $this->getParentNode($parentNode);
        
        if($this->name_space !== ""){
            $xmlIterator = new SimpleXMLIterator($xmlAsString, 0, false, $this->name_space, TRUE);
        }else{
                $xmlIterator = new SimpleXMLIterator($xmlAsString);
        }
        $headerRowsNum = $dataRowsNum = $trailerRowsNum = 0;
        for ($xmlIterator->rewind(); $xmlIterator->valid(); $xmlIterator->next()) {
            foreach ($xmlIterator->getChildren() as $currentChild => $data) {
                if (isset($repeatedTags['header']['repeatedTag'])) {
                    if ($currentChild === $repeatedTags['header']['repeatedTag']) {
						$headerRowsNum++;
                        for ($i = 0; $i < count($this->input_array['header']); $i++) {
                            $headerSubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array['header'][$i]['path']), $this->pathDelimiter);
                            $headerSubPath = '//' . str_replace("." , "/" , $headerSubPath);
                            $headerReturndValue = $data->xpath($headerSubPath);
                            if ($headerReturndValue) {
                                $headerValue = strval($headerReturndValue[0]);
                            } else {
                                $headerValue = '';
                            }
                            $this->headerRows[$headerRowsNum-1][$this->input_array['header'][$i]['name']] = $headerValue;
                        }
                    }
                }
                if (isset($repeatedTags['data']['repeatedTag'])) {
                    if ($currentChild === $repeatedTags['data']['repeatedTag']) {
						$dataRowsNum++;
                        for ($j = 0; $j < count($this->input_array['data']); $j++) {
                            $dataSubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array['data'][$j]['path']), $this->pathDelimiter);
                            $dataSubPath = '//' . str_replace("." , "/" , $dataSubPath);
                            $dataReturndValue = $data->xpath($dataSubPath);
                            if ($dataReturndValue) {
                                $dataValue = strval($dataReturndValue[0]);
                            } else {
                                $dataValue = '';
                            }
                            $this->dataRows[$dataRowsNum-1][$this->input_array['data'][$j]['name']] = $dataValue;
                        }
                    }
                }
                if (isset($repeatedTags['trailer']['repeatedTag'])) {
                    if ($currentChild === $repeatedTags['trailer']['repeatedTag']) {
						$trailerRowsNum++;
                        for ($k = 0; $k < count($this->input_array['trailer']); $k++) {
                            $trailerSubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array['trailer'][$k]['path']), $this->pathDelimiter);
                            $trailerSubPath = '//' . str_replace("." , "/" , $trailerSubPath);
                            $trailerReturndValue = $data->xpath($trailerSubPath);
                            if ($trailerReturndValue) {
                                $trailerValue = strval($trailerReturndValue[0]);
                            } else {
                                $trailerValue = '';
                            }
                            $this->trailerRows[$trailerRowsNum-1][$this->input_array['trailer'][$k]['name']] = $trailerValue;
                        }
                    }
                }
            }
        }
    }

    protected function preXmlBuilding() {
        foreach ($this->input_array as $segment => $indexes) {
            for ($a = 0; $a < count($indexes); $a++) {
                if (isset($this->input_array[$segment][$a])) {
                    if (isset($this->input_array[$segment][$a]['path'])) {
                        $this->pathes[] = $this->input_array[$segment][$a]['path'];
                        $this->pathesBySegment[$segment][] = $this->input_array[$segment][$a]['path'];
                    } else {
                        throw "No path for one of the " . $segment . "'s entity. No parse was made." . PHP_EOL;
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
                        throw "No pathes in " . $segment . " segment. No parse was made." . PHP_EOL;
                    } else {
                        Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: No pathes in ' . $segment . ' segment.' . $ex, Zend_Log::WARN);
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
    
        function simplexml_load_string_nons($xml, $sxclass = 'SimpleXMLElement', $nsattr = false, $flags = null){
	// Validate arguments first
	if(!is_string($sxclass) or empty($sxclass) or !class_exists($sxclass)){
		trigger_error('$sxclass must be a SimpleXMLElement or a derived class.', E_USER_WARNING);
		return false;
	}
	if(!is_string($xml) or empty($xml)){
		trigger_error('$xml must be a non-empty string.', E_USER_WARNING);
		return false;
	}
	// Load XML if URL is provided as XML
	if(preg_match('~^https?://[^\s]+$~i', $xml) || file_exists($xml)){
		$xml = file_get_contents($xml);
	}
	// Let's drop namespace definitions
	if(stripos($xml, 'xmlns=') !== false){
		$xml = preg_replace('~[\s]+xmlns=[\'"].+?[\'"]~i', null, $xml);
	}
	// I know this looks kind of funny but it changes namespaced attributes
	if(preg_match_all('~xmlns:([a-z0-9]+)=~i', $xml, $matches)){
		foreach(($namespaces = array_unique($matches[1])) as $namespace){
			$escaped_namespace = preg_quote($namespace, '~');
			$xml = preg_replace('~[\s]xmlns:'.$escaped_namespace.'=[\'].+?[\']~i', null, $xml);
			$xml = preg_replace('~[\s]xmlns:'.$escaped_namespace.'=["].+?["]~i', null, $xml);
			$xml = preg_replace('~([\'"\s])'.$escaped_namespace.':~i', '$1'.$namespace.'_', $xml);
		}
	}
	// Let's change <namespace:tag to <namespace_tag ns="namespace"
	$regexfrom = sprintf('~<([a-z0-9]+):%s~is', !empty($nsattr) ? '([a-z0-9]+)' : null);
	$regexto = strlen($nsattr) ? '<$1_$2 '.$nsattr.'="$1"' : '<$1_';
	$xml = preg_replace($regexfrom, $regexto, $xml);
	// Let's change </namespace:tag> to </namespace_tag>
	$xml = preg_replace('~</([a-z0-9]+):~is', '</$1_', $xml);
	// Default flags I use
	if(empty($flags)) $flags = LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NOCDATA;
	// Now load and return (namespaceless)
	return $xml = simplexml_load_string($xml, $sxclass, $flags);
}
    
}
