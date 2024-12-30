<?php

namespace Wsmallnews\Pay\Adapters;

use Wsmallnews\Pay\Contracts\AdapterInterface;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Pay\Exceptions\ExpressException;
use Wsmallnews\Pay\Models\PayRecord as PayRecordModel;
use Wsmallnews\Pay\PayManager;

class MoneyAdapter implements AdapterInterface
{

    /**
     * @var User
     */
    protected $user = null;
    protected $user_mark = null;

    /**
     * PayManager
     *
     * @var PayManager
     */
    protected $payManager = null;

    public function __construct(PayManager $payManager)
    {
        $this->payManager = $payManager;

        // $this->user = $payManager->getUser();
        // $this->user_mark = $payManager->getUserMark();
    }


    /**
     * 获取当前驱动名
     *
     * @return string
     */
    public function getType(): string
    {
        return 'money';
    }


    public function pay($money = null): array
    {
        // @sn todo 扣除用户余额
        // WalletService::change($this->user, 'money', -$money, 'order_pay', [
        //     'order_id' => $this->payManager->getOrderAdapter()->getOrderId(),
        //     'order_sn' => $this->payManager->getOrderAdapter()->getOrderSn(),
        //     'order_type' => $this->payManager->getOrderAdapter()->getOrderType(),
        // ]);

        return [
            'pay_fee' => $money,
            'real_fee' => $money,
            'pay_status' => Enums\PayStatus::Paid
        ];
    }




    public function refund($payRecord, $refund)
    {
        // @sn todo 退回用户余额
        // WalletService::change($pay->user_id, 'money', $refund->refunded_fee, 'order_refund', [
        //     'refund_id' => $refund->id,
        //     'refund_sn' => $refund->refund_sn,
        //     'pay_id' => $pay->id,
        //     'pay_sn' => $pay->pay_sn,
        //     'table_type' => $pay->table_type,
        //     'table_id' => $pay->table_id,
        //     'order_type' => $pay->order_type,
        // ]);

        return [
            'refunded_fee' => $refund->refunded_fee,
            'real_refunded_fee' => $refund->refunded_fee,
            'refund_status' => Enums\RefundStatus::Completed
        ];




    }
}