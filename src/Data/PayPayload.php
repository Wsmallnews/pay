<?php

namespace Wsmallnews\Pay\Data;

use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayerInterface;

/**
 * 支付请求载荷（发起一笔支付的全部输入）。
 *
 * 业务参数（openid、return_url 等）只经由 extra 进入，pay 包不感知业务逻辑。
 */
class PayPayload
{
    public function __construct(
        public readonly string $paySn,              // 本笔支付单号（operator 预生成，适配器可带入渠道 meta）
        public readonly int $amount,                // 订单币种金额（整数分）
        public readonly string $currency,           // ISO 4217（单据快照）
        public readonly string $channel,            // 渠道：wechat / alipay / money / 自定义
        public readonly string $method,             // 支付方式（终端）：mp / mini / h5 / app / scan / balance ...
        public readonly PayableInterface $payable,
        public readonly ?PayerInterface $payer = null,
        public readonly ?string $walletType = null, // money 通道的钱包类型
        public readonly array $extra = [],          // 业务参数：openid / return_url / notify_url 覆盖 / _config 租户键等
    ) {}

    /**
     * 从 extra 取值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->extra[$key] ?? $default;
    }
}
