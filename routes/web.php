<?php

use Illuminate\Support\Facades\Route;
use Wsmallnews\Pay\Http\Controllers\PayController;

/*
|--------------------------------------------------------------------------
| 支付统一回调入口
|--------------------------------------------------------------------------
|
| POST /pay/notify/{channel}/{method}
| 第三方服务器间回调统一从本入口进入：验签 → 幂等处理 → 状态流转 → 应答。
| 业务模块监听 PaySucceeded / RefundSucceeded 等事件做后续动作，不需要自建回调路由。
| 回调为机器对机器通信，不走 web 中间件组（无 CSRF 困扰）。
|
*/

Route::post(config('sn-pay.route_prefix', 'pay') . '/notify/{channel}/{method}', [PayController::class, 'notify'])
    ->name('sn-pay.notify');
