<?php

namespace Wsmallnews\Pay\Data;

use Wsmallnews\Pay\Enums\PayStatus;
use Wsmallnews\Pay\Models\PayRecord;

/**
 * 支付结果。
 *
 * $sdkResult：第三方预下单返回的原始结果（二维码串/唤起参数/跳转 Response 等），
 * 由调用方按 method 消费；余额支付为 null（支付已直接完成）。
 */
class PayResult
{
    public function __construct(
        public readonly PayRecord $payRecord,
        public readonly mixed $sdkResult = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->payRecord->status === PayStatus::Paid;
    }
}
