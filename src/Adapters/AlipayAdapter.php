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
 * 支付宝渠道适配器（yansongda/pay v3）。
 *
 * 支付方式（终端）→ yansongda 端点：web(电脑网站) / wap(手机网站) / app / scan。
 * 支付宝报文金额为主单位（元字符串），出入参自动经 sn_money 换算；
 * 退款为同步接口（fund_change=Y 即成功），无需退款回调轮转。
 */
class AlipayAdapter implements ThirdAdapterInterface
{
    use ManagesYansongda;

    public function __construct(protected PayManager $payManager) {}

    public function getChannel(): string
    {
        return 'alipay';
    }

    /**
     * 支付方式（终端）→ yansongda 端点映射
     */
    protected function endpoint(string $method): string
    {
        return match ($method) {
            'web', 'wap', 'app', 'scan' => $method,
            default => throw new PayException("Unsupported alipay pay method [{$method}]."),
        };
    }

    public function pay(PayPayload $payload): array
    {
        if ($payload->currency !== 'CNY') {
            throw new PayException('Alipay channel only supports CNY, convert the currency first.');
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

        $order = [
            'out_trade_no' => $payRecord->pay_sn,
            'total_amount' => sn_money()->decimal($payRecord->pay_fee),
            'subject' => $extra['description'] ?? '订单支付',
            'notify_url' => $extra['notify_url'] ?? route('sn-pay.notify', ['channel' => 'alipay', 'method' => $payload->method]),
            '_config' => $extra['_config'] ?? 'default',
        ];

        if ($endpoint === 'wap' || $endpoint === 'web') {
            // 网站支付支持支付完成后浏览器跳转（业务方传入）
            if ($returnUrl = $extra['return_url'] ?? null) {
                $order['return_url'] = $returnUrl;
            }
        }

        return YansongdaPay::alipay()->{$endpoint}($order);
    }

    public function verifyNotify(Request $request): NotifyPayload
    {
        $this->bootYansongda();

        $origin = YansongdaPay::alipay()->callback();

        $tradeStatus = $origin['trade_status'] ?? '';

        return new NotifyPayload(
            paySn: (string) ($origin['out_trade_no'] ?? ''),
            transactionId: $origin['trade_no'] ?? null,
            amount: sn_money()->minor($origin['total_amount'] ?? '0'),
            currency: 'CNY',
            success: in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true),
            successTime: $origin['gmt_payment'] ?? null,
            buyerInfo: $origin['buyer_id'] ?? null,
            origin: $origin,
        );
    }

    public function verifyRefundNotify(Request $request): NotifyPayload
    {
        // 支付宝退款为同步接口，无独立退款回调；统一回调入口收到退款通知时按支付通知验签处理
        return $this->verifyNotify($request);
    }

    public function buildNotifyResponse(bool $success): Response
    {
        return response($success ? 'success' : 'fail');
    }

    public function refund(RefundPayload $payload): array
    {
        $this->bootYansongda();

        $result = YansongdaPay::alipay()->refund([
            'out_trade_no' => $payload->payRecord->pay_sn,
            'out_request_no' => $payload->refund->refund_sn,
            'refund_amount' => sn_money()->decimal($payload->refund->refund_fee),
            '_config' => $payload->extra['_config'] ?? 'default',
        ]);

        // 支付宝退款同步返回：fund_change=Y 表示资金变化成功
        $fundChange = $result['fund_change'] ?? 'N';

        if ($fundChange !== 'Y' && ($result['code'] ?? '') !== '10000') {
            throw new PayException('Alipay refund failed: ' . ($result['sub_msg'] ?? json_encode($result, JSON_UNESCAPED_UNICODE)));
        }

        return [
            'status' => RefundStatus::Completed,
            'sdk_result' => $result,
        ];
    }
}
