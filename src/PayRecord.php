<?php

namespace Wsmallnews\Pay;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayerInterface;
use Wsmallnews\Pay\Models\PayRecord as PayRecordModel;
use Wsmallnews\Pay\Models\Refund as RefundModel;

class PayRecord
{
    // protected $pay_type = null;

    /**
     * payOperator
     */
    protected ?PayerInterface $payer;

    /**
     * payable
     */
    protected ?PayableInterface $payable;

    public function __construct(?PayerInterface $payer = null, ?PayableInterface $payable = null)
    {
        $this->payer = $payer;

        $this->payable = $payable;
    }

    /**
     * 添加 pay 记录
     *
     * @param  think\Model  $order
     * @param  array  $params
     * @return think\Model
     */
    public function addPay($params)
    {
        $payModel = new PayRecordModel;

        $payModel->scope_type = $this->payable->getScopeType();
        $payModel->scope_id = $this->payable->getScopeId();
        $payModel->pay_sn = get_sn($this->payer->payerMask(), 'P');
        $payModel->payer_type = $this->payer->morphType();
        $payModel->payer_id = $this->payer->morphId();
        $payModel->payable_type = $this->payable->morphType();
        $payModel->payable_id = $this->payable->morphId();
        $payModel->payable_options = $this->payable->morphOptions();
        $payModel->pay_method = $params['pay_method'];
        $payModel->pay_fee = $params['pay_fee'];
        $payModel->real_fee = $params['real_fee'];
        $payModel->transaction_id = $params['transaction_id'];
        $payModel->payment_json = $params['payment_json'];
        $payModel->paid_at = $params['status'] == Enums\PayStatus::Paid ? Carbon::now() : null;
        $payModel->status = $params['status'];
        $payModel->refunded_fee = 0;
        $payModel->save();

        return $payModel;
    }

    /**
     * 三方支付回调成功
     */
    public function notifyOk($record, $params)
    {
        // 支付回调，填充支付渠道信息
        $record->status = Enums\PayStatus::Paid;
        $record->transaction_id = $params['transaction_id'];
        $record->buyer_info = $params['buyer_info'];
        $record->payment_json = $params['payment_json'];
        $record->paid_at = Carbon::now();
        $record->save();

        return $record;
    }

    /**
     * 增加退款金额
     */
    public function addRefundedFee($payRecord, $refund)
    {
        $payRecord->refunded_fee = DB::raw('refunded_fee + ' . $refund->refund_fee);        // @sn todo 看下文档，然后测试一下， 保存成功之后， refunded_fee 字段变成了啥
        $payRecord->save();
    }

    /**
     * 检查当前 pay 记录是否退款完成
     */
    public function checkPayAndRefunded($payRecord)
    {
        // 重新查询 payRecord
        $payRecord = PayRecordModel::find($payRecord->id);

        if ($payRecord->refunded_fee >= $payRecord->pay_fee) {
            // 退款完成了
            $payRecord->status = Enums\PayStatus::Refunded;
            $payRecord->save();
        }

        return $payRecord;
    }

    /**
     * 添加 pay 退款 记录
     *
     * @param  array  $params
     * @return RefundModel
     */
    public function addRefund(PayRecordModel $payRecord, $refund_amount, $params)
    {
        $refund_type = $params['refund_type'] ?? 'back';
        $status = $params['status'] ?? Enums\RefundStatus::Ing;

        // 判断退款方式
        if ($refund_type == 'back') {
            // 原路退回
            $refund_method = $payRecord->pay_method;
        } else {
            if ($payRecord->pay_method == 'score') {
                // 退积分
                $refund_method = 'score';
            } else {
                // 退回到余额
                $refund_method = 'money';
            }
        }

        $refund = new RefundModel;
        $refund->scope_type = $payRecord->scope_type;
        $refund->scope_id = $payRecord->scope_id;
        $refund->pay_record_id = $payRecord->id;
        $refund->refund_sn = get_sn($this->payer->payerMask(), 'R');
        $refund->payer_type = $this->payer->morphType();
        $refund->payer_id = $this->payer->morphId();
        $refund->refundable_type = $payRecord->payable_type;
        $refund->refundable_id = $payRecord->payable_id;
        $refund->refundable_options = $payRecord->payable_options;
        $refund->pay_method = $payRecord->pay_method;
        $refund->refund_fee = $refund_amount;
        // $refund->real_refund_fee = $refund_amount;               // @sn 这个字段怎么处理
        $refund->refund_type = $refund_type;
        $refund->refund_method = $refund_method;
        $refund->status = $status;
        $refund->remark = $params['remark'] ?? null;
        $refund->save();

        return $refund;
    }

    /**
     * 完成退款单
     *
     * @param  \think\Model  $refund
     * @return \think\Model
     */
    public function refundCompleted($refund, $params = [])
    {
        $refund->status = Enums\RefundStatus::Completed;
        $refund->transaction_id = $params['transaction_id'] ?? null;
        $refund->payment_json = $params['payment_json'] ?? null;
        $refund->save();

        return $refund;
    }

    /**
     * 获取 payable 已支付金额，商城订单 计算 积分抵扣金额
     *
     * @param  \think\Model  $order
     * @param  string  $order_type
     * @return string
     */
    // public function getPaidFee()
    // {
    //     $paid_fee = PayRecordModel::scopeable($this->payable->getScopeType(), $this->payable->getScopeId())
    //         ->payable($this->payable->morphType(), $this->payable->morphId())
    //         ->paid()->lockForUpdate()->sum('real_fee');     // 积分商城支付，real_fee 为 0

    //     return $paid_fee;
    // }

    // public function getAllPaidPays($is_lock = false)
    // {
    //     $paidRecords = PayRecordModel::scopeable($this->payable->getScopeType(), $this->payable->getScopeId())
    //         ->payable($this->payable->morphType(), $this->payable->morphId())
    //         ->paid()->lockForUpdate()->order('id', 'asc')->get();

    //     return $paidRecords;
    // }

    // /**
    //  * 获取剩余可退款的pays 记录（不含积分抵扣）
    //  *
    //  * @param integer $order_id
    //  * @param string $sort  排序：money=优先退回余额支付的钱
    //  * @return \think\Collection
    //  */
    // public function getCanRefundPays($sort = 'money')
    // {
    //     $table_type = $this->orderAdapter->getType();
    //     $table_id = $this->orderAdapter->getOrderId();
    //     $order_type = $this->orderAdapter->getOrderType();

    //     // 商城订单，已支付的 pay 记录, 这里只查 钱的支付记录，不查积分
    //     $pays = PayModel::tableInfo($table_type, $table_id)->orderType($order_type)->paid()->isMoney()->lock(true)->order('id', 'asc')->select();
    //     $pays = collection($pays);

    //     if ($sort == 'money') {
    //         // 对 pays 进行排序，优先退 money 的钱
    //         $pays = $pays->sort(function ($a, $b) {
    //             if ($a['pay_type'] == 'money' && $b['pay_type'] == 'money') {
    //                 return 0;
    //             } else if ($a['pay_type'] == 'money' && $b['pay_type'] != 'money') {
    //                 return -1;
    //             } else if ($a['pay_type'] != 'money' && $b['pay_type'] == 'money') {
    //                 return 1;
    //             } else {
    //                 return 0;
    //             }
    //         });

    //         $pays = $pays->values();
    //     }

    //     return $pays;
    // }

    // /**
    //  * 获取剩余可退款金额，不含积分相关支付
    //  *
    //  * @param mixed $pays
    //  * @return string
    //  */
    // public function getRemainRefundMoney($pays = [])
    // {
    //     // 拿到 所有可退款的支付记录
    //     $pays = ($pays && $pays instanceof Collection) ? $pays : $this->getCanRefundPays();

    //     // 支付金额，除了已经退完款的金额 （这里不退积分）
    //     $payed_money = (string)array_sum($pays->column('pay_fee'));
    //     // 已经退款金额 （这里不退积分）
    //     $refunded_money = (string)array_sum($pays->column('refunded_fee'));
    //     // 当前剩余的最大可退款金额，支付金额 - 已退款金额
    //     $remain_max_refund_money = bcsub($payed_money, $refunded_money, 2);

    //     return $remain_max_refund_money;
    // }

}
