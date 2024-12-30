<?php

namespace Wsmallnews\Pay\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Wsmallnews\Support\Enums\Traits\EnumHelper;

Enum RefundStatus :string implements HasColor, HasLabel
{

    use EnumHelper;

    case Ing = 'ing';

    case Completed = 'completed';

    case Fail = 'fail';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Ing => '退款中',
            self::Completed => '退款完成',
            self::Fail => '退款失败',
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