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
