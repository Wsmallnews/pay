<?php

namespace Wsmallnews\Pay\Events;

use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Models\PayRecord;

/**
 * 支付成功（支付单被标记已支付后触发；业务方监听做发货/跳转等后续动作）。
 */
class PaySucceeded
{
    public function __construct(
        public readonly PayRecord $payRecord,
        public readonly PayableInterface $payable,
    ) {}
}
