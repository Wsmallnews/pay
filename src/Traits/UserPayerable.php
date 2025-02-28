<?php

namespace Wsmallnews\Pay\Traits;

trait UserPayerable
{
    public function pay()
    {
        $pay = app('sn-pay');
        $pay->payer($this);

        return $pay;
    }

    /**
     * 付款人标识 （生成订单号使用）
     */
    public function payerMask(): string
    {
        return $this->morphId() ? $this->morphId() : (mt_rand(10, 99) . 'N' . mt_rand(100, 999));
    }
}
