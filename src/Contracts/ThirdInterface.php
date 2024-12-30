<?php

namespace Wsmallnews\Pay\Contracts;

use Closure;

/**
 * 第三方支付 补充接口
 */
interface ThirdInterface
{
    public function prepay($pay, $params);

    public function notify(Closure $callback);

    public function notifyOk($pay, $params);

    public function refundNotify(Closure $callback);

    public function refundNotifyOk($refund, $params);
}
