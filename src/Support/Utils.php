<?php

declare(strict_types=1);

namespace Wsmallnews\Pay\Support;

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
}
