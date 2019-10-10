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
        } catch (Exception $ex) {
            Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: ' . $ex->getMessage(), Zend_Log::ALERT);
            return;
        }
        $commonPathAsArray = $this->pathAsArray($this->commonPath);
        if ($this->name_space_prefix !== "") {
            $GivenXml = simplexml_load_file($filename, 'SimpleXMLElement', 0, $this->name_space_prefix, TRUE);
        } else {
            $GivenXml = simplexml_load_file($filename);
        }
        if ($GivenXml === false) {
            Billrun_Factory::log('Billrun_Generator_PaymentGateway_Xml: Couldn\'t open ' . $filename . ' file. Please open it with chrom browser to find the problem, and reprocess.', Zend_Log::ALERT);
            return;
        }

        $GivenXml->registerXPathNamespace($this->name_space_prefix, $this->name_space);
        $xmlAsString = file_get_contents($filename);

        $fixedTag = $commonPathAsArray[(count($commonPathAsArray) - 1)];
        $parentNode = $GivenXml;
        $this->getParentNode($parentNode);
        $headerRowsNum = $dataRowsNum = $trailerRowsNum = 0;

        for($i = 0; $i < count($commonPathAsArray); $i++){
            $parentNode = $this->getParentNodeAccordingToNameSpaceValue($parentNode);
        }
        foreach ($parentNode as $currentChild => $data) {
            if (isset($repeatedTags['header']['repeatedTag'])) {
                if ($currentChild === $repeatedTags['header']['repeatedTag']) {
                    $headerRowsNum++;
                    for ($i = 0; $i < count($this->input_array['header']); $i++) {
                        $headerSubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array['header'][$i]['path']), $this->pathDelimiter);
                        if($this->name_space_prefix === ""){
                            $headerSubPath = '//' . str_replace(".", "/" , $headerSubPath);
                        }else{
                                $headerSubPath = '//' . $this->name_space_prefix . ':' . str_replace(".", "/" . $this->name_space_prefix . ':', $headerSubPath);
                        }
                        $headerReturndValue = $data->xpath($headerSubPath);
                        if ($headerReturndValue) {
                            $headerValue = strval($headerReturndValue[0]);
                        } else {
                            $headerValue = '';
                        }
                        $this->headerRows[$headerRowsNum - 1][$this->input_array['header'][$i]['name']] = $headerValue;
                    }
                }
            }
            if (isset($repeatedTags['data']['repeatedTag'])) {
                if ($currentChild === $repeatedTags['data']['repeatedTag']) {
                    $dataRowsNum++;
                    for ($i = 0; $i < count($this->input_array['data']); $i++) {
                        $dataSubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array['data'][$i]['path']), $this->pathDelimiter);
                        if($this->name_space_prefix === ""){
                            $dataSubPath = '//' . str_replace(".", "/" , $dataSubPath);
                        }else{
                                $dataSubPath = '//' . $this->name_space_prefix . ':' . str_replace(".", "/" . $this->name_space_prefix . ':', $dataSubPath);
                        }
                        $dataReturndValue = $data->xpath($dataSubPath);
                        if ($dataReturndValue) {
                            $dataValue = strval($dataReturndValue[0]);
                        } else {
                            $dataValue = '';
                        }
                        $this->dataRows[$dataRowsNum - 1][$this->input_array['data'][$i]['name']] = $dataValue;
                    }
                }
            }
            if (isset($repeatedTags['trailer']['repeatedTag'])) {
                if ($currentChild === $repeatedTags['trailer']['repeatedTag']) {
                    $trailerRowsNum++;
                    for ($i = 0; $i < count($this->input_array['trailer']); $i++) {
                        $trailerSubPath = trim(str_replace(($this->commonPath . '.' . $currentChild), "", $this->input_array['trailer'][$i]['path']), $this->pathDelimiter);
                        if($this->name_space_prefix === ""){
                            $trailerSubPath = '//' . str_replace(".", "/" , $trailerSubPath);
                        }else{
                                $trailerSubPath = '//' . $this->name_space_prefix . ':' . str_replace(".", "/" . $this->name_space_prefix . ':', $trailerSubPath);
                        }
                        $trailerReturndValue = $data->xpath($trailerSubPath);
                        if ($trailerReturndValue) {
                            $trailerValue = strval($trailerReturndValue[0]);
                        } else {
                            $trailerValue = '';
                        }
                        $this->trailerRows[$trailerRowsNum - 1][$this->input_array['trailer'][$i]['name']] = $trailerValue;
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

    public function getParentNodeAccordingToNameSpaceValue($parentNode) {
        if ($this->name_space_prefix !== "") {
            return $parentNode->children($this->name_space_prefix, true);
        } else {
            return $parentNode->children();
        }
    }

}
