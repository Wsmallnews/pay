<?php

namespace Wsmallnews\Pay;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wsmallnews\Pay\Contracts\AdapterInterface;
use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayerInterface;
use Wsmallnews\Pay\Contracts\ThirdInterface;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Pay\Models\PayRecord as PayRecordModel;

class PayOperator
{
    /**
     * payManager
     */
    protected PayManager $payManager;

    /**
     * adapter
     */
    protected AdapterInterface $adapter;

    /**
     * payable
     */
    protected ?PayableInterface $payable;

    /**
     * payer
     */
    protected PayerInterface $payer;

    /**
     * payRecord
     */
    protected PayRecord $payRecord;

    /**
     * 实例化
     */
    public function __construct(PayManager $payManager, AdapterInterface $adapter)
    {
        $this->payManager = $payManager;

        $this->adapter = $adapter;

        $this->payable = $payManager->getPayable();

        $this->payer = $payManager->getPayer();

        $this->payRecord = new PayRecord($this->payer, $this->payable);
    }

    /**
     * 支付
     *
     * @param  string  $amount  支付金额
     * @return object
     */
    public function pay($amount = null)
    {
        if ($this->payable->isPaid()) {
            throw new PayException('订单已支付，无需重复支付');
        }

        // 剩余应付金额
        $remain_pay_fee = $this->payable->getRemainPayFee();

        if (is_null($amount)) {
            // 未传金额，默认剩余支付金额
            $amount = $remain_pay_fee;
        }

        if ($amount > $remain_pay_fee) {
            throw new PayException('支付金额不能大于剩余应支付金额');
        }

        $adapterResult = $this->adapter->pay($amount);

        // 添加支付记录
        $payRecord = $this->payRecord->addPay([
            'pay_method' => $this->adapter->getType(),
            'pay_fee' => $adapterResult['pay_fee'],
            'real_fee' => $adapterResult['real_fee'],
            'transaction_id' => null,
            'payment_json' => [],
            'status' => $adapterResult['pay_status'] ?? Enums\PayStatus::Unpaid,
        ]);

        if ($payRecord->status == Enums\PayStatus::Paid) {
            // 检测 payable 支付状态 （有些支付方式是直接支付成功的）
            $this->payable->checkAndPaid();
        }

        return $payRecord;
    }

    /**
     * 三方支付预付款
     *
     * @param  Model  $payRecord
     * @param  array  $params
     * @return object
     */
    public function thirdPrepay($payRecord, $params = [])
    {
        if (! $this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持预付款');
        }

        return $this->adapter->prepay($payRecord, $params);
    }

    /**
     * 三方渠道支付回调
     *
     * @param  object  $pay
     * @param  array  $notify
     * @return object
     */
    public function thirdNotify(?Closure $callback = null)
    {
        if (! $this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持回调');
        }

        return $this->adapter->notify(function ($data, $originData) use ($callback) {
            Log::write('pay-notify-data:' . json_encode($data));

            $out_trade_no = $data['out_trade_no'];

            // 查询 pay 交易记录
            $payRecordModel = PayRecordModel::where('pay_sn', $out_trade_no)->find();
            if (! $payRecordModel || $payRecordModel->status != Enums\PayStatus::Unpaid) {
                // 订单不存在，或者订单已支付
                return true;
            }

            DB::transaction(function () use ($payRecordModel, $data, $originData, $callback) {
                if ($callback) {
                    // 自定义回调处理 ， 处理成功请返回  true
                    return $callback($data, $originData);
                }

                $params = [
                    'pay_sn' => $data['out_trade_no'],
                    'transaction_id' => $data['transaction_id'],
                    'notify_time' => $data['notify_time'],
                    'buyer_info' => $data['buyer_info'],
                    'payment_json' => $originData ? json_encode($originData) : json_encode($data),
                    'pay_fee' => $data['pay_fee'],          // 微信和抖音的已经*100处理过了
                    'payment_type' => $this->adapter->getType(),              // 支付方式
                ];

                // 通过 payRecord 获取 payable 实例
                $this->payable = $this->getPayableByPayRecord($payRecordModel);
                $this->payManager->payable($this->payable);

                // 完成支付单
                $payRecordModel = $this->payRecord->notifyOk($payRecordModel, $params);

                // 检测支付
                if (! $this->payable->isPaid()) {
                    $this->payable->checkAndPaid();
                }

                return $payRecordModel;
            });

            return true;
        });
    }

    /**
     * 通过 payRecord 获取 payable 实例
     *
     * @param  object  $payRecord
     * @return PayableInterface
     */
    private function getPayableByPayRecord($payRecord)
    {
        // payable 实例
        $payable_type = $payRecord->payable_type;
        $payable_id = $payRecord->payable_id;

        $payableClass = Relation::getMorphedModel($payable_type) ?: $payable_type;

        return $payableClass::lockForUpdate()->findOrFail($payable_id);     // 加锁读 获取 payable 实例
    }

    /**
     * 退款
     */
    public function refund($payRecord, $refund_amount = null, $params = [])
    {
        // @sn todo 这里可以判断下 payRecord 是否已经退完了 （考虑性添加）

        // 如果 refund_money = null 那就是全部退
        $refund_amount = is_null($refund_amount) ? $payRecord->pay_fee : $refund_amount;

        // @sn todo 判断退款金额是否大于剩余可退款金额

        // 添加退款单
        $refund = $this->payRecord->addRefund($payRecord, $refund_amount, array_merge($params, [
        ]));

        // 退款
        $refundResult = $this->adapter->refund($payRecord, $refund);

        // 增加支付单的退款金额
        $this->payRecord->addRefundedFee($payRecord, $refund);

        if ($refundResult['refund_status'] == Enums\RefundStatus::Completed) {
            // 已退款的
            $refund = $this->payRecord->refundCompleted($refund);
        }

        // 检查pay 记录是否退款完成
        $pay = $this->payRecord->checkPayAndRefunded($payRecord);

        return $refund;

        // $refund = $this->payRecord->addRefund($payRecord, array_merge($data, [
        //     'user_mark' => $this->user_mark,
        // ]), $refund_money);

        // // 增加支付单的退款金额
        // $this->payRecord->addRefundedFee($payRecord, $refund);

        // // 退款
        // $refundResult = $this->adapter->refund($payRecord, $refund);

        // if ($refundResult['refund_status'] == Enums\RefundStatus::Completed) {
        //     // 已退款的
        //     $refund = $this->payRecord->refundCompleted($refund);
        // }

        // // // 检查pay 记录是否退款完成
        // $pay = $this->payRecord->checkPayAndRefunded($payRecord);

        // return $refund;
    }

    public function thirdRefundNotify(Closure $callback)
    {
        if (! $this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持退款回调');
        }

        return $this->adapter->refundNotify($callback);
    }

    /**
     * 三方退款回调成功
     */
    public function thirdRefundNotifyOk($refund, $params = [])
    {
        if (! $this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持回调');
        }

        $refund = $this->adapter->refundNotifyOk($refund, $params);

        // 完成退款单
        $refund = $this->payRecord->refundCompleted($refund, $params);

        return $refund;
    }
}
