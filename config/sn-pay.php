<?php

use Wsmallnews\Pay\Models\PayRecord;
use Wsmallnews\Pay\Models\Refund;

// config for Wsmallnews/Pay
return [
    /**
     * Scopeable 实例声明（单一事实源）
     *
     * main 为默认实例（必须存在）；未显式引用实例键的组件均使用 main，
     * 只有需要差异分区的实例才在此声明。
     */
    'scopeables' => [
        'main' => [
            'scope_type' => 'sn-pay',
            'scope_id' => 0,
        ],
    ],

    /**
     * Custom models
     */
    'models' => [
        'pay_record' => PayRecord::class,
        'refund' => Refund::class,
    ],

    /**
     * 支付渠道配置。
     *
     * 每渠道：enabled 开关 + methods 可用支付方式（终端）白名单 + 渠道自有参数；
     * tenants 为多租户配置键（yansongda _config 体系，默认 default）。
     * 证书约定：.pem/.crt 结尾的相对路径自动解析到 storage/app/private/ 下，也可配绝对路径或证书内容。
     *
     * 配置源可整体替换：实现 PayConfigInterface 后容器绑定（如数据库/租户维度的配置管理界面）。
     */
    'channels' => [

        'wechat' => [
            'enabled' => env('SN_PAY_WECHAT_ENABLED', false),
            'methods' => ['mp', 'mini', 'h5', 'app', 'scan'],
            'tenants' => [
                'default' => [
                    // 0 普通商户 2 服务商
                    'mode' => (int) env('SN_PAY_WECHAT_MODE', 0),
                    'app_id' => env('SN_PAY_WECHAT_APP_ID', ''),
                    'mch_id' => env('SN_PAY_WECHAT_MCH_ID', ''),
                    'mch_secret_key' => env('SN_PAY_WECHAT_MCH_SECRET_KEY', ''),
                    'mch_secret_cert' => env('SN_PAY_WECHAT_MCH_SECRET_CERT', ''),
                    'mch_public_cert_path' => env('SN_PAY_WECHAT_MCH_PUBLIC_CERT_PATH', ''),
                    // 服务商模式可选：子商户/子应用
                    // 'sub_app_id' => '',
                    // 'sub_mch_id' => '',
                ],
            ],
        ],

        'alipay' => [
            'enabled' => env('SN_PAY_ALIPAY_ENABLED', false),
            'methods' => ['web', 'wap', 'app', 'scan'],
            'tenants' => [
                'default' => [
                    // 0 普通模式 1 服务商模式
                    'mode' => (int) env('SN_PAY_ALIPAY_MODE', 0),
                    'app_id' => env('SN_PAY_ALIPAY_APP_ID', ''),
                    'app_secret_cert' => env('SN_PAY_ALIPAY_APP_SECRET_CERT', ''),
                    'app_public_cert_path' => env('SN_PAY_ALIPAY_APP_PUBLIC_CERT_PATH', ''),
                    'alipay_public_cert_path' => env('SN_PAY_ALIPAY_PUBLIC_CERT_PATH', ''),
                    'alipay_root_cert_path' => env('SN_PAY_ALIPAY_ROOT_CERT_PATH', ''),
                ],
            ],
        ],

        // 钱包余额支付：钱包扩展实现 WalletOperator 并容器绑定后开启
        'money' => [
            'enabled' => false,
            'methods' => ['balance'],
            'wallet_type' => 'balance',
        ],

        // 聚合支付平台（汇付天下、随行付等）与境外渠道（stripe、paddle）：
        // 经 PayManager::extend('huifu', HuifuAdapter::class) 注册自定义适配器接入，
        // 配置结构由适配器实现方自定义（建议沿用本 channels 结构声明 enabled + methods）。
    ],

    /**
     * 统一回调路由前缀（POST {prefix}/notify/{channel}/{method}）。
     * 特殊部署（如 nginx 路径重写）可覆盖。
     */
    'route_prefix' => 'pay',

    /**
     * Panel register
     *
     * global_default 共享默认（非 FQCN 的 string key）会合并到所有条目：
     *   - navigation_group: 所有页面/资源的默认导航组
     *
     * 条目格式：
     *   - 简单 FQCN：ClassName::class（仅合并共享默认）
     *   - 键值对：ClassName::class => ['key' => 'value']（合并共享默认 + 自定义覆盖）
     *   - 配置项键名使用 snake_case（如 navigation_label、navigation_icon）
     */
    'panel_register' => [
        'global_default' => [
            'navigation_group' => 'sn-pay::pay.global_default.navigation_group',
        ],
        'resources' => [],
        'pages' => [],
    ],

    /**
     * 日志通道（支付流水日志）
     */
    'logger' => env('SN_PAY_LOGGER_CHANNEL', env('LOG_CHANNEL', 'stack')),

    /**
     * 文件基础目录，会自动拼接当前年月日 (仅用于 filament 默认上传组件 (Forms\Components\FileUpload))
     */
    'file_directory' => 'sn/pay/',
];
