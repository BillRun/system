<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 *
 * @author tomfeigin
 */
abstract class Billrun_DataTypes_Conf_Base {
	protected $val = null;
	
	public abstract function validate();
	public function value() {
		return $this->val;
	}
}
