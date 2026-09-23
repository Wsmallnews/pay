<?php

namespace Wsmallnews\Pay\Contracts;

use Wsmallnews\Pay\Data\PayPayload;
use Wsmallnews\Pay\Data\RefundPayload;
use Wsmallnews\Pay\Enums\PayStatus;
use Wsmallnews\Pay\Enums\RefundStatus;

/**
 * 支付渠道适配器接口。
 *
 * 适配器是纯渠道薄壳：SDK 参数拼装 + 调用 + 结果翻译；不落库（DB 写入统一由 PayOperator 负责）。
 * 任何第三方渠道（含聚合平台：汇付、随行付等）实现本接口（第三方另加 ThirdAdapterInterface）
 * 并经 PayManager::extend() 注册即可接入，无需 pay 包内置支持。
 */
interface AdapterInterface
{
    /**
     * 渠道标识（如 wechat / alipay / money / 自定义渠道名）
     */
    public function getChannel(): string;

    /**
     * 发起支付：余额类直接完成扣款（status = Paid）；第三方仅返回未支付状态（预下单由 ThirdAdapterInterface 承担）
     *
     * @return array{status: PayStatus, real_fee: int, options?: array<string, mixed>}
     *                                                                                 options 携带渠道侧附加信息（如钱包扣减快照，会固化进 PayRecord.options）
     */
    public function pay(PayPayload $payload): array;

    /**
     * 退款（退款单已创建，payload->refund->refund_sn 即渠道侧 out_refund_no）
     *
     * @return array{status: RefundStatus, sdk_result?: mixed, options?: array<string, mixed>}
     */
    public function refund(RefundPayload $payload): array;
}
