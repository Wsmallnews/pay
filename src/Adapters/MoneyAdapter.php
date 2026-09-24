<?php

namespace Wsmallnews\Pay\Adapters;

use Wsmallnews\Pay\Contracts\AdapterInterface;
use Wsmallnews\Pay\Contracts\WalletOperator;
use Wsmallnews\Pay\Data\PayPayload;
use Wsmallnews\Pay\Data\RefundPayload;
use Wsmallnews\Pay\Enums\PayStatus;
use Wsmallnews\Pay\Enums\RefundStatus;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Pay\PayManager;

/**
 * 钱包余额支付适配器。
 *
 * 依赖 WalletOperator 契约（钱包扩展实现并绑定容器后，经 sn-pay.channels.money.enabled 开启）。
 * 扣款/回款与支付单落库在同一事务（由 PayOperator::pay 保证），汇率快照固化进 PayRecord.options.wallet。
 */
class MoneyAdapter implements AdapterInterface
{
    public function __construct(protected PayManager $payManager) {}

    public function getChannel(): string
    {
        return 'money';
    }

    protected function walletOperator(): WalletOperator
    {
        if (! app()->bound(WalletOperator::class)) {
            throw new PayException('Wallet operator is not bound, implement and bind Wsmallnews\Pay\Contracts\WalletOperator to enable the money channel.');
        }

        return app(WalletOperator::class);
    }

    protected function walletType(PayPayload | RefundPayload $payload): string
    {
        if ($payload instanceof PayPayload && $payload->walletType) {
            return $payload->walletType;
        }

        // 退款按支付时快照的钱包类型回款（快照缺失回落通道配置）
        if ($payload instanceof RefundPayload) {
            return (string) ($payload->payRecord->options['wallet']['wallet_type'] ?? config('sn-pay.channels.money.wallet_type', 'balance'));
        }

        return (string) config('sn-pay.channels.money.wallet_type', 'balance');
    }

    public function pay(PayPayload $payload): array
    {
        // 钱包契约未绑定时优先报通道不可用（渠道可用性检查）
        $operator = $this->walletOperator();

        $payer = $payload->payer;

        if (! $payer) {
            throw new PayException('Wallet pay requires a payer.');
        }

        $walletType = $this->walletType($payload);

        if (! $operator->sufficient($payer, $walletType, $payload->amount, $payload->currency)) {
            throw new PayException('Insufficient wallet balance.');
        }

        // 扣减钱包（两跳换算：订单币种 → 本位币 → 钱包币种；快照随明细返回）
        $deduction = $operator->deduct($payer, $walletType, $payload->amount, $payload->currency, [
            'pay_sn' => $payload->paySn,
            'channel' => $payload->channel,
            'method' => $payload->method,
            'payable' => ['type' => $payload->payable->morphType(), 'id' => $payload->payable->morphId()],
        ]);

        return [
            'status' => PayStatus::Paid,
            'real_fee' => $payload->amount,
            'options' => ['wallet' => $deduction],
        ];
    }

    public function refund(RefundPayload $payload): array
    {
        $payer = $payload->payer ?? $payload->payRecord->payer;

        if (! $payer) {
            throw new PayException('Wallet refund requires a payer.');
        }

        // 按支付时快照逆向回款，绝不重新换算
        $snapshot = $payload->payRecord->options['wallet'] ?? [];

        $credit = $this->walletOperator()->credit($payer, $this->walletType($payload), $payload->refundFee, $payload->payRecord->currency, $snapshot, [
            'refund_sn' => $payload->refund->refund_sn,
            'pay_sn' => $payload->payRecord->pay_sn,
        ]);

        return [
            'status' => RefundStatus::Completed,
            'sdk_result' => $credit,
        ];
    }
}
