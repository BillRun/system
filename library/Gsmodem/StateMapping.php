<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ModemStatesMapping
 *
 * @author eran
 */
class Gsmodem_StateMapping {
	
		const CALLING_STATE = 'calling_state';
		const IDLE_STATE = 'idle_state';
		const ANSWERING_STATE = 'answering_state';
		const RINGING_STATE = 'ringing_state';
		const HANGING_UP_STATE = 'hanging_up_state';
		const IN_CALL_STATE = 'in_call_state';
		const OUT_CALL_STATE = 'outgoing_call_state';
	
		public $resultToStateMapping = array(
					self::OUT_CALL_STATE => array(
							'NO ANSWER' => self::IDLE_STATE,
							'BUSY' => self::IDLE_STATE,
							'ERROR' => self::IDLE_STATE,
							'NO CARRIER' => self::IDLE_STATE,									
						),
					self::IN_CALL_STATE =>	array(
							'NO ANSWER' => self::IDLE_STATE,
							'BUSY' => self::IDLE_STATE,
							'NO CARRIER' => self::IDLE_STATE,							
					),
					self::CALLING_STATE =>	array(
							'OK' => self::OUT_CALL_STATE,
							'ERROR' => self::IDLE_STATE,
							'RING' => self::RINGING_STATE,
							'BUSY' => self::IDLE_STATE,
					),
					self::RINGING_STATE =>	array(
							'RING' => self::RINGING_STATE,
					),
					self::ANSWERING_STATE =>	array(
							'OK' => self::IN_CALL_STATE,
							'ERROR' => self::IDLE_STATE,
					),

					self::HANGING_UP_STATE =>	array(
							'OK' => self::IDLE_STATE,
					),
					self::IDLE_STATE => array(							
							'ERROR' => self::IDLE_STATE,
							'RING' => self::RINGING_STATE,
					),
	);

	public $commandToStateMapping = array(
					self::IDLE_STATE => array(							
							'^ATD' => self::CALLING_STATE,							
					),
					self::RINGING_STATE => array(
						'^ATA' => self::ANSWERING_STATE,
						'^ATH' => self::HANGING_UP_STATE,
					),
					self::IN_CALL_STATE => array(							
							'^ATH' => self::HANGING_UP_STATE,							
					),
					self::CALLING_STATE =>	array(
							'^ATH' => self::HANGING_UP_STATE,
					),
					self::OUT_CALL_STATE => array(							
							'^ATH' => self::HANGING_UP_STATE,							
					),
	);
}
