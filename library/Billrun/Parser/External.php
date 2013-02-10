<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Plugin
 *
 * @author eran
 */
class Billrun_Parser_External  extends Billrun_Parser_Base_Binary  {
	public function parse() {
		return $this->chain->trigger('parseData',array($this->getType(),$this->getLine(), &$this));
	}

	public function parseField($data, $fileDesc) {
		return $this->chain->trigger('parseField', array($this->getType(),$data, $fileDesc, &$this));
	}

	public function parseHeader($data) {
		return $this->chain->trigger('parseHeader', array($this->getType(),$data, &$this));
	}

	public function parseTrailer($data) {
		return $this->chain->trigger('parseTrailer', array($this->getType(),$data, &$this));
	}
	
}

?>
