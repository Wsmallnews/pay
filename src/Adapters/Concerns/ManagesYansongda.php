<?php

namespace Wsmallnews\Pay\Adapters\Concerns;

use Wsmallnews\Pay\Features\YansongdaPayConfig;
use Yansongda\Artful\Contract\LoggerInterface;
use Yansongda\Pay\Pay as YansongdaPay;

/**
 * yansongda 驱动公共能力：配置装配（多租户全量装载，单据经 _config 键切换）与日志接管。
 */
trait ManagesYansongda
{
    /**
     * 渠道全量租户配置（sn-pay.channels.{channel}.tenants）
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $tenants = [];

    /**
     * 装配 yansongda 配置（幂等：仅首次装配）
     */
    protected function bootYansongda(): void
    {
        if ($this->tenants) {
            return;
        }

        $configSource = $this->payManager->getConfig();

        $this->tenants = method_exists($configSource, 'getChannelTenants')
            ? $configSource->getChannelTenants($this->getChannel())
            : ['default' => $configSource->getChannelConfig($this->getChannel())];

        YansongdaPay::config(
            YansongdaPayConfig::format($this->getChannel(), $this->tenants)
        );

        // 接管 yansongda 日志到 laravel 通道
        YansongdaPay::set(LoggerInterface::class, function () {
            return app('log')->channel(config('sn-pay.logger'));
        });
    }
}
