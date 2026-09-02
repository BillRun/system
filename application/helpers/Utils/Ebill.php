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
    /**
     * Utility function for calculating CreditorReference (related to QR code) in the XML invoice.
     * The Creditor Reference is according to the ISO 11649 standard. 
     * The reference must be a minimum of 5 and a maximum of 25 alphanumerical characters.
     * Starts with RF, followed by check digits (3rd and 4th digit).
     *
     * @param string $prefix    A numeric string used as the prefix.
     * @param int|string $aid   The Account ID number. This will be padded to 20 digits.
     * @param bool $separated   Determines display format. If true, returns a space-separated string; if false, returns a continuous string.
     * * @return string           The calculated Creditor Reference string starting with RF.
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
