<?php

namespace Wsmallnews\Pay\Contracts;

/**
 * 付款人接口
 */
interface PayerInterface
{
    public function pay();

    /**
     * payable 的 type
     */
    public function morphType(): string;

    /**
     * payable 的 id
     */
    public function morphId(): int;

    /**
     * 付款人标识 （兼容匿名用户，生成订单号使用）
     */
    public function payerMask(): string;
}
