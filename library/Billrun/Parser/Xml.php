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

    public function __construct($options) {
        $this->input_array['header'] = isset($options['header_structure']) ? $options['header_structure'] : null;
        $this->input_array['data'] = isset($options['data_structure']) ? $options['data_structure'] : null;
        $this->input_array['trailer'] = isset($options['trailer_structure']) ? $options['trailer_structure'] : null;
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
        $GivenXml = simplexml_load_file($filename);
        if ($GivenXml === false) {
            Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: Couldn\'t open ' . $filename . ' file. Might missing \'<\' or \'/\'. Please check, and reprocess.' , Zend_Log::ALERT);
            return;
        }

        $xmlAsString = file_get_contents($filename);
        $fixedTag = $commonPathAsArray[(count($commonPathAsArray) - 1)];

        $parentNode = $GivenXml;
        $this->getParentNode($parentNode);
        $xmlIterator = new SimpleXMLIterator($xmlAsString);
		$headerRowsNum = $dataRowsNum = $trailerRowsNum = 0;
        for ($xmlIterator->rewind(); $xmlIterator->valid(); $xmlIterator->next()) {
            foreach ($xmlIterator->getChildren() as $currentChild => $data) {
                if (isset($repeatedTags['header']['repeatedTag'])) {
                    if ($currentChild === $repeatedTags['header']['repeatedTag']) {
						$headerRowsNum++;
                        for ($i = 0; $i < count($this->input_array['header']); $i++) {
                            $headerSubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array['header'][$i]['path']), $this->pathDelimiter);
                            $headerSubPath = '//' . str_replace(".", "/", $headerSubPath);
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
                            $dataSubPath = '//' . str_replace(".", "/", $dataSubPath);
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
                            $trailerSubPath = '//' . str_replace(".", "/", $trailerSubPath);
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

}
