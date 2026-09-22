<?php

namespace Wsmallnews\Pay\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Wsmallnews\Support\Enums\Traits\EnumHelper;

enum RefundStatus: string implements HasColor, HasLabel
{
    use EnumHelper;

    case Ing = 'ing';

    case Completed = 'completed';

    case Fail = 'fail';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Ing => __('sn-pay::pay.refund_status.ing'),
            self::Completed => __('sn-pay::pay.refund_status.completed'),
            self::Fail => __('sn-pay::pay.refund_status.fail'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Ing => 'gray',
            self::Completed => 'success',
            self::Fail => 'danger',
        };
    }
}
