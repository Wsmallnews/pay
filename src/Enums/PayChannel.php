<?php

namespace Wsmallnews\Pay\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Wsmallnews\Support\Enums\Traits\EnumHelper;

/**
 * 支付渠道（内置渠道；聚合平台/境外渠道等经 PayManager::extend() 注册任意字符串渠道）。
 */
enum PayChannel: string implements HasColor, HasIcon, HasLabel
{
    use EnumHelper;

    case Wechat = 'wechat';

    case Alipay = 'alipay';

    case Money = 'money';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Wechat => __('sn-pay::pay.channel.wechat'),
            self::Alipay => __('sn-pay::pay.channel.alipay'),
            self::Money => __('sn-pay::pay.channel.money'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Wechat => 'success',
            self::Alipay => 'primary',
            self::Money => 'warning',
        };
    }

    public function getIcon(): string | BackedEnum | null
    {
        return match ($this) {
            self::Wechat => Heroicon::OutlinedChatBubbleLeftRight,
            self::Alipay => Heroicon::OutlinedBuildingLibrary,
            self::Money => Heroicon::OutlinedBanknotes,
        };
    }

    /**
     * 渠道是否为第三方（有预下单与回调）
     */
    public function isThird(): bool
    {
        return in_array($this, [self::Wechat, self::Alipay], true);
    }
}
