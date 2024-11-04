<?php
/**
 * The Payrexx Exception for any exception occurred during the API process
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */

namespace Payrexx;

/**
 * Class PayrexxException
 * @package Payrexx
 */
class PayrexxException extends \Exception
{
    private $reason = '';

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($reason = '')
    {
        $this->reason = $reason;
    }
}
