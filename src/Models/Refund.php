<?php

namespace Wsmallnews\Pay\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Support\Casts\MoneyCast;
use Wsmallnews\Support\Models\SupportModel;
use Wsmallnews\Support\Support\Utils as SupportUtils;

class Refund extends SupportModel
{
    protected $table = 'sn_pay_refunds';

    protected $guarded = [];

    protected $casts = [
        // json
        'refundable_options' => 'array',
        'payment_json' => 'array',
        'buyer_info' => 'array',

        // 金额（币种读行内 currency 列，与支付单一致）
        'refund_fee' => MoneyCast::class . ':currency',
        'real_refund_fee' => MoneyCast::class . ':currency',

        // Enum
        'status' => Enums\RefundStatus::class,
    ];

    /**
     * 付款人信息 (@sn todo叫付款人还是退款人，后面在考虑)
     */
    public function payer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 退款主体
     */
    public function refundable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 租户
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportUtils::getTenantModel());
    }
}
