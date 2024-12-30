<?php

namespace Wsmallnews\Pay\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Support\Models\SupportModel;
use Wsmallnews\Support\Casts\MoneyCast;

class PayRecord extends SupportModel
{

    protected $table = 'sn_pay_records';

    protected $guarded = [];

    protected $casts = [
        // json
        'payable_options' => 'array',
        'buyer_info' => 'array',
        'payment_json' => 'array',

        // 金额
        'pay_fee' => MoneyCast::class,
        'real_fee' => MoneyCast::class,
        'refunded_fee' => MoneyCast::class,

        // Enum
        'status' => Enums\PayStatus::class,

        'paid_at' => 'timestamp',
    ];



    public function user()
    {
        return $this->belongsTo(config('sn-pay.user_model'), 'user_id');
    }
}
