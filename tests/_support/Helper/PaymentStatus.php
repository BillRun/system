<?php

namespace Helper;

class PaymentStatus extends \Codeception\Module
{
    public const PENDING = '2';
    
    public const PAID = [
        '$in' => ['true', true, 1, '1']
    ];
    
    public const UNPAID = [
        '$in' => ['false', false, 0, '0']
    ];
}