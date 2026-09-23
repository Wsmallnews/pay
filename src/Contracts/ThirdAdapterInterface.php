<?php

namespace Wsmallnews\Pay\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wsmallnews\Pay\Data\NotifyPayload;
use Wsmallnews\Pay\Data\PayPayload;
use Wsmallnews\Pay\Models\PayRecord;

/**
 * 第三方支付渠道补充接口（微信、支付宝、聚合平台等）。
 */
interface ThirdAdapterInterface extends AdapterInterface
{
    /**
     * 预下单（发起第三方支付）：返回 SDK 原始结果
     * （二维码串 / 唤起参数 / 跳转 URL 等，由调用方按 method 消费）
     *
     * @param  array<string, mixed>  $extra  业务参数（openid、return_url、notify_url 覆盖等）
     */
    public function prepay(PayRecord $payRecord, PayPayload $payload, array $extra = []): mixed;

    /**
     * 验签并解析回调（内部完成签名校验，失败抛 PayException）
     */
    public function verifyNotify(Request $request): NotifyPayload;

    /**
     * 验签并解析退款回调
     */
    public function verifyRefundNotify(Request $request): NotifyPayload;

    /**
     * 构建回调应答（各渠道格式不同：微信 JSON/XML、支付宝 success 字符串）
     */
    public function buildNotifyResponse(bool $success): Response;
}
