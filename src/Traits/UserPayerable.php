<?php

namespace Wsmallnews\Pay\Traits;

use Wsmallnews\Pay\PayManager;

/**
 * 付款人便捷能力：实现 PayerInterface 的模型 use 本 trait 获得 pay() 入口与 payerMask 默认实现。
 */
trait UserPayerable
{
    /**
     * 发起支付（返回已绑定 payer 的 PayManager）
     */
    public function pay(): PayManager
    {
        return app('sn-pay')->payer($this);
    }

    /**
     * 付款人标识（生成支付/退款单号用，匿名兼容）
     */
    public function payerMask(): string
    {
        return $this->getKey() ?: (mt_rand(10, 99) . 'N' . mt_rand(100, 999));
    }
}
