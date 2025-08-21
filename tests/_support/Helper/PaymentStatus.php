<?php

namespace Helper;

class PaymentStatus extends \Codeception\Module
{
    public const PENDING = '2';
    
    public const PAID = ['true', true, 1, '1'];
    
    public const UNPAID = ['false', false, 0, '0'];
}