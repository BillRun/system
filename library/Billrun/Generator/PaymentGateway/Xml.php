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

    public function __construct($options) {
        $this->input_array['headers'] = isset($options['headers']) ? $options['headers'] : null;
        $this->input_array['data'] = isset($options['data']) ? $options['data'] : null;
        $this->input_array['trailers'] = isset($options['trailers']) ? $options['trailers'] : null;
        $this->file_name = $options['file_name'];
        if (isset($options['local_dir'])) {
            $this->file_path = $options['local_dir'] . DIRECTORY_SEPARATOR . $options['file_name'];
        }
    }

    public function generate() {
        
        $result = $this->preXmlBuilding();

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
            echo 'No common path was found - abort.' . PHP_EOL;
            return;
        }
        $rootNode = $doc->createElement($firstTag);
        $this->createXmlRoot($doc, $rootNode);
        $document = $doc->appendChild($rootNode);

        $flag = 0;
        foreach ($this->workingArray as $segment => $values) {
                for ($a = 0; $a < count($values); $a++) {
                    $b = $doc->createElement($tags[$segment]['repeatedTag']);
                    $pathAsArray = $this->pathAsArray($segment, $tags[$segment]['repeatedTag'], $a);

                    $node = $doc->appendChild($doc->createElement(array_shift($pathAsArray)));
                    $this->buildNode($segment, $doc, $node, $pathAsArray, $a);

                    $document->appendChild($node);
                }
        }
        $doc->save($this->file_path);
    }

    protected function buildNode($segment, $doc, &$node, $pathAsArray, $index) {
        if (count($pathAsArray) == 1) {
            $currentTag = array_shift($pathAsArray);
            $element = $doc->createElement($currentTag, $this->workingArray[$segment][$index]['value']);
            if (isset($this->workingArray[$segment][$index]['attribute'])) {
                $element->setAttribute($this->input_array[$segment][$index]['attribute']['name'], $this->input_array[$segment][$index]['attribute']['value']);
            }
            $node->appendChild($element);
        } else {
            if (count($pathAsArray) == 0) {
                return;
            }
            $currentTag = array_shift($pathAsArray);
            $element = $doc->createElement($currentTag);
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
                        $this->workingArray[$segment][] = array('path' => $curentPathes[$i], 'value' => $this->input_array[$segment][$a][$curentPathes[$i]]['value']);
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
                        echo 'Warning: No pathes in ' . $segment . ' segment.' . PHP_EOL;
                    }
                }
            }
        }
        return $returnedValue;
    }

    protected function createXmlRoot($doc, &$rootNode) {
        if (count($this->commonPathAsArray) == 1) {
            $currentTag = array_shift($this->commonPathAsArray);
            $element = $doc->createElement($currentTag);
            $rootNode->appendChild($element);
        } else {
            if (count($this->commonPathAsArray) == 0) {
                return;
            }
        }
        $this->createXmlRoot($doc, $element);
    }

}
