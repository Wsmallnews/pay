<?php

namespace Wsmallnews\Pay\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 支付统一回调控制器（薄控制器：验签/幂等/状态流转全部在 PayOperator 内完成）。
 */
class PayController
{
    /**
     * 统一回调入口：POST /pay/notify/{channel}/{method}
     */
    public function notify(Request $request, string $channel, string $method): Response
    {
        return app('sn-pay')->channel($channel, $method)->notify($request);
    }
}
