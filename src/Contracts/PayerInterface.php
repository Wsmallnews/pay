<?php

namespace Wsmallnews\Pay\Contracts;

use Wsmallnews\Support\Contracts\HasSnIdentifiable;

/**
 * 付款人接口。
 *
 * 复用 support 的身份展示契约（名字/头像/邮箱），pay 侧仅补充付款人标识；
 * morph 标识直接使用 Laravel 原生 getMorphClass()/getKey()，不再单独约定方法。
 * pay() 便捷入口由 UserPayerable trait 提供，不进契约。
 */
interface PayerInterface extends HasSnIdentifiable
{
    /**
     * 付款人标识（兼容匿名用户，用于生成支付/退款单号）
     */
    public function payerMask(): string;
}
