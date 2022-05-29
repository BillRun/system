<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generator XML for payment gateways files
 */
class Billrun_Generator_PaymentGateway_Xml {

    protected $input_array = [];
    protected $workingArray = [];
    protected $pathes = [];
    protected $parents = [];
    protected $pathDelimiter = '.';
    protected $file_name;
    protected $file_path;
    protected $local_dir;
    protected $SegmentsPathesAndValues;
    protected $pathesBySegment;
    protected $commonPath;
    protected $commonPathAsArray;
    protected $name_space = "";
    protected $root_NS = "";
    protected $attributes;
    protected $repeatedTags;
    protected $encoding = 'utf-8';
    protected $transactionsCounter = 0;

    
    public function __construct($options) {
        $this->validateOptions($options['configByType']);
        $this->name_space = isset($options['configByType']['generator']['name_space']) ? $options['configByType']['generator']['name_space'] : $this->name_space;
        $this->root_NS = isset($options['configByType']['generator']['root_attribute']) ? $options['configByType']['generator']['root_attribute'] : $this->root_NS;
        $this->encoding = isset($options['configByType']['generator']['encoding']) ? $options['configByType']['generator']['encoding'] : $this->encoding;
        if (isset($options['local_dir'])) {
            $this->local_dir = $options['local_dir'];
        }
    }

    /**
	 * validate the config.
	 *
	 * @param  array   $options   Relevant params from the config
	 * @return true - in case all the expected config params exist, and if the config is built as expected, error message - otherwise.
	 */ 
    protected static function validateOptions($config){
        $structures = ['header_structure', 'data_structure', 'trailer_structure'];
        $structuresArray = array();
        for($i = 0; $i < count($structures); $i++){
            $structuresArray[$structures[$i]] = $config['generator'][$structures[$i]];
        }
        foreach ($structuresArray as $segment => $indexes) {
            for ($a = 0; $a < count($indexes); $a++) {
                if (isset($structuresArray[$segment][$a])) {
                    $curentPathes = array_keys($structuresArray[$segment][$a]);
                    for ($i = 0; $i < count($curentPathes); $i++) {
                        if ((in_array('attributes', $curentPathes)) && (isset($structuresArray[$segment][$a]['attributes']))) {
                            for ($b = 0; $b < count($structuresArray[$segment][$a]['attributes']); $b++) {
                                if(empty($structuresArray[$segment][$a]['attributes'][$b]['key']) || empty($structuresArray[$segment][$a]['attributes'][$b]['value'])){
                                    throw new Exception("One of the attributes's key/value is missing. No generate was made.", Zend_Log::ALERT);
                                }
                            }
                        }
                        $pathes[] = $structuresArray[$segment][$a]['path'];
                        $pathesBySegment[$segment][] = $structuresArray[$segment][$a]['path'];
                    }
                }
            }
        }
        sort($pathes);
        if (count($pathes) > 1) {
            $commonPrefix = array_shift($pathes);  // take the first item as initial prefix
            $length = strlen($commonPrefix);
            foreach ($pathes as $item) {
                // check if there is a match; if not, decrease the prefix by one character at a time
                while ($length && substr($item, 0, $length) !== $commonPrefix) {
                    $length--;
                    $commonPrefix = substr($commonPrefix, 0, -1);
                }
                if (!$length) {
                    break;
                }
            }
            $LastPointPosition = strrpos($commonPrefix, '.', 0);
            $commonPrefix = substr($commonPrefix, 0, $LastPointPosition);
            $commonPrefix = rtrim($commonPrefix, '.');
            if((count($structuresArray['data_structure']) == 0) || (count($structuresArray['header_structure']) == 0)){
                $lastDelimiterPos = strrpos($commonPrefix, '.', 0);
                $commonPrefix = substr($commonPrefix, 0, $lastDelimiterPos);
                $commonPrefix = rtrim($commonPrefix, '.');
            }
            $commonPath = $commonPrefix;
        }
        foreach ($pathesBySegment as $segment => $paths) {
            if (count($pathesBySegment[$segment]) > 1) {
                sort($pathesBySegment[$segment]);
                $commonPrefix = array_shift($pathesBySegment[$segment]);  // take the first item as initial prefix
                $length = strlen($commonPrefix);
                foreach ($pathesBySegment[$segment] as $item) {
                    // check if there is a match; if not, decrease the prefix by one character at a time
                    while ($length && substr($item, 0, $length) !== $commonPrefix) {
                        $length--;
                        $commonPrefix = substr($commonPrefix, 0, -1);
                    }
                    if (!$length) {
                        break;
                    }
                }
                $LastPointPosition = strrpos($commonPrefix, '.', 0);
                $commonPrefix = substr($commonPrefix, 0, $LastPointPosition);
                $commonPrefix = rtrim($commonPrefix, '.');
                $repeatedPrefix = trim(str_replace($commonPath, "", $commonPrefix), '.');
                $returnedValue[$segment] = ['repeatedTag' => $repeatedPrefix];
            } else {
                if (count($pathesBySegment[$segment]) == 1) {
                    $pathWithNoParents = str_replace($commonPath, "", $pathesBySegment[$segment][0]);
                    $pathWithNoParents = trim($pathWithNoParents, '.');
                    $firstPointPos = strpos($pathWithNoParents, '.');
                    $repeatedPrefix = substr_replace($pathWithNoParents, "", $firstPointPos);
                    if($repeatedPrefix !== $commonPath){
                        $returnedValue[$segment] = ['repeatedTag' => $repeatedPrefix];
                    }
                } else {
                    if ($segment === "data_structure") {
                        throw new Exception("No paths in data segment. No generate was made.", Zend_Log::ALERT);
                    }
                }
            }
        }
        if($commonPath == ""){
            throw new Exception("Billrun_Generator_PaymentGateway_Xml: No common path was found. No generate was made.", Zend_Log::ALERT);
        }
        foreach($structuresArray as $segment => $data){
            if((count($structuresArray[$segment]) !== 0) && ((!isset($returnedValue[$segment]) || (count($returnedValue[$segment]) == 0) || empty($returnedValue[$segment]['repeatedTag'])))){
                throw new Exception($segment . " segment has paths, without repeated tag. No generate was made.", Zend_Log::ALERT);
            }
        }
        return true;    
    }

    
    public function generate() {
        $this->preXmlBuilding();
        $result = $this->repeatedTags;
        foreach ($result as $segment => $repeatedTag) {
            $tags[$segment]['repeatedTag'] = $repeatedTag['repeatedTag'];
        }

        $doc = new DOMDocument('1.0');
        $doc->formatOutput = true;
        $xml_file_name = $this->file_name;

        $this->commonPathAsArray = explode($this->pathDelimiter, $this->commonPath);
        $firstTag = array_shift($this->commonPathAsArray);
        $rootNode = $doc->createElement($this->name_space . ':' . $this->commonPathAsArray[count($this->commonPathAsArray) - 1]);
        $document = $doc->appendChild($rootNode);
        $flag = 0;
        foreach ($this->workingArray as $segment => $indexes) {
            foreach($indexes as $nodeIndex => $values){
                for ($a = 0; $a < count($values); $a++) {
                    if ($a == 0) {
                            $pathAsArray = $this->pathAsArray($segment, $tags[$segment]['repeatedTag'], $nodeIndex, $a);
                            $val = $this->workingArray[$segment][$nodeIndex][$a]['value'];
                        $pathAndValueAsArr = array();
                        Billrun_Util::setIn($pathAndValueAsArr, $pathAsArray, $val);

                        $nodeArray = $pathAndValueAsArr;
                        continue 1;
                    }

                        $pathAsArray = $this->pathAsArray($segment, $tags[$segment]['repeatedTag'], $nodeIndex, $a);
                        $val = $this->workingArray[$segment][$nodeIndex][$a]['value'];
                    $pathAndValueAsArr = array();
                    Billrun_Util::setIn($pathAndValueAsArr, $pathAsArray, $val);
                    $this->set_in_without_override($nodeArray, $pathAndValueAsArr);

                }
                $this->newNode($nodeArray, $document, $doc);
                if($segment === "data"){
                    $this->transactionsCounter++;
                }
            }
        }
        $root = $doc->createElement($this->name_space . ':' . $firstTag);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $this->name_space, $this->root_NS);
        $root->appendChild($document);
        $doc->loadXML($root->ownerDocument->saveXML($root));
        $xpath = new DOMXpath($doc);
        
        if(isset($this->attributes)){
            foreach($this->attributes as $path => $attribute){
                $query = '/' . $this->name_space . ':' . str_replace('.', '/' . $this->name_space . ':', $path);
            
                $elements = $xpath->query($query);
                    foreach($elements as $element){
                        $element->setAttribute($attribute['key'], $attribute['value']);
                    }
            }
        }
        $doc->encoding = $this->encoding;
        $doc->save($this->file_path);
    }

    /**
     * The function gets an xml node's path (a.b.c...), and return it as array, whose values are the tags. 
     * Also, slice the file's common path, and the segment's repeated tag.
     * @param string $segment - headers/data/trailer.
     * @param string $repeatedTag - the tag that repeats itself in the given segment. 
     * @param int $nodeIndex - the index of the segment's row.
     * @param int $currentDataFieldIndex - current field index.
     * @return the relative path (from the repeated tag) of this field in the file.
     */
    protected function pathAsArray($segment, $repeatedTag, $nodeIndex, $currentDataFieldIndex) {
        $path = $this->workingArray[$segment][$nodeIndex][$currentDataFieldIndex]['path'];
        $path = str_replace($this->commonPath, "", $path);
        $pathAsArray = explode($this->pathDelimiter, $path);
        for ($i = 0; $i < count($pathAsArray); $i++) {
            if ($pathAsArray[$i] == $repeatedTag) {
                $pathAsArray = array_slice($pathAsArray, $i, NULL, TRUE);
                break;
            }
        }
        return $pathAsArray;
    }

    protected function createXmlRoot($doc, &$rootNode) {
        if (count($this->commonPathAsArray) == 1) {
            $currentTag = array_shift($this->commonPathAsArray);
            $element = $doc->createElement($this->name_space . ':' . $currentTag);
            $rootNode->appendChild($element);
        } else {
            if (count($this->commonPathAsArray) == 0) {
                return;
            }
        }
        $this->createXmlRoot($doc, $element);
    }

    protected function set_in_without_override(&$base_array, $addition_array) {
        //adding the "addition_array", aithout overriding nothing in "base_array".
        if (!is_array($addition_array)) {
            return;
        }
        if (!is_array($base_array)) {
            $base_array = $addition_array;
        }
        foreach ($addition_array as $key => $value) {
            if (array_key_exists($key, $base_array) && is_array($value)) {
                $this->set_in_without_override($base_array[$key], $addition_array[$key]);
            } else {
                $base_array[$key] = $value;
            }
        }
    }

    /**
     * The function creates new xml node, according to the array that is given.
     * @param array $arr - xml node as array.
     * @param DomElement $node - xml node, to add the $arr to - as xml node.
     * @param DomDocument $doc - to use Dom's functions.
     * 
     */
    protected function newNode($arr, $node, $doc) {
        if (is_null($node)){
            $node = $this->appendChild($this->name_space . ':' . $doc->createElement("items"));
        }

        foreach ($arr as $element => $value) {
            $element = is_numeric($element) ? "node" : $element;
            $newElement = $doc->createElement($this->name_space . ':' . $element, (is_array($value) ? null : $value));
            $node->appendChild($newElement);

            if (is_array($value)) {
                $this->newNode($value, $newElement,$doc);
            }
        }
    }

    /**
     * Preparation function:
     * Function that pulls out all the information from the input data, like attributes,
     * parents tags, repeated tags.
     */
    protected function preXmlBuilding() {
        foreach ($this->input_array as $segment => $indexes) {
            for ($a = 0; $a < count($indexes); $a++) {
                if (isset($this->input_array[$segment][$a])) {
                    $curentPathes = array_keys($this->input_array[$segment][$a]);
                    for ($i = 0; $i < count($curentPathes); $i++) {
                        if ((array_key_exists('attributes', $this->input_array[$segment][$a][$curentPathes[$i]])) && (isset($this->input_array[$segment][$a][$curentPathes[$i]]['attributes']))) {
                            for ($b = 0; $b < count($this->input_array[$segment][$a][$curentPathes[$i]]['attributes']); $b++) {
                                $attributes[] = $this->input_array[$segment][$a][$curentPathes[$i]]['attributes'][$b];
                                $this->attributes[$curentPathes[$i]] = array('key' => $this->input_array[$segment][$a][$curentPathes[$i]]['attributes'][$b]['key'], 'value' => $this->input_array[$segment][$a][$curentPathes[$i]]['attributes'][$b]['value']);
                            }
                        } else {
                            $attributes = array();
                        }
                        $this->workingArray[$segment][$a][] = array('path' => $curentPathes[$i], 'value' => $this->input_array[$segment][$a][$curentPathes[$i]]['value'], 'attributes' => $attributes);
                        unset($attributes);
                        $this->pathes[] = $curentPathes[$i];
                        $this->pathesBySegment[$segment][] = $curentPathes[$i];
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
            if((count($this->input_array['data']) == 0) || (count($this->input_array['headers']) == 0)){
                $lastDelimiterPos = strrpos($commonPrefix, $this->pathDelimiter, 0);
                $commonPrefix = substr($commonPrefix, 0, $lastDelimiterPos);
                $commonPrefix = rtrim($commonPrefix, $this->pathDelimiter);
            }
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
                    if($repeatedPrefix !== $this->commonPath){
                        $returnedValue[$segment] = ['repeatedTag' => $repeatedPrefix];
                    }
                } else {
                        $message = 'Billrun_Generator_PaymentGateway_Xml: No paths in ' . $segment . ' segment.';
                        Billrun_Factory::log($message, Zend_Log::WARN);
                        $this->logFile->updateLogFileField('warnings', $message);
                }
            }
        }
        $this->repeatedTags = $returnedValue;
    }

    
    public function setFileName($fileName){
        $this->file_name = $fileName;
    }
    
    public function setFilePath($dir){
        $this->file_path = $dir . '/' . $this->file_name;
    }
    
    public function setDataRows($data) {
        $this->input_array['data'] = $data;
    }
    
    public function setHeaderRows($header) {
        $this->input_array['headers'] = $header;
    }
    
    public function setTrailerRows($trailer) {
        $this->input_array['trailers'] = $trailer;
    }
    
    public function getTransactionsCounter (){
        return $this->transactionsCounter;
    }
}
