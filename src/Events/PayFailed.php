<?php

namespace Wsmallnews\Pay\Events;

use Wsmallnews\Pay\Models\PayRecord;

/**
 * 支付失败（回调报文为交易失败/关闭时触发；预下单异常走异常通道不触发本事件）。
 */
class PayFailed
{
    public function __construct(
        public readonly PayRecord $payRecord,
        public readonly string $reason,
    ) {}
}
