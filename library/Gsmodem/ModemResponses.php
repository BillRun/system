<?php

/**
 * @package         Gsmodem
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Gsmodem AT command  interface class
 *
 * @package  Gsmodem
 * @since    0.1
 */
class Gsmodem_ModemResponses {
		public static $modemResponseMapping = array(
							'NO ANSWER' => Gsmodem_ModemState::IDLE_STATE,
							'BUSY' => Gsmodem_ModemState::IDLE_STATE,
							'ERROR' => Gsmodem_ModemState::IDLE_STATE,
							'NO CARRIER' => Gsmodem_ModemState::IDLE_STATE,									
							'NO ANSWER' => Gsmodem_ModemState::IDLE_STATE,
							'BUSY' => Gsmodem_ModemState::IDLE_STATE,
							'NO CARRIER' => Gsmodem_ModemState::IDLE_STATE,							
							'OK' => Gsmodem_ModemState::OUT_CALL_STATE,
							'ERROR' => Gsmodem_ModemState::IDLE_STATE,
							'RING' => Gsmodem_ModemState::RINGING_STATE,
							'BUSY' => Gsmodem_ModemState::IDLE_STATE,
							'RING' => Gsmodem_ModemState::RINGING_STATE,
							'OK' => Gsmodem_ModemState::IN_CALL_STATE,
							'ERROR' => Gsmodem_ModemState::IDLE_STATE,
							'OK' => Gsmodem_ModemState::IDLE_STATE,
							'ERROR' => Gsmodem_ModemState::IDLE_STATE,
							'RING' => Gsmodem_ModemState::RINGING_STATE,
			);
}
