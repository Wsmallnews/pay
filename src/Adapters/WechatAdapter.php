<?php

namespace Wsmallnews\Pay\Adapters;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wsmallnews\Pay\Adapters\Concerns\ManagesYansongda;
use Wsmallnews\Pay\Contracts\ThirdAdapterInterface;
use Wsmallnews\Pay\Data\NotifyPayload;
use Wsmallnews\Pay\Data\PayPayload;
use Wsmallnews\Pay\Data\RefundPayload;
use Wsmallnews\Pay\Enums\PayStatus;
use Wsmallnews\Pay\Enums\RefundStatus;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Pay\Models\PayRecord;
use Wsmallnews\Pay\PayManager;
use Yansongda\Pay\Pay as YansongdaPay;

/**
 * 微信支付渠道适配器（yansongda/pay v3）。
 *
 * 支付方式（终端）→ yansongda 端点：mp(公众号) / mini(小程序) / app / h5 / scan。
 * 微信仅支持 CNY；金额一律整数分（微信 v3 即分）。
 * openid 等业务参数由调用方经 PayPayload->extra 传入，本适配器不处理业务逻辑。
 */
class WechatAdapter implements ThirdAdapterInterface
{
    use ManagesYansongda;

    public function __construct(protected PayManager $payManager) {}

    public function getChannel(): string
    {
        return 'wechat';
    }

    /**
     * 支付方式（终端）→ yansongda 端点映射
     */
    protected function endpoint(string $method): string
    {
        return match ($method) {
            'mp', 'mini', 'app', 'h5', 'scan' => $method,
            default => throw new PayException("Unsupported wechat pay method [{$method}]."),
        };
    }

    public function pay(PayPayload $payload): array
    {
        if ($payload->currency !== 'CNY') {
            throw new PayException('Wechat channel only supports CNY, convert the currency first.');
        }

        return [
            'status' => PayStatus::Unpaid,
            'real_fee' => $payload->amount,
        ];
    }

    public function prepay(PayRecord $payRecord, PayPayload $payload, array $extra = []): mixed
    {
        $this->bootYansongda();

        $endpoint = $this->endpoint($payload->method);
        $tenant = $extra['_config'] ?? 'default';
        $tenantConfig = $this->tenants[$tenant] ?? null;

        if (! $tenantConfig) {
            throw new PayException("Wechat tenant config [{$tenant}] is missing.");
        }

        $order = [
            'out_trade_no' => $payRecord->pay_sn,
            'amount' => [
                'total' => sn_money()->minor($payRecord->pay_fee),
                'currency' => 'CNY',
            ],
            'description' => $extra['description'] ?? '订单支付',
            'notify_url' => $extra['notify_url'] ?? route('sn-pay.notify', ['channel' => 'wechat', 'method' => $payload->method]),
            '_config' => $tenant,
        ];

        if (in_array($endpoint, ['mp', 'mini'])) {
            // 公众号/小程序支付必须携带用户 openid（业务方传入）
            $openid = $extra['openid'] ?? null;

            if (blank($openid)) {
                throw new PayException("Wechat [{$endpoint}] pay requires the user openid via extra.");
            }

            if ((int) ($tenantConfig['mode'] ?? 0) === YansongdaPay::MODE_SERVICE) {
                // 服务商模式：openid 挂到 sub_openid
                $order['payer'] = ['sub_openid' => $openid];
            } else {
                $order['payer'] = ['openid' => $openid];
            }
        }

        if ($endpoint === 'h5') {
            $order['scene_info'] = [
                'payer_client_ip' => request()->ip(),
                'h5_info' => ['type' => 'Wap'],
            ];
        }

        return YansongdaPay::wechat()->{$endpoint}($order);
    }

    public function verifyNotify(Request $request): NotifyPayload
    {
        $this->bootYansongda();

        $origin = YansongdaPay::wechat()->callback();

        $eventType = $origin['event_type'] ?? '';
        $ciphertext = $origin['resource']['ciphertext'] ?? [];

        if ($eventType === 'REFUND.SUCCESS') {
            // 退款成功回调（结构同退款回调）
            return $this->parseRefundNotify($origin, $ciphertext);
        }

        $tradeState = $ciphertext['trade_state'] ?? '';

        return new NotifyPayload(
            paySn: (string) ($ciphertext['out_trade_no'] ?? ''),
            transactionId: $ciphertext['transaction_id'] ?? null,
            amount: (int) ($ciphertext['amount']['total'] ?? 0),
            currency: (string) ($ciphertext['amount']['currency'] ?? 'CNY'),
            success: $eventType === 'TRANSACTION.SUCCESS' && $tradeState === 'SUCCESS',
            successTime: $ciphertext['success_time'] ?? null,
            buyerInfo: $ciphertext['payer']['openid'] ?? ($ciphertext['payer']['sub_openid'] ?? null),
            origin: $origin,
        );
    }

    public function verifyRefundNotify(Request $request): NotifyPayload
    {
        $this->bootYansongda();

        $origin = YansongdaPay::wechat()->callback();

        return $this->parseRefundNotify($origin, $origin['resource']['ciphertext'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $ciphertext
     */
    protected function parseRefundNotify(array $origin, array $ciphertext): NotifyPayload
    {
        return new NotifyPayload(
            paySn: (string) ($ciphertext['out_trade_no'] ?? ''),
            transactionId: $ciphertext['transaction_id'] ?? null,
            amount: (int) ($ciphertext['amount']['refund'] ?? 0),
            currency: (string) ($ciphertext['amount']['currency'] ?? 'CNY'),
            success: ($origin['event_type'] ?? '') === 'REFUND.SUCCESS' && ($ciphertext['refund_status'] ?? '') === 'SUCCESS',
            successTime: $ciphertext['success_time'] ?? null,
            buyerInfo: null,
            origin: $origin,
            refundSn: $ciphertext['out_refund_no'] ?? null,
        );
    }

    public function buildNotifyResponse(bool $success): Response
    {
        if ($success) {
            return YansongdaPay::wechat()->success();
        }

        return response('fail', 500);
    }

    public function refund(RefundPayload $payload): array
    {
        $this->bootYansongda();

        $payRecord = $payload->payRecord;
        $refund = $payload->refund;

        $order = [
            'out_trade_no' => $payRecord->pay_sn,
            'out_refund_no' => $refund->refund_sn,
            'amount' => [
                'refund' => sn_money()->minor($refund->refund_fee),
                'total' => sn_money()->minor($payRecord->pay_fee),
                'currency' => $payRecord->currency,
            ],
            'reason' => $refund->remark ?: '订单退款',
            '_config' => $payload->extra['_config'] ?? 'default',
        ];

        $result = YansongdaPay::wechat()->refund($order);

        // 微信退款为异步：受理成功即 PROCESSING（偶发直接 SUCCESS），完成态经退款回调流转
        $status = in_array($result['status'] ?? '', ['SUCCESS', 'PROCESSING'], true)
            ? RefundStatus::Ing
            : throw new PayException('Wechat refund failed: ' . ($result['message'] ?? json_encode($result, JSON_UNESCAPED_UNICODE)));

        return [
            'status' => $status,
            'sdk_result' => $result,
        ];
    }
}
