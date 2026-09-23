<?php

namespace Wsmallnews\Pay\Contracts;

/**
 * 支付配置源接口。
 *
 * 解析优先级：调用方注入实例（PayManager::config()）→ 容器绑定 → 默认读 sn-pay.php。
 * 将来做多租户/后台配置管理时，实现一个数据库版配置源并容器绑定即可整体切换。
 */
interface PayConfigInterface
{
    /**
     * 渠道是否可用（enabled 且配置完整）
     */
    public function supports(string $channel): bool;

    /**
     * 获取渠道配置（yansongda 等驱动可直接消费的数组格式）
     *
     * @param  string  $tenant  多租户配置键（yansongda 多租户体系，默认 default）
     * @return array<string, mixed>
     */
    public function getChannelConfig(string $channel, string $tenant = 'default'): array;
}
