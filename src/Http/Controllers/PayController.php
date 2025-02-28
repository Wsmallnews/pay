<?php

namespace Wsmallnews\Pay\Http\Controllers;

use Illuminate\Support\Facades\Log;

class PayController
{
    public function notify()
    {
        Log::write('pay-notify-comein:');

        // 1、支付参数如何获得
        // 2、


        $pay = app('sn-pay');
        



        $payManager = new PayManager();
        $payManager->setThirdPayConfig(new ShopPayConfig($platform, $payment));     // 设置三方支付配置实例

        $result = $payManager->thirdNotify($payment, function ($data, $originData = []) use ($payManager, $payment) {
            Log::write('pay-notify-data:' . json_encode($data));

            $out_trade_no = $data['out_trade_no'];

            // 查询 pay 交易记录
            $payModel = PayModel::where('pay_sn', $out_trade_no)->find();
            if (!$payModel || $payModel->status != PayModel::PAY_STATUS_UNPAID) {
                // 订单不存在，或者订单已支付
                return true;
            }

            Db::transaction(function () use ($payManager, $payModel, $data, $originData, $payment) {
                $notify = [
                    'pay_sn' => $data['out_trade_no'],
                    'transaction_id' => $data['transaction_id'],
                    'notify_time' => $data['notify_time'],
                    'buyer_info' => $data['buyer_info'],
                    'payment_json' => $originData ? json_encode($originData) : json_encode($data),
                    'pay_fee' => $data['pay_fee'],          // 微信和抖音的已经*100处理过了
                    'pay_type' => $payment              // 支付方式
                ];

                // pay 实例
                $order = $this->getOrderInstanceByPay($payModel)->lock(true)->find();
                $payManager->setOrderAdapter(new OrderAdapter($order));
                $payManager->thirdNotifyOk($payModel, $notify);
            });

            return true;
        });

        return $this->payResponse($result, $payment);
    }
}
