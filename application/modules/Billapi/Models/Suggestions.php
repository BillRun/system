<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Billapi model for Suggestions entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Suggestions extends Models_Entity {

	public static function isAllowedChangeDuringClosedCycle() {
		return true;
	}
}