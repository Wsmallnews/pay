<?php

namespace Wsmallnews\Pay\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Support\Casts\MoneyCast;
use Wsmallnews\Support\Models\SupportModel;

class Refund extends SupportModel
{
    protected $table = 'sn_pay_refunds';

    protected $guarded = [];

    protected $casts = [
        // json
        // 'payable_options' => 'array',
        // 'buyer_info' => 'array',
        // 'payment_json' => 'array',

        // // 金额
        // 'pay_fee' => MoneyCast::class,
        // 'real_fee' => MoneyCast::class,
        // 'refunded_fee' => MoneyCast::class,

        // // Enum
        // 'status' => Enums\PayStatus::class,

        // 'paid_at' => 'timestamp',
    ];

    /**
     * 付款人信息 (@sn todo叫付款人还是退款人，后面在考虑)
     */
    public function payer(): MorphTo
    {
        return $this->morphTo();
    }
}
