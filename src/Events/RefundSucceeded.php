<?php

namespace Wsmallnews\Pay\Events;

use Wsmallnews\Pay\Models\PayRecord;
use Wsmallnews\Pay\Models\Refund;

/**
 * 退款成功。
 */
class RefundSucceeded
{
    public function __construct(
        public readonly Refund $refund,
        public readonly PayRecord $payRecord,
    ) {}
}
