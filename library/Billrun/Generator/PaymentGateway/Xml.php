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

    public function __construct($options) {
        $this->input_array['headers'] = isset($options['headers']) ? $options['headers'] : null;
        $this->input_array['data'] = isset($options['data']) ? $options['data'] : null;
        $this->input_array['trailers'] = isset($options['trailers']) ? $options['trailers'] : null;
        $this->name_space = isset($options['configByType']['generator']['name_space']) ? $options['configByType']['generator']['name_space'] : $this->name_space;
        $this->root_NS = isset($options['configByType']['generator']['root_attribute']) ? $options['configByType']['generator']['root_attribute'] : $this->root_NS;
        $this->file_name = $options['file_name'];
        if (isset($options['local_dir'])) {
            $this->file_path = $options['local_dir'] . DIRECTORY_SEPARATOR . $options['file_name'];
        }
    }

    public function generate() {
        try{
            $result = $this->preXmlBuilding();
        }catch(Exception $ex){
            Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: ' . $ex->getMessage(), Zend_Log::ALERT);
            return;
        }
        

        foreach ($result as $segment => $repeatedTag) {
            $tags[$segment]['repeatedTag'] = $repeatedTag['repeatedTag'];
        }

        $doc = new DOMDocument();
        $doc->encoding = 'utf-8';
        $doc->xmlVersion = '1.0';
        $doc->formatOutput = true;
        $xml_file_name = $this->file_name;

        $this->commonPathAsArray = explode($this->pathDelimiter, $this->commonPath);
        $firstTag = array_shift($this->commonPathAsArray);
        if($this->commonPath === ""){
            Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: No common path was found - abort.' , Zend_Log::ERR);
            return;
        }
        //$rootNode = $doc->createElement($this->name_space . ':' . $this->commonPathAsArray[count($this->commonPathAsArray) - 1]);
        //$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $this->name_space, $this->root_NS);
        $rootNode = $doc->createElement($this->name_space . ':' . $this->commonPathAsArray[count($this->commonPathAsArray) - 1]);
        //$this->createXmlRoot($doc, $rootNode);
        $document = $doc->appendChild($rootNode);
        //echo $document->ownerDocument->saveXML($document) . PHP_EOL . PHP_EOL;
        $flag = 0;
        foreach ($this->workingArray as $segment => $values) {
                
                    $b = $doc->createElement($this->name_space . ':' . $tags[$segment]['repeatedTag']);
                    
                    //echo $b->ownerDocument->saveXML($b) . PHP_EOL . PHP_EOL;
                    for ($a = 0; $a < count($values); $a++) {
                        $pathAsArray = $this->pathAsArray($segment, $tags[$segment]['repeatedTag'], $a);
                        
                        if(count($pathAsArray) == 2){
                            array_shift($pathAsArray);
                            $node = $doc->createElement($this->name_space . ':' . array_shift($pathAsArray), $this->workingArray[$segment][$a]['value']);
                        }else{
                            array_shift($pathAsArray);
                            $node = $doc->appendChild($doc->createElement($this->name_space . ':' . array_shift($pathAsArray)));
                            $this->buildNode($segment, $doc, $node, $pathAsArray, $a);
                        }
                        
                    
                        //echo $node->ownerDocument->saveXML($node) . PHP_EOL . PHP_EOL;
                    
                        

                        $b->appendChild($node);
                    
                        //echo $b->ownerDocument->saveXML($b) . PHP_EOL . PHP_EOL;
                    }
                    $document->appendChild($b);
                    //echo $document->ownerDocument->saveXML($document) . PHP_EOL . PHP_EOL;
        }
        $root = $doc->createElement($this->name_space . ':' . $firstTag);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $this->name_space, $this->root_NS);
        $root->appendChild($document);
        $doc->loadXML($root->ownerDocument->saveXML($root));
        $doc->save($this->file_path);
    }

    protected function buildNode($segment, $doc, &$node, $pathAsArray, $index) {
        if (count($pathAsArray) == 1) {
            $currentTag = array_shift($pathAsArray);
            $element = $doc->createElement($this->name_space . ':' . $currentTag, $this->workingArray[$segment][$index]['value']);
            if ((isset($this->workingArray[$segment][$index]['attributes'])) && (count($this->workingArray[$segment][$index]['attributes']) > 0)) {
                for($i = 0; $i < count($this->workingArray[$segment][$index]['attributes']); $i++){
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

    protected function preXmlBuilding() {
        foreach ($this->input_array as $segment => $indexes) {
            for ($a = 0; $a < count($indexes); $a++) {
                if (isset($this->input_array[$segment][$a])) {
                    $curentPathes = array_keys($this->input_array[$segment][$a]);
                    for ($i = 0; $i < count($curentPathes); $i++) {
                        if((array_key_exists('attributes', $this->input_array[$segment][$a][$curentPathes[$i]])) && (isset($this->input_array[$segment][$a][$curentPathes[$i]]['attributes']))){
                            for($b = 0; $b < count($this->input_array[$segment][$a][$curentPathes[$i]]['attributes']); $b++){
                                $attributes[] = $this->input_array[$segment][$a][$curentPathes[$i]]['attributes'][$b];
                            }
                        }else{
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
                        throw "No pathes in " . $segment . " segment. No generate was made." . PHP_EOL;
                    } else {
                        Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: No pathes in ' . $segment . ' segment.' , Zend_Log::WARN);
                    }
                }
            }
        }
        return $returnedValue;
    }

    protected function createXmlRoot($doc, &$rootNode) {
        if (count($this->commonPathAsArray) == 1) {
            $currentTag = array_shift($this->commonPathAsArray);
            $element = $doc->createElement($this->name_space . ':' .$currentTag);
            $rootNode->appendChild($element);
        } else {
            if (count($this->commonPathAsArray) == 0) {
                return;
            }
        }
        $this->createXmlRoot($doc, $element);
    }

}
