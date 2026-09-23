<?php

namespace Wsmallnews\Pay\Data;

use Wsmallnews\Pay\Contracts\PayerInterface;
use Wsmallnews\Pay\Models\PayRecord;
use Wsmallnews\Pay\Models\Refund;

/**
 * 退款请求载荷（退款单已由 operator 创建，携带退款单号供渠道 out_refund_no 使用）。
 */
class RefundPayload
{
    public function __construct(
        public readonly PayRecord $payRecord,
        public readonly Refund $refund,
        public readonly int $refundFee,             // 退款金额（整数分，订单币种）
        public readonly ?PayerInterface $payer = null,
        public readonly string $refundType = 'back',    // back 原路退回 / balance 退到钱包
        public readonly string $remark = '',
        public readonly array $extra = [],
    ) {}
}
