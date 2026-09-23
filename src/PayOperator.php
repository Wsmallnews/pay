<?php

namespace Wsmallnews\Pay;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Wsmallnews\Pay\Contracts\AdapterInterface;
use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayerInterface;
use Wsmallnews\Pay\Contracts\ThirdAdapterInterface;
use Wsmallnews\Pay\Data\NotifyPayload;
use Wsmallnews\Pay\Data\PayPayload;
use Wsmallnews\Pay\Data\PayResult;
use Wsmallnews\Pay\Data\RefundPayload;
use Wsmallnews\Pay\Data\RefundResult;
use Wsmallnews\Pay\Enums\PayStatus;
use Wsmallnews\Pay\Enums\RefundStatus;
use Wsmallnews\Pay\Events\PayFailed;
use Wsmallnews\Pay\Events\PaySucceeded;
use Wsmallnews\Pay\Events\RefundFailed;
use Wsmallnews\Pay\Events\RefundSucceeded;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Pay\Models\PayRecord;
use Wsmallnews\Pay\Models\Refund;

/**
 * 渠道支付操作器（channel + method 绑定）。
 *
 * 职责：支付单/退款单的全部 DB 写入、金额校验、回调幂等处理、状态流转与事件分发；
 * 适配器只负责渠道交互（SDK 调用 + 结果翻译）。
 *
 * 金额一律整数分（订单币种），币种由 payable 快照提供。
 */
class PayOperator
{
    public function __construct(
        protected PayManager $payManager,
        protected AdapterInterface $adapter,
        protected ?string $method = null,
    ) {}

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * 当前渠道
     */
    public function getChannel(): string
    {
        return $this->adapter->getChannel();
    }

    // ============================== 支付 ==============================

    /**
     * 发起支付。
     *
     * @param  int|null  $amount  支付金额（整数分），null = 剩余应支付金额（部分支付/定金模式可传更小金额）
     * @param  array<string, mixed>  $extra  业务参数（openid、return_url、notify_url 覆盖、_config 租户键等）
     */
    public function pay(?int $amount = null, array $extra = []): PayResult
    {
        $payable = $this->requirePayable();
        $payer = $this->payManager->getPayer();

        if ($payable->isPaid()) {
            throw new PayException('The payable order is already paid.');
        }

        $remain = $payable->getRemainPayFee();

        if ($remain <= 0) {
            throw new PayException('The payable order has no remaining fee to pay.');
        }

        $amount ??= $remain;

        if ($amount <= 0 || $amount > $remain) {
            throw new PayException("Pay amount [{$amount}] is invalid, remaining is [{$remain}].");
        }

        $payload = new PayPayload(
            paySn: $this->makePaySn($payer),
            amount: $amount,
            currency: $payable->getPayCurrency(),
            channel: $this->getChannel(),
            method: $this->method ?? '',
            payable: $payable,
            payer: $payer,
            walletType: $extra['wallet_type'] ?? null,
            extra: $extra,
        );

        $paidNow = false;

        // 余额类支付：钱包扣款（适配器）与支付单落库同事务
        $payRecord = DB::transaction(function () use ($payload, $payable, $amount, $payer, &$paidNow) {
            $adapted = $this->adapter->pay($payload);

            $payRecord = new PayRecord;
            $payRecord->scope_type = $payable->getScopeType();
            $payRecord->scope_id = $payable->getScopeId();
            $payRecord->pay_sn = $payload->paySn;
            $payRecord->payer_type = $payer ? $payer->getMorphClass() : '';
            $payRecord->payer_id = $payer ? $payer->getKey() : 0;
            $payRecord->payable_type = $payable->morphType();
            $payRecord->payable_id = $payable->morphId();
            $payRecord->payable_options = $payable->morphOptions();
            $payRecord->pay_method = $payload->method;
            $payRecord->channel = $payload->channel;
            $payRecord->currency = $payload->currency;
            // 金额写入必须用 Money 对象（分）；标量会被 cast 按元解析导致错账
            $payRecord->pay_fee = sn_money()->fromMinor($amount, $payload->currency);
            $payRecord->real_fee = sn_money()->fromMinor((int) ($adapted['real_fee'] ?? $amount), $payload->currency);
            $payRecord->refunded_fee = 0;
            $payRecord->status = $adapted['status'];
            $payRecord->paid_at = $adapted['status'] === PayStatus::Paid ? Carbon::now() : null;
            $payRecord->options = $adapted['options'] ?? [];
            $payRecord->save();

            if ($adapted['status'] === PayStatus::Paid) {
                // 余额类支付直接完成
                $payable->checkAndPaid();
                $paidNow = true;
            }

            return $payRecord;
        });

        if ($paidNow) {
            event(new PaySucceeded($payRecord, $payable));

            return new PayResult($payRecord);
        }

        // 第三方渠道：发起预下单，SDK 结果由调用方按 method 消费
        if ($this->adapter instanceof ThirdAdapterInterface) {
            $sdkResult = $this->adapter->prepay($payRecord, $payload, $extra);

            return new PayResult($payRecord, $sdkResult);
        }

        throw new PayException('The channel adapter returned an unpaid record without third-party prepay support.');
    }

    // ============================== 回调 ==============================

    /**
     * 处理第三方支付回调（验签 → 幂等处理 → 应答）
     */
    public function notify(Request $request): Response
    {
        if (! $this->adapter instanceof ThirdAdapterInterface) {
            throw new PayException('The channel adapter does not support notify.');
        }

        try {
            $payload = $this->adapter->verifyNotify($request);
        } catch (\Throwable $e) {
            Log::channel(config('sn-pay.logger'))->error('pay notify verify failed: ' . $e->getMessage());

            return $this->adapter->buildNotifyResponse(false);
        }

        return $this->handleNotify($payload);
    }

    /**
     * 处理标准化回调载荷（幂等：已支付单直接应答成功）
     */
    public function handleNotify(NotifyPayload $payload): Response
    {
        $adapter = $this->requireThirdAdapter();

        $payRecord = PayRecord::where('pay_sn', $payload->paySn)->first();

        if (! $payRecord) {
            Log::channel(config('sn-pay.logger'))->warning("pay notify received unknown pay_sn [{$payload->paySn}]");

            return $adapter->buildNotifyResponse(false);
        }

        // 幂等：已支付（或已退款）的单据直接应答成功，防渠道重发重复处理
        if ($payRecord->status !== PayStatus::Unpaid) {
            return $adapter->buildNotifyResponse(true);
        }

        if (! $payload->success) {
            // 渠道侧交易失败/关闭：应答成功停止重发，广播失败事件
            event(new PayFailed($payRecord, 'notify reports transaction not success'));

            return $adapter->buildNotifyResponse(true);
        }

        // 金额/币种防篡改校验
        if ($payload->amount != sn_money()->minor($payRecord->pay_fee)) {
            Log::channel(config('sn-pay.logger'))->critical(sprintf(
                'pay notify amount mismatch: pay_sn [%s], expected [%s], received [%s]',
                $payload->paySn,
                $payRecord->pay_fee,
                $payload->amount
            ));

            return $adapter->buildNotifyResponse(false);
        }

        DB::transaction(function () use ($payRecord, $payload) {
            $payRecord = PayRecord::query()->lockForUpdate()->findOrFail($payRecord->id);

            if ($payRecord->status !== PayStatus::Unpaid) {
                return;     // 并发双保险
            }

            $payRecord->status = PayStatus::Paid;
            $payRecord->real_fee = sn_money()->fromMinor($payload->amount, $payRecord->currency ?? 'CNY');
            $payRecord->transaction_id = $payload->transactionId;
            $payRecord->buyer_info = ['buyer' => $payload->buyerInfo];
            $payRecord->payment_json = ['origin' => $payload->origin];
            $payRecord->paid_at = Carbon::now();
            $payRecord->save();

            $payable = $this->resolvePayable($payRecord);
            $payable->checkAndPaid();

            event(new PaySucceeded($payRecord, $payable));
        });

        return $adapter->buildNotifyResponse(true);
    }

    // ============================== 退款 ==============================

    /**
     * 退款（原路退回）。
     *
     * @param  int|null  $refundFee  退款金额（整数分），null = 该支付单剩余可退全额
     * @param  array<string, mixed>  $params  remark / refund_type / extra 等附加参数
     */
    public function refund(PayRecord $payRecord, ?int $refundFee = null, array $params = []): RefundResult
    {
        // 重查防止调用方传入陈旧模型（已退金额/状态可能过期）
        $payRecord = PayRecord::query()->findOrFail($payRecord->id);

        $recordChannel = (string) $payRecord->channel;

        if ($recordChannel !== $this->getChannel()) {
            throw new PayException("Pay record channel [{$recordChannel}] does not match operator channel [{$this->getChannel()}].");
        }

        if ($payRecord->status === PayStatus::Unpaid) {
            throw new PayException('Cannot refund an unpaid pay record.');
        }

        $remainRefundFee = max(0, sn_money()->minor($payRecord->pay_fee) - sn_money()->minor($payRecord->refunded_fee));

        if ($remainRefundFee <= 0) {
            throw new PayException('The pay record is fully refunded.');
        }

        $refundFee ??= $remainRefundFee;

        if ($refundFee <= 0 || $refundFee > $remainRefundFee) {
            throw new PayException("Refund fee [{$refundFee}] is invalid, remaining refundable is [{$remainRefundFee}].");
        }

        $payer = $this->payManager->getPayer() ?? $payRecord->payer;

        // 退款单先落库（refund_sn 即渠道侧 out_refund_no）
        $refund = new Refund;
        $refund->scope_type = $payRecord->scope_type;
        $refund->scope_id = $payRecord->scope_id;
        $refund->pay_record_id = $payRecord->id;
        $refund->refund_sn = $this->makeRefundSn($payer);
        $refund->payer_type = $payRecord->payer_type;
        $refund->payer_id = $payRecord->payer_id;
        $refund->refundable_type = $payRecord->payable_type;
        $refund->refundable_id = $payRecord->payable_id;
        $refund->refundable_options = $payRecord->payable_options;
        $refund->channel = $payRecord->channel;
        $refund->pay_method = $payRecord->pay_method;
        $refund->currency = $payRecord->currency;
        $refund->refund_fee = sn_money()->fromMinor($refundFee, $payRecord->currency ?? 'CNY');
        $refund->refund_type = $params['refund_type'] ?? 'back';
        $refund->refund_method = ($params['refund_type'] ?? 'back') === 'back' ? $payRecord->pay_method : 'balance';
        $refund->status = RefundStatus::Ing;
        $refund->remark = $params['remark'] ?? '';
        $refund->save();

        $payload = new RefundPayload(
            payRecord: $payRecord,
            refund: $refund,
            refundFee: $refundFee,
            payer: $payer,
            refundType: $refund->refund_type,
            remark: $refund->remark,
            extra: $params['extra'] ?? [],
        );

        try {
            $adapted = $this->adapter->refund($payload);
        } catch (\Throwable $e) {
            $refund->status = RefundStatus::Fail;
            $refund->remark = $refund->remark . ' | ' . $e->getMessage();
            $refund->save();

            event(new RefundFailed($refund, $e->getMessage()));

            throw $e;
        }

        $result = new RefundResult($refund, $adapted['status'], $adapted['sdk_result'] ?? null, $adapted['options'] ?? []);

        if ($result->isCompleted()) {
            $refund->status = RefundStatus::Completed;
            $refund->real_refund_fee = sn_money()->fromMinor($refundFee, $payRecord->currency ?? 'CNY');
            $refund->save();

            event(new RefundSucceeded($refund, $payRecord));
        }

        // 累计已退金额；全额退完标记支付单已退款
        $this->addRefundedFee($payRecord, $refundFee);

        return $result;
    }

    /**
     * 处理第三方退款回调
     */
    public function refundNotify(Request $request): Response
    {
        if (! $this->adapter instanceof ThirdAdapterInterface) {
            throw new PayException('The channel adapter does not support refund notify.');
        }

        $adapter = $this->adapter;

        try {
            $payload = $adapter->verifyRefundNotify($request);
        } catch (\Throwable $e) {
            Log::channel(config('sn-pay.logger'))->error('refund notify verify failed: ' . $e->getMessage());

            return $adapter->buildNotifyResponse(false);
        }

        return $this->handleRefundNotify($payload);
    }

    /**
     * 处理标准化退款回调载荷（幂等）
     */
    public function handleRefundNotify(NotifyPayload $payload): Response
    {
        $adapter = $this->requireThirdAdapter();

        $refund = Refund::where('refund_sn', $payload->refundSn)->first();

        if (! $refund) {
            Log::channel(config('sn-pay.logger'))->warning("refund notify received unknown refund_sn [{$payload->refundSn}]");

            return $adapter->buildNotifyResponse(false);
        }

        if ($refund->status === RefundStatus::Completed) {
            return $adapter->buildNotifyResponse(true);     // 幂等应答
        }

        if (! $payload->success) {
            $refund->status = RefundStatus::Fail;
            $refund->save();

            event(new RefundFailed($refund, 'notify reports refund not success'));

            return $adapter->buildNotifyResponse(true);
        }

        DB::transaction(function () use ($refund, $payload) {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status === RefundStatus::Completed) {
                return;
            }

            $refund->status = RefundStatus::Completed;
            $refund->real_refund_fee = $refund->refund_fee;
            $refund->transaction_id = $payload->transactionId;
            $refund->payment_json = ['origin' => $payload->origin];
            $refund->save();

            event(new RefundSucceeded($refund, $refund->payRecord));
        });

        return $adapter->buildNotifyResponse(true);
    }

    // ============================== 内部 ==============================

    protected function requirePayable(): PayableInterface
    {
        $payable = $this->payManager->getPayable();

        if (! $payable) {
            throw new PayException('Payable is required, call payable() before paying.');
        }

        return $payable;
    }

    protected function requireThirdAdapter(): ThirdAdapterInterface
    {
        if (! $this->adapter instanceof ThirdAdapterInterface) {
            throw new PayException('The channel adapter does not support third-party notify.');
        }

        return $this->adapter;
    }

    /**
     * 通过支付单反查 payable 实例（加锁）
     */
    protected function resolvePayable(PayRecord $payRecord): PayableInterface
    {
        $payableClass = Relation::getMorphedModel($payRecord->payable_type) ?: $payRecord->payable_type;

        return $payableClass::query()->lockForUpdate()->findOrFail($payRecord->payable_id);
    }

    /**
     * 累计支付单已退金额；全额退完时标记已退款
     */
    protected function addRefundedFee(PayRecord $payRecord, int $refundFee): void
    {
        $payRecord = PayRecord::query()->lockForUpdate()->findOrFail($payRecord->id);

        $refundedFee = sn_money()->minor($payRecord->refunded_fee) + $refundFee;
        $payRecord->refunded_fee = sn_money()->fromMinor($refundedFee, $payRecord->currency ?? 'CNY');

        if ($refundedFee >= sn_money()->minor($payRecord->pay_fee)) {
            $payRecord->status = PayStatus::Refunded;
        }

        $payRecord->save();
    }

    protected function makePaySn(?PayerInterface $payer): string
    {
        return get_sn($payer ? $payer->payerMask() : '0', 'P');
    }

    protected function makeRefundSn(?PayerInterface $payer): string
    {
        return get_sn($payer ? $payer->payerMask() : '0', 'R');
    }
}
