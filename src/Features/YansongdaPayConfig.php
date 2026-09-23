<?php

namespace Wsmallnews\Pay\Features;

use Illuminate\Support\Str;
use Yansongda\Pay\Pay as YansongdaPay;

/**
 * sn-pay 渠道配置 → yansongda/pay v3 配置格式化器。
 *
 * 输入：channels.{channel}.tenants 的租户配置映射（键为 yansongda _config 租户键）；
 * 输出：yansongda Pay::config() 可直接消费的完整配置（含 logger/http/_force）。
 *
 * 证书约定：以 .pem/.crt 结尾的相对路径自动解析到 storage/app/private/ 下，也可配置绝对路径或证书内容。
 */
class YansongdaPayConfig
{
    /**
     * @param  string  $channel  wechat / alipay
     * @param  array<string, array<string, mixed>>  $tenants  租户配置映射
     * @return array<string, mixed>
     */
    public static function format(string $channel, array $tenants): array
    {
        $formatted = [];

        foreach ($tenants as $tenant => $tenantConfig) {
            $formatted[$tenant] = static::formatTenant($channel, $tenantConfig);
        }

        return array_merge(
            [$channel => $formatted],
            static::baseConfig()
        );
    }

    /**
     * @param  array<string, mixed>  $tenantConfig
     * @return array<string, mixed>
     */
    protected static function formatTenant(string $channel, array $tenantConfig): array
    {
        $tenantConfig['mode'] = (int) ($tenantConfig['mode'] ?? YansongdaPay::MODE_NORMAL);

        foreach (['mch_secret_cert', 'mch_public_cert_path', 'app_secret_cert', 'app_public_cert_path', 'alipay_public_cert_path', 'alipay_root_cert_path'] as $certField) {
            if (! empty($tenantConfig[$certField]) && is_string($tenantConfig[$certField]) && Str::endsWith($tenantConfig[$certField], ['.crt', '.pem'])) {
                // 相对路径统一落到 storage/app/private/
                $tenantConfig[$certField] = storage_path('app/private') . Str::start($tenantConfig[$certField], '/');
            }
        }

        return $tenantConfig;
    }

    /**
     * yansongda 基础配置
     *
     * @return array<string, mixed>
     */
    protected static function baseConfig(): array
    {
        return [
            // 使用 laravel 日志通道接管 yansongda 日志（sn-pay.logger 配置）
            'logger' => [
                'enable' => false,
            ],
            'http' => [
                'timeout' => 5.0,
                'connect_timeout' => 5.0,
            ],
            '_force' => true,       // 强制使用传入配置（多请求内重复 config 场景）
        ];
    }
}
