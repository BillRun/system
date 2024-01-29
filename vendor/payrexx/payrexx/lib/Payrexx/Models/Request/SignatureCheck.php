<?php
/**
 * The signatureCheck request model
 * @author    Remo Siegenthaler <remo.siegenthaler@payrexx.com>
 * @copyright 2017 Payrexx AG
 * @since     v1.0
 */
namespace Payrexx\Models\Request;

/**
 * Class SignatureCheck
 * @package Payrexx\Models\Request
 */
class SignatureCheck extends \Payrexx\Models\Base
{
    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\SignatureCheck();
    }
}
