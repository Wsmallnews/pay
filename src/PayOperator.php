<?php

namespace Wsmallnews\Pay;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Wsmallnews\Pay\Contracts\AdapterInterface;
use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\ThirdInterface;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Pay\Exceptions\PayException;

class PayOperator
{

    protected $user = null;

    protected string $user_mark = '';


    /**
     * adapter
     *
     * @var AdapterInterface
     */
    protected AdapterInterface $adapter;

    /**
     * payable
     *
     * @var PayableInterface
     */
    protected PayableInterface $payable;

    /**
     * payRecord
     *
     * @var PayRecord
     */
    protected PayableInterface $payRecord;

    /**
     * 实例化
     *
     * @param mixed $user
     */
    public function __construct(AdapterInterface $adapter, PayableInterface $payable)
    {
        $this->adapter = $adapter;

        $this->payable = $payable;

        $this->payRecord = new PayRecord($this, $payable);
    }


    public function setUser($user = null)
    {
        // 优先使用传入的用户
        $this->user = $user ? (is_numeric($user) ? User::get($user) : $user) : Auth::guard()->user();

        $this->user_mark = $this->user ? $this->user->id : (mt_rand(10, 99) . 'n' . mt_rand(100, 999));
    }



    /**
     * 获取付款用户
     *
     * @return void
     */
    public function getUser(): ?User
    {
        return $this->user;
    }


    /**
     * 获取用户标记
     *
     * @return void
     */
    public function getUserMark()
    {
        return $this->user_mark;
    }



    /**
     * 支付
     *
     * @param string $driver    支付渠道
     * @param string  $money    支付金额
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
            'status' => $adapterResult['pay_status'] ?? Enums\PayStatus::Unpaid
        ]);

        if ($payRecord->status == Enums\PayStatus::Paid) {
            // 检测 payable 支付状态 （有些支付方式是直接支付成功的）
            $this->payable->checkAndPaid();
        }

        return $payRecord;
    }


    /**
     * 三方支付预付款
     */
    public function thirdPrepay($payRecord, $params = [])
    {
        if (!$this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持预付款');
        }

        return $this->adapter->prepay($payRecord, $params);
    }



    /**
     * 三方渠道支付回调
     *
     * @param object $pay
     * @param array $notify
     * @return object
     */
    public function thirdNotify($driver, Closure $callback)
    {
        if (!$this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持回调');
        }

        return $this->adapter->notify($callback);
    }


    /**
     * 三方支付回调成功
     */
    public function thirdNotifyOk($payRecord, $params = [])
    {
        if (!$this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持回调');
        }

        $payRecord = $this->adapter->notifyOk($payRecord, $params);

        // 完成支付单
        $payRecord = $this->payRecord->notifyOk($payRecord, $params);

        // 检测支付
        if (!$this->payable->isPaid()) {
            $this->payable->checkAndPaid();
        }

        return $payRecord;
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
        if (!$this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持退款回调');
        }

        return $this->adapter->refundNotify($callback);
    }



    /**
     * 三方退款回调成功
     */
    public function thirdRefundNotifyOk($refund, $params = [])
    {
        if (!$this->adapter instanceof ThirdInterface) {
            throw new PayException('当前支付类型不支持回调');
        }

        $refund = $this->adapter->refundNotifyOk($refund, $params);

        // 完成退款单
        $refund = $this->payRecord->refundCompleted($refund, $params);
        return $refund;
    }

}
