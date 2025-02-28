<?php

namespace Wsmallnews\Pay\Adapters;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Wsmallnews\Pay\Contracts\AdapterInterface;
use Wsmallnews\Pay\Contracts\ThirdInterface;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Pay\Features\YansongdaPayConfig;
use Wsmallnews\Pay\PayManager;
use Yansongda\Artful\Contract\LoggerInterface;
use Yansongda\Pay\Pay as YansongdaPay;

class WechatAdapter implements AdapterInterface, ThirdInterface
{
    /**
     * payManager
     *
     * @var PayManager
     */
    protected $payManager = null;

    /**
     * @var YansongdaPayConfig
     */
    protected $payConfig = null;

    public function __construct(PayManager $payManager)
    {
        $this->payManager = $payManager;

        $config = $payManager->getConfig('wechat');

        $this->payConfig = new YansongdaPayConfig($this->getType(), $config);

        // 配置支付参数
        YansongdaPay::config(array_merge($this->payConfig->getFinalConfig(), ['_force' => true]));      // 强制使用配置

        YansongdaPay::set(LoggerInterface::class, function () {
            return app()->make('log')->channel(config('sn-pay.logger'));
        });
    }

    /**
     * 获取当前驱动名
     */
    public function getType(): string
    {
        return 'wechat';
    }

    /**
     * 付款
     *
     * @param  string  $amount
     */
    public function pay($amount = null): array
    {
        return [
            'pay_fee' => $amount,
            'real_fee' => $amount,
            'pay_status' => Enums\PayStatus::Unpaid,
        ];
    }

    /**
     * 预支付
     *
     * @param  Model  $payRecord
     * @param  array  $params
     * @return mixed
     */
    public function prepay($payRecord, $params)
    {
        // $params = ['_config', 'endpoint', 'openid','description', 'notify_url'];      // params 字段

        // 获取干净的支付参数
        $payConfig = $this->payConfig->getPayConfig($params['_config'] ?? 'default');

        $endpoint = $params['endpoint'] ?? 'mini';        // 默认小程序支付

        $orderData = [
            'out_trade_no' => $payRecord->pay_sn,       // 商户订单号
            'amount' => [
                'total' => intval(bcmul($payRecord->pay_fee, '100')),      // 剩余支付金额
            ],
            'payer' => [
                'openid' => $params['openid'] ?? '',
            ],
            'description' => $params['description'] ?? '商城订单支付',
            '_config' => $params['_config'] ?? 'default',            // 多租户，切换配置
        ];

        if (isset($params['notify_url']) && $params['notify_url']) {
            // 优先使用传入的 notify_url
            $orderData['notify_url'] = $params['notify_url'];
        }

        if (isset($payConfig['mode']) && $payConfig['mode'] === YansongdaPay::MODE_SERVICE) {        // 服务商模式
            if (in_array($endpoint, ['mp', 'mini'])) {     // 公众号，或者小程序支付
                $orderData['payer']['sub_openid'] = $orderData['payer']['openid'] ?? '';
                unset($orderData['payer']['openid']);
            }
        }

        if ($endpoint == 'wap') {              // 手机网站支付
            $orderData['_type'] = 'app';        // 使用 配置中的 app_id 字段
            $orderData['scene_info'] = [
                'payer_client_ip' => request()->ip(),
                'h5_info' => [
                    'type' => 'Wap',
                ],
            ];
        }

        return YansongdaPay::wechat()->$endpoint($orderData);
    }

    /**
     * 支付回调
     */
    public function notify(Closure $callback)
    {
        try {
            $originData = YansongdaPay::wechat()->callback(); // 是的，验签就这么简单！
            // {
            //     "id": "a5c68a7c-5474-5151-825d-88b4143f8642",
            //     "create_time": "2022-06-20T16:16:12+08:00",
            //     "resource_type": "encrypt-resource",
            //     "event_type": "TRANSACTION.SUCCESS",
            //     "summary": "支付成功",
            //     "resource": {
            //         "original_type": "transaction",
            //         "algorithm": "AEAD_AES_256_GCM",
            //         "ciphertext": {
            //             "mchid": "1623831039",
            //             "appid": "wx43051b2afa4ed3d0",
            //             "out_trade_no": "P202204155176122100021000",
            //             "transaction_id": "4200001433202206201698588194",
            //             "trade_type": "JSAPI",
            //             "trade_state": "SUCCESS",
            //             "trade_state_desc": "支付成功",
            //             "bank_type": "OTHERS",
            //             "attach": "",
            //             "success_time": "2022-06-20T16:16:12+08:00",
            //             "payer": {
            //                 "openid": "oRj5A44G6lgCVENzVMxZtoMfNeww"
            //             },
            //             "amount": {
            //                 "total": 1,
            //                 "payer_total": 1,
            //                 "currency": "CNY",
            //                 "payer_currency": "CNY"
            //             }
            //         },
            //         "associated_data": "transaction",
            //         "nonce": "qoJzoS9MCNgu"
            //     }
            // }
            Log::info('wechatpay_notify_origin_data：' . json_encode($originData));
            if ($originData['event_type'] == 'TRANSACTION.SUCCESS') {
                // 支付成功回调
                $data = $originData['resource']['ciphertext'] ?? [];
                if (isset($data['trade_state']) && $data['trade_state'] == 'SUCCESS') {
                    // 交易成功
                    $data['pay_fee'] = bcdiv($data['amount']['total'], 100, 2);
                    $data['notify_time'] = date('Y-m-d H:i:s', strtotime((string) $data['success_time']));
                    $data['buyer_info'] = $data['payer']['openid'] ?? ($data['payer']['sub_openid'] ?? '');

                    $result = $callback($data, $originData);
                    if ($result === true) {
                        return YansongdaPay::wechat()->success();
                    }
                }

                return 'fail';
            } else {
                // 微信交易未成功，返回 false，让微信再次通知
                Log::error('wechatpay_notify_error:交易未成功:' . $originData['event_type']);

                return 'fail';
            }
        } catch (\Exception $e) {
            exception_log($e, 'wechatpay_notify');

            return 'fail';
        }
    }

    /**
     * 微信退款
     *
     * @param  \think\Model  $pay
     * @param  \think\Model  $refund
     * @return mixed
     */
    public function refund($payRecord, $refund)
    {
        YansongdaPay::config($this->payConfig->getFinalConfig($this->getType()));

        $orderData = [
            'out_trade_no' => $payRecord->pay_sn,
            'out_refund_no' => $refund->refund_sn,
            'amount' => [
                'refund' => intval(bcmul($refund->refund_fee, '100')),
                'total' => intval(bcmul($payRecord->pay_fee, '100')),
                'currency' => 'CNY',
            ],
            'reason' => $refund->remark,
        ];

        $result = YansongdaPay::wechat()->refund($orderData);

        Log::write('wechat_refund_origin_data:' . json_encode($result, JSON_UNESCAPED_UNICODE));

        // {   返回数据字段
        //     "amount": {
        //         "currency": "CNY",
        //         "discount_refund": 0,
        //         "from": [],
        //         "payer_refund": 1,
        //         "payer_total": 1,
        //         "refund": 1,
        //         "settlement_refund": 1,
        //         "settlement_total": 1,
        //         "total": 1
        //     },
        //     "channel": "ORIGINAL",
        //     "create_time": "2022-06-20T19:06:36+08:00",
        //     "funds_account": "AVAILABLE",
        //     "out_refund_no": "R202207063668479227002100",
        //     "out_trade_no": "P202205155977315528002100",
        //     "promotion_detail": [],
        //     "refund_id": "50301802252022062021833667769",
        //     "status": "PROCESSING",
        //     "transaction_id": "4200001521202206207964248014",
        //     "user_received_account": "\u652f\u4ed8\u7528\u6237\u96f6\u94b1"
        // }

        if (! isset($result['status']) || ! in_array($result['status'], ['SUCCESS', 'PROCESSING'])) {
            // 微信返回的状态会是 PROCESSING
            throw new PayException('退款失败:' . (isset($result['message']) ? $result['message'] : json_encode($result, JSON_UNESCAPED_UNICODE)));
        }

        return [
            'refunded_fee' => $refund->refunded_fee,
            'real_refunded_fee' => $refund->refunded_fee,
            'refund_status' => Enums\RefundStatus::Ing,
        ];
    }

    /**
     * 退款回调
     */
    public function refundNotify(Closure $callback)
    {
        YansongdaPay::config($this->payConfig->getFinalConfig($this->getType()));

        try {
            $originData = YansongdaPay::wechat()->callback(); // 是的，验签就这么简单！
            // {
            //     "id": "4a553265-1f28-53a3-9395-8d902b902462",
            //     "create_time": "2022-06-21T11:25:33+08:00",
            //     "resource_type": "encrypt-resource",
            //     "event_type": "REFUND.SUCCESS",
            //     "summary": "\u9000\u6b3e\u6210\u529f",
            //     "resource": {
            //         "original_type": "refund",
            //         "algorithm": "AEAD_AES_256_GCM",
            //         "ciphertext": {
            //             "mchid": "1623831039",
            //             "out_trade_no": "P202211233042122753002100",
            //             "transaction_id": "4200001417202206214219765470",
            //             "out_refund_no": "R202211252676008994002100",
            //             "refund_id": "50300002272022062121864292533",
            //             "refund_status": "SUCCESS",
            //             "success_time": "2022-06-21T11:25:33+08:00",
            //             "amount": {
            //                 "total": 1,
            //                 "refund": 1,
            //                 "payer_total": 1,
            //                 "payer_refund": 1
            //             },
            //             "user_received_account": "\u652f\u4ed8\u7528\u6237\u96f6\u94b1"
            //         },
            //         "associated_data": "refund",
            //         "nonce": "8xfQknYyLVop"
            //     }
            // }
            Log::info('wechatpay_refund_notify_origin_data:' . json_encode($originData));
            if ($originData['event_type'] == 'REFUND.SUCCESS') {
                // 支付成功回调
                $data = $originData['resource']['ciphertext'] ?? [];
                if (isset($data['refund_status']) && $data['refund_status'] == 'SUCCESS') {
                    // 退款成功
                    $result = $callback($data, $originData);
                    if ($result === true) {
                        return YansongdaPay::wechat()->success();
                    }
                }

                return 'fail';
            } else {
                // 微信交易未成功，返回 false，让微信再次通知
                Log::error('wechatpay_notify_error:退款未成功:' . $originData['event_type']);

                return 'fail';
            }
        } catch (\Exception $e) {
            exception_log($e, 'wechatpay_refund_notify');

            return 'fail';
        }
    }

    /**
     * 退款回调成功
     *
     * @param  \think\Model  $refund
     * @param  array  $params
     * @return \think\Model
     */
    public function refundNotifyOk($refund, $params)
    {
        // 啥也不做

        return $refund;
    }
}
