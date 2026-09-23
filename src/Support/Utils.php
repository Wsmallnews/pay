<?php

declare(strict_types=1);

namespace Wsmallnews\Pay\Support;

use Wsmallnews\Pay\Contracts\PayConfigInterface;
use Wsmallnews\Pay\Enums\PayChannel;
use Wsmallnews\Pay\Enums\PayMethod;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Support\Data\ScopeableContext;
use Wsmallnews\Support\Exceptions\InvalidScopeException;
use Wsmallnews\Support\Support\Utils as SupportUtils;

/**
 * Utility class for Pay package configuration and helpers.
 */
class Utils
{
    /**
     * Get configuration value.
     *
     * @param  string|null  $name  Configuration key (dot notation)
     * @param  mixed  $default  Default value if not found
     */
    public static function getConfig(?string $name = null, mixed $default = null): mixed
    {
        $config = config('sn-pay');

        return $name ? (data_get($config, $name) ?? $default) : $config;
    }

    /**
     * Get scopeable configuration as ScopeableContext object.
     *
     * @param  string|null  $key  实例键（null = main 默认实例）
     *
     * @throws PayException
     */
    public static function getScopeableContext(?string $key = null): ScopeableContext
    {
        try {
            return SupportUtils::getScopeFromInstances('sn-pay.scopeables', $key);
        } catch (InvalidScopeException $e) {
            throw new PayException('Scopeable configuration error. ' . $e->getMessage());
        }
    }

    /**
     * Get scopeable array.
     *
     * @param  string|null  $key  实例键（null = main 默认实例）
     * @return array{scope_type: string, scope_id: int}
     *
     * @throws PayException
     */
    public static function getScopeable(?string $key = null): array
    {
        return self::getScopeableContext($key)->toArray();
    }

    /**
     * Get scope type.
     *
     * @param  string|null  $key  实例键（null = main 默认实例）
     *
     * @throws PayException
     */
    public static function getScopeType(?string $key = null): string
    {
        return self::getScopeableContext($key)->scopeType;
    }

    /**
     * Get scope ID.
     *
     * @param  string|null  $key  实例键（null = main 默认实例）
     *
     * @throws PayException
     */
    public static function getScopeId(?string $key = null): int
    {
        return self::getScopeableContext($key)->scopeId;
    }

    /**
     * Get panel register raw config.
     *
     * @param  string|null  $type  Register type (pages, resources, global_default) or null for all
     */
    public static function getPanelRegister(?string $type = null): mixed
    {
        if (blank($type)) {
            return self::getConfig('panel_register', null);
        }

        return self::getConfig("panel_register.{$type}", null);
    }

    /**
     * Get model class by name.
     *
     * @param  string  $name  Model name (e.g., 'pay_record', 'refund')
     * @param  bool  $shouldException  Whether to throw exception if not found
     *
     * @throws PayException
     */
    public static function getModel(string $name, bool $shouldException = true): ?string
    {
        $model = self::getConfig('models')[$name] ?? null;

        if (blank($model) && $shouldException) {
            throw new PayException("Model {$name} not found.");
        }

        return $model;
    }

    /**
     * Get PayRecord model class.
     */
    public static function getPayRecordModel(): string
    {
        return self::getModel('pay_record');
    }

    /**
     * Get Refund model class.
     */
    public static function getRefundModel(): string
    {
        return self::getModel('refund');
    }

    /**
     * Get file directory path with optional type and date.
     *
     * @param  string|null  $type  Directory type
     */
    public static function getFileDirectory(?string $type = null): string
    {
        return self::getConfig('file_directory', 'sn/pay/') . ($type ? $type . '/' : '') . date('Ymd');
    }

    /**
     * 可用的支付方式清单（enabled 渠道的 methods 白名单，收银台展示用）。
     *
     * @return array<int, array{channel: string, method: string, label: string, icon: mixed, method_label: string}>
     */
    public static function getAvailableMethods(): array
    {
        $configSource = app()->bound(PayConfigInterface::class)
            ? app(PayConfigInterface::class)
            : app(ConfigPayConfig::class);

        $methods = [];

        foreach (self::getConfig('channels', []) as $channel => $channelConfig) {
            if (! is_array($channelConfig) || ($channelConfig['enabled'] ?? false) !== true) {
                continue;
            }

            // 配置源可否决渠道可用性（如 money 通道未绑定 WalletOperator）
            if (! $configSource->supports($channel)) {
                continue;
            }

            $channelEnum = PayChannel::tryFrom($channel);

            foreach ((array) ($channelConfig['methods'] ?? []) as $method) {
                $methodEnum = PayMethod::tryFrom($method);

                $methods[] = [
                    'channel' => $channel,
                    'method' => $method,
                    'label' => $channelEnum?->getLabel() ?? $channel,
                    'icon' => $channelEnum?->getIcon(),
                    'method_label' => $methodEnum?->getLabel() ?? $method,
                ];
            }
        }

        return $methods;
    }
}
