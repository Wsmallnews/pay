<?php

namespace Wsmallnews\Pay\Support;

use Wsmallnews\Pay\Contracts\PayConfigInterface;
use Wsmallnews\Pay\Contracts\WalletOperator;
use Wsmallnews\Pay\Exceptions\PayException;

/**
 * 默认支付配置源：读 sn-pay.php 的 channels 结构。
 *
 * 替换方式：实现 PayConfigInterface 后容器绑定（数据库配置源等），
 * 或调用方临时注入 app('sn-pay')->config($customConfig)。
 */
class ConfigPayConfig implements PayConfigInterface
{
    public function supports(string $channel): bool
    {
        $config = config("sn-pay.channels.{$channel}");

        if (! is_array($config) || ($config['enabled'] ?? false) !== true) {
            return false;
        }

        // 余额通道额外要求钱包操作契约已绑定（钱包扩展接入后才可用）
        if ($channel === 'money' && ! app()->bound(WalletOperator::class)) {
            return false;
        }

        return true;
    }

    public function getChannelConfig(string $channel, string $tenant = 'default'): array
    {
        $config = config("sn-pay.channels.{$channel}");

        if (! is_array($config) || ($config['enabled'] ?? false) !== true) {
            throw new PayException("Pay channel [{$channel}] is not enabled, check the sn-pay.channels.{$channel} config.");
        }

        $tenantConfig = $config['tenants'][$tenant] ?? null;

        if (! is_array($tenantConfig)) {
            throw new PayException("Pay channel [{$channel}] has no tenant config [{$tenant}].");
        }

        return $tenantConfig;
    }

    /**
     * 渠道的全部租户配置（yansongda 多租户体系使用）
     *
     * @return array<string, array<string, mixed>>
     */
    public function getChannelTenants(string $channel): array
    {
        $config = config("sn-pay.channels.{$channel}");

        if (! is_array($config) || ($config['enabled'] ?? false) !== true) {
            throw new PayException("Pay channel [{$channel}] is not enabled.");
        }

        return $config['tenants'] ?? [];
    }

    /**
     * 渠道可用配置键（跨租户合并键，如 money 通道的 wallet_type）
     */
    public function getChannelOption(string $channel, string $key, mixed $default = null): mixed
    {
        $config = config("sn-pay.channels.{$channel}");

        if (! is_array($config)) {
            return $default;
        }

        return $config[$key] ?? $default;
    }
}
