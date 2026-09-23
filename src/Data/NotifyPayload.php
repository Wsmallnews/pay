<?php

namespace Wsmallnews\Pay\Data;

/**
 * 回调验签后的标准化通知载荷（各渠道适配器负责把原始报文翻译成本结构）。
 */
class NotifyPayload
{
    public function __construct(
        public readonly string $paySn,              // 商户单号（out_trade_no）
        public readonly ?string $transactionId,     // 渠道交易单号
        public readonly int $amount,                // 实收金额（整数分，通知报文金额）
        public readonly string $currency,           // 通知币种
        public readonly bool $success,              // 交易是否成功
        public readonly ?string $successTime = null,
        public readonly mixed $buyerInfo = null,    // 付款人渠道侧信息（openid 等）
        public readonly mixed $origin = null,       // 渠道原始报文（审计用）
        public readonly ?string $refundSn = null,   // 退款单号（退款回调时存在）
    ) {}
}
