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
    protected $SegmentsPathesAndValues;
    protected $pathesBySegment;
    protected $commonPath;
    protected $commonPathAsArray;
    protected $name_space = "";
    protected $root_NS = "";
    protected $attributes;
    protected $repeatedTags;
    
    public function __construct($options) {
        $this->input_array['headers'] = isset($options['headers']) ? $options['headers'] : null;
        $this->input_array['data'] = isset($options['data']) ? $options['data'] : null;
        $this->input_array['trailers'] = isset($options['trailers']) ? $options['trailers'] : null;
        $response = $this->validateOptions($options);
        if($response !== true){
            throw new Exception($response);
        }
        $this->name_space = isset($options['configByType']['generator']['name_space']) ? $options['configByType']['generator']['name_space'] : $this->name_space;
        $this->root_NS = isset($options['configByType']['generator']['root_attribute']) ? $options['configByType']['generator']['root_attribute'] : $this->root_NS;
        $this->file_name = $options['file_name'];
        if (isset($options['local_dir'])) {
            $this->file_path = $options['local_dir'] . DIRECTORY_SEPARATOR . $options['file_name'];
        }
    }

    /**
	 * validate the config.
	 *
	 * @param  array   $options   Relevant params from the config
	 * @return true - in case all the expected config params exist, and if the config is built as expected, error message - otherwise.
	 */ 
    protected function validateOptions($options){
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
                        $this->workingArray[$segment][] = array('path' => $curentPathes[$i], 'value' => $this->input_array[$segment][$a][$curentPathes[$i]]['value'], 'attributes' => $attributes);
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
                    $returnedValue[$segment] = ['repeatedTag' => $repeatedPrefix];
                } else {
                    if ($segment === "data") {
                        return "No pathes in " . $segment . " segment. No generate was made." . PHP_EOL;
                    } else {
                        Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: No pathes in ' . $segment . ' segment.', Zend_Log::WARN);
                    }
                }
            }
        }
        if($this->commonPath == ""){
            return 'Billrun_Generator_PaymentGateway_Xml: No common path was found - abort.';
        }
        $this->repeatedTags = $returnedValue;
        return true;    }
    
    public function generate() {

        $result = $this->repeatedTags;
        foreach ($result as $segment => $repeatedTag) {
            $tags[$segment]['repeatedTag'] = $repeatedTag['repeatedTag'];
        }

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;
        $xml_file_name = $this->file_name;

        $this->commonPathAsArray = explode($this->pathDelimiter, $this->commonPath);
        $firstTag = array_shift($this->commonPathAsArray);
        
        $rootNode = $doc->createElement($this->name_space . ':' . $this->commonPathAsArray[count($this->commonPathAsArray) - 1]);
        $document = $doc->appendChild($rootNode);
        $flag = 0;
        foreach ($this->workingArray as $segment => $values) {

            for ($a = 0; $a < count($values); $a++) {
                if ($a == 0) {
                    $pathAsArray = $this->pathAsArray($segment, $tags[$segment]['repeatedTag'], $a);
                    $val = $this->workingArray[$segment][$a]['value'];
                    $pathAndValueAsArr = array();
                    Billrun_Util::setIn($pathAndValueAsArr, $pathAsArray, $val);
                    
                    $nodeArray = $pathAndValueAsArr;
                    continue 1;
                }

                $pathAsArray = $this->pathAsArray($segment, $tags[$segment]['repeatedTag'], $a);
                $val = $this->workingArray[$segment][$a]['value'];
                $pathAndValueAsArr = array();
                Billrun_Util::setIn($pathAndValueAsArr, $pathAsArray, $val);
                $this->set_in_without_override($nodeArray, $pathAndValueAsArr);

            }
            $this->newNode($nodeArray, $document, $doc);
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
        $doc->save($this->file_path);
    }

    protected function buildNode($segment, $doc, &$node, $pathAsArray, $index) {
        if (count($pathAsArray) == 1) {
            $currentTag = array_shift($pathAsArray);
            $element = $doc->createElement($this->name_space . ':' . $currentTag, $this->workingArray[$segment][$index]['value']);
            if ((isset($this->workingArray[$segment][$index]['attributes'])) && (count($this->workingArray[$segment][$index]['attributes']) > 0)) {
                for ($i = 0; $i < count($this->workingArray[$segment][$index]['attributes']); $i++) {
                    $element->setAttribute($this->workingArray[$segment][$index]['attributes'][$i]['key'], $this->workingArray[$segment][$index]['attributes'][$i]['value']);
                }
            }
            $node->appendChild($element);
        } else {
            if (count($pathAsArray) == 0) {
                return;
            }
            $currentTag = array_shift($pathAsArray);
            $element = $doc->createElement($this->name_space . ':' . $currentTag);
            $node->appendChild($element);
        }
        $this->buildNode($segment, $doc, $element, $pathAsArray, $index);
    }

    protected function pathAsArray($segment, $repeatedTag, $a) {
        $path = $this->workingArray[$segment][$a]['path'];
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

    protected function newNode($arr, $node, $doc) {
        if (is_null($node)){
            $node = $this->appendChild($this->name_space . ':' . $doc->createElement("items"));
        }

        foreach ($arr as $element => $value) {
            $element = is_numeric($element) ? "node" : $element;
            $newElement = $doc->createElement($this->name_space . ':' . $element, (is_array($value) ? null : $value));
            $node->appendChild($newElement);

            if (is_array($value)) {
                self::newNode($value, $newElement,$doc);
            }
        }
    }

}
