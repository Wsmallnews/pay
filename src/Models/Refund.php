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

        // Enum（channel/pay_method 保持字符串，原因见 PayRecord）
        'status' => Enums\RefundStatus::class,
    ];

    /**
     * 付款人信息（@sn todo 叫付款人还是退款人，后面再考虑）
     */
    public function payer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 退款主体（订单等）
     */
    public function refundable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 所属支付单
     */
    public function payRecord(): BelongsTo
    {
        return $this->belongsTo(PayRecord::class, 'pay_record_id');
    }

    /**
     * 租户
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportUtils::getTenantModel());
    }
}
