<?php

namespace Payrexx\Models\Request;

use Payrexx\Models\Base;

class Payout extends Base
{
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\Payout();
    }
}
