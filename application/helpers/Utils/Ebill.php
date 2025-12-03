<?php


/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Pelephone subscriber class
 *
 * @package  Bootstrap
 */
class Utils_Ebill
{
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
