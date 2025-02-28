<?php

namespace Wsmallnews\Pay\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Support\Casts\MoneyCast;
use Wsmallnews\Support\Models\SupportModel;

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

    /**
     * 付款的记录
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', Enums\PayStatus::Paid);
    }

    /**
     * 付款的记录
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', Enums\PayStatus::Unpaid);
    }

    /**
     * 付款的记录
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', Enums\PayStatus::Refunded);
    }

    /**
     * 范围查询
     *
     * @param  string  $payable_type
     * @param  int  $payable_id
     */
    public function scopePayable(Builder $query, $payable_type, $payable_id = 0): Builder
    {
        return $query->where('payable_type', $payable_type)->where('payable_id', $payable_id);
    }

    /**
     * 付款人信息
     */
    public function payer(): MorphTo
    {
        return $this->morphTo();
    }
}
