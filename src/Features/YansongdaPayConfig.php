<?php

namespace Wsmallnews\Pay\Features;

use Illuminate\Support\Str;
use Wsmallnews\Pay\Contracts\PayConfigInterface;
use Wsmallnews\Pay\Exceptions\PayException;
use Yansongda\Pay\Pay;

class YansongdaPayConfig implements PayConfigInterface
{
    protected string $pay_method;

    protected array $config;

    public function __construct($pay_method, $config)
    {
        $this->pay_method = $pay_method;

        $this->config = $this->formatConfig($config);
    }

    public function getPayConfig($tenant = 'default'): array
    {
        ($this->config[$tenant] ?? []) || throw new PayException("缺少租户 [{$tenant}] 的支付配置");

        return $this->config[$tenant];
    }

    public function getFinalConfig(): array
    {
        // 合并参数
        $yansongdaBaseConfig = $this->yansongdaBaseConfig();

        return array_merge($yansongdaBaseConfig, [$this->pay_method => $this->config]);
    }

    // @sn todo 具体使用哪种付款方式，由调用方决定，和当前打开平台无关
    // public function getPayEndpoint($platform): string
    // {
    //     $method = [
    //         'wechat' => [
    //             'WechatOfficialAccount' => 'mp',        //公众号支付 Collection
    //             'H5' => 'h5',                      //手机网站支付 Response
    //             'App' => 'app',                      // APP 支付 JsonResponse
    //             'WechatMiniProgram' => 'mini',       //小程序支付 Collection

    //             'TtMiniProgram' => 'mini',
    //         ]
    //     ];

    //     return $method[$platform];
    // }

    // public function getNotifyUrl()
    // {
    //     return request()->domain() . '/estore/api.pay/notify/payment/' . $this->payment . '/platform/' . $this->platform;
    // }

    // public function getRefundNotifyUrl()
    // {
    //     return request()->domain() . '/estore/api.pay/refundNotify/payment/' . $this->payment . '/platform/' . $this->platform;
    // }

    protected function formatConfig($config)
    {
        foreach ($config as $tenant => $payConfig) {
            $config[$tenant] = $this->{'format' . str::studly($this->pay_method) . 'Config'}($payConfig);
        }

        return $config;
    }

    /**
     * 格式化微信官方渠道配置参数
     */
    protected function formatWechatConfig($payConfig, $type = 'normal')
    {
        $payConfig['mode'] = (int) ($payConfig['mode'] ?? Pay::MODE_NORMAL);       // 格式化为 int
        if ($payConfig['mode'] == Pay::MODE_SERVICE && $type == 'sub_mch') {
            // 服务商模式，但需要子商户直连 ，重新定义 config(商家转账到零钱)
            $payConfig = [
                'mch_id' => $payConfig['sub_mch_id'],
                'mch_secret_key' => $payConfig['sub_mch_secret_key'],
                'mch_secret_cert' => $payConfig['sub_mch_secret_cert'],
                'mch_public_cert_path' => $payConfig['sub_mch_public_cert_path'],
            ];
            $payConfig['mode'] = Pay::MODE_NORMAL;        // 临时改为普通商户
        }

        // 下面考虑按照 yansongda 的文档，所有 appid 都自己在外面赋值，而不是在下面判断平台

        // if ($payConfig['mode'] === Pay::MODE_SERVICE) {
        //     // 首先将平台配置的 app_id 初始化到配置中
        //     $payConfig['mp_app_id'] = $payConfig['app_id'];       // 服务商关联的公众号的 appid
        //     $payConfig['sub_app_id'] = $payConfig['sub_app_id'];        // 服务商特约子商户
        // } else {
        //     $payConfig['app_id'] = $payConfig['app_id'];
        // }

        // switch ($this->platform) {
        //     case 'WechatMiniProgram':
        //         $payConfig['_type'] = 'mini';          // 小程序提现，需要传 _type = mini 才能正确获取到 appid
        //         if ($payConfig['mode'] === Pay::MODE_SERVICE) {
        //             $payConfig['sub_mini_app_id'] = $payConfig['sub_app_id'];
        //             unset($payConfig['sub_app_id']);
        //         } else {
        //             $payConfig['mini_app_id'] = $payConfig['app_id'];
        //             unset($payConfig['app_id']);
        //         }
        //         break;
        //     case 'WechatOfficialAccount':
        //         $payConfig['_type'] = 'mp';          // 小程序提现，需要传 _type = mp 才能正确获取到 appid
        //         if ($payConfig['mode'] === 2) {
        //             $payConfig['sub_mp_app_id'] = $payConfig['sub_app_id'];
        //             unset($payConfig['sub_app_id']);
        //         } else {
        //             $payConfig['mp_app_id'] = $payConfig['app_id'];
        //             unset($payConfig['app_id']);
        //         }
        //         break;
        //     case 'App':
        //     case 'H5':
        //     default:
        //         break;
        // }

        // @sn 支付回调地址设置位置
        // $payConfig['notify_url'] = request()->domain() . '/addons/shopro/pay/notify/payment/wechat/platform/' . $this->platform;

        if (isset($payConfig['mch_secret_cert']) && Str::endsWith($payConfig['mch_secret_cert'], ['.crt', '.pem'])) {
            $payConfig['mch_secret_cert'] = storage_path('app/private') . Str::start($payConfig['mch_secret_cert'], '/');
        }

        if (isset($payConfig['mch_public_cert_path']) && Str::endsWith($payConfig['mch_public_cert_path'], ['.crt', '.pem'])) {
            $payConfig['mch_public_cert_path'] = storage_path('app/private') . Str::start($payConfig['mch_public_cert_path'], '/');
        }

        return $payConfig;
    }

    /**
     * 格式化支付宝官方渠道配置参数
     *
     * @param [type] $params
     * @return void
     */
    protected function formatAlipayConfig($payConfig, $data = [])
    {
        // @sn 支付回调地址设置位置
        // $payConfig['notify_url'] = request()->domain() . '/addons/shopro/pay/notify/payment/alipay/platform/' . $this->platform;

        if (in_array($this->platform, ['H5'])) {
            // app 支付不能带着个参数
            $payConfig['return_url'] = str_replace('&amp;', '&', request()->param('return_url', ''));
        }

        if (isset($payConfig['app_secret_cert']) && Str::endsWith($payConfig['app_secret_cert'], ['.crt', '.pem'])) {
            $payConfig['app_secret_cert'] = storage_path('app/private') . Str::start($payConfig['app_secret_cert'], '/');
        }
        if (isset($payConfig['app_public_cert_path']) && Str::endsWith($payConfig['app_public_cert_path'], ['.crt', '.pem'])) {
            $payConfig['app_public_cert_path'] = storage_path('app/private') . Str::start($payConfig['app_public_cert_path'], '/');
        }
        if (isset($payConfig['alipay_public_cert_path']) && Str::endsWith($payConfig['alipay_public_cert_path'], ['.crt', '.pem'])) {
            $payConfig['alipay_public_cert_path'] = storage_path('app/private') . Str::start($payConfig['alipay_public_cert_path'], '/');
        }
        if (isset($payConfig['alipay_root_cert_path']) && Str::endsWith($payConfig['alipay_root_cert_path'], ['.crt', '.pem'])) {
            $payConfig['alipay_root_cert_path'] = storage_path('app/private') . Str::start($payConfig['alipay_root_cert_path'], '/');
        }

        return $payConfig;
    }

    /**
     * 格式化微信官方渠道配置参数
     */
    protected function formatDouyinConfig($payConfig, $platformPayConfig = [], $type = 'normal')
    {
        $payConfig['mode'] = (int) ($payConfig['mode'] ?? 0);       // 格式化为 int

        $payConfig['mini_app_id'] = $platformPayConfig['app_id'];

        switch ($this->platform) {
            case 'TtMiniProgram':
                break;
            default:
                break;
        }

        // $payConfig['notify_url'] = request()->domain() . '/addons/shopro/pay/notify/payment/douyin/platform/' . $this->platform;

        return $payConfig;
    }

    /**
     * yansongda 基础配置
     */
    protected function yansongdaBaseConfig(): array
    {
        return [
            // 不启用 yansongda logger ，使用 laravel 的 logger
            'logger' => [ // optional
                'enable' => true,
                // 'file' => $log_path . 'pay.log',
                // 'level' => config('app_debug') ? 'debug' : 'info', // 建议生产环境等级调整为 info，开发环境为 debug
                // 'type' => 'daily', // optional, 可选 daily.
                // 'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
            ],
            'http' => [ // optional
                'timeout' => 5.0,
                'connect_timeout' => 5.0,
                // 更多配置项请参考 [Guzzle](https://guzzle-cn.readthedocs.io/zh_CN/latest/request-options.html)
            ],
        ];
    }
}
