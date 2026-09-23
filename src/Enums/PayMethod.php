<?php

namespace Wsmallnews\Pay\Enums;

use Filament\Support\Contracts\HasLabel;
use Wsmallnews\Support\Enums\Traits\EnumHelper;

/**
 * 支付方式（终端语境）：同渠道的多端支付形态。
 *
 * value 对齐 yansongda/pay v3 的端点命名（wechat: mp/mini/app/h5/scan；alipay: web/wap/app/scan）。
 */
enum PayMethod: string implements HasLabel
{
    use EnumHelper;

    case Mp = 'mp';                 // 微信公众号（JSAPI）

    case Mini = 'mini';             // 微信小程序

    case App = 'app';               // APP 支付

    case H5 = 'h5';                 // 微信 H5

    case Scan = 'scan';             // 扫码支付

    case Web = 'web';               // 支付宝电脑网站支付

    case Wap = 'wap';               // 支付宝手机网站支付

    case Balance = 'balance';       // 钱包余额支付

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Mp => __('sn-pay::pay.method.mp'),
            self::Mini => __('sn-pay::pay.method.mini'),
            self::App => __('sn-pay::pay.method.app'),
            self::H5 => __('sn-pay::pay.method.h5'),
            self::Scan => __('sn-pay::pay.method.scan'),
            self::Web => __('sn-pay::pay.method.web'),
            self::Wap => __('sn-pay::pay.method.wap'),
            self::Balance => __('sn-pay::pay.method.balance'),
        };
    }
}
