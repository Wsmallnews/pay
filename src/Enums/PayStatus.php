<?php

namespace Wsmallnews\Pay\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Wsmallnews\Support\Enums\Traits\EnumHelper;

enum PayStatus: string implements HasColor, HasLabel
{
    use EnumHelper;

    case Unpaid = 'unpaid';

    case Paid = 'paid';

    case Refunded = 'refunded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Unpaid => __('sn-pay::pay.pay_status.unpaid'),
            self::Paid => __('sn-pay::pay.pay_status.paid'),
            self::Refunded => __('sn-pay::pay.pay_status.refunded'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Unpaid => 'gray',
            self::Paid => 'success',
            self::Refunded => 'danger',
        };
    }
}
