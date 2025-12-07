<?php


/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for swiss eBill invoice
 * @package  Bootstrap
 */
class Utils_Ebill
{
    /*
    Util funciton for calculating CreditorReference (related to qr code, example can be seen in salt pdf invoice) tag in the xml invoice.
    The Creditor Reference is according to the ISO 11649 standard. The reference must be a minimum of 5
    and a maximum of 25 alphanumerical characters. Starting with RF, followed by the check digits (3rd
    and 4th digit). The check digit of the creditor reference must be calculated with modulo 97–10.
    */
    public static function buildCreditorReference($prefix, $aid, $separated = false)
    {
        $checkedNumber = Billrun_Utils_DataManipulation::addModulo10ToNumber(
            $prefix . str_pad($aid, 20, '0', STR_PAD_LEFT)
        );

        return $separated
            ? substr($checkedNumber, 0, 2) . ' ' . implode(' ', str_split(substr($checkedNumber, 2), 5))
            : $checkedNumber;
    }
}
