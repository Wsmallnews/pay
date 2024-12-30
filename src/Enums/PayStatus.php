<?php

namespace Wsmallnews\Pay\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Wsmallnews\Support\Enums\Traits\EnumHelper;

Enum PayStatus :string implements HasColor, HasLabel
{

    use EnumHelper;

    case Unpaid = 'unpaid';

    case Paid = 'paid';

    case Refunded = 'refunded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Unpaid => '未支付',
            self::Paid => '已支付',
            self::Refunded => '已退款',
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