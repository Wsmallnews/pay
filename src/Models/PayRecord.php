<?php

namespace Wsmallnews\Pay\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Wsmallnews\Pay\Enums;
use Wsmallnews\Support\Casts\MoneyCast;
use Wsmallnews\Support\Models\SupportModel;
use Wsmallnews\Support\Support\Utils as SupportUtils;

class PayRecord extends SupportModel
{
    protected $table = 'sn_pay_records';

    protected $guarded = [];

    protected $casts = [
        // json
        'payable_options' => 'array',
        'buyer_info' => 'array',
        'payment_json' => 'array',
        'options' => 'array',

        // 金额（币种读行内 currency 列）
        'pay_fee' => MoneyCast::class . ':currency',
        'real_fee' => MoneyCast::class . ':currency',
        'refunded_fee' => MoneyCast::class . ':currency',

        // Enum（channel/pay_method 保持字符串：渠道经 PayManager::extend 可注册任意自定义渠道，
        // 展示层需要标签时用 PayChannel::tryFrom()/PayMethod::tryFrom() 解析）
        'status' => Enums\PayStatus::class,

        'paid_at' => 'timestamp',
    ];

    /**
     * 付款人信息
     */
    public function payer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 被支付主体（订单等）
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 退款单
     */
    public function refunds()
    {
        return $this->hasMany(Refund::class, 'pay_record_id');
    }

    /**
     * 租户
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportUtils::getTenantModel());
    }

    /**
     * 已支付的支付单
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', Enums\PayStatus::Paid);
    }

    /**
     * 未支付的支付单
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', Enums\PayStatus::Unpaid);
    }

    /**
     * 已退款的支付单
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', Enums\PayStatus::Refunded);
    }

    /**
     * 按被支付主体过滤
     *
     * @param  string  $payable_type
     * @param  int  $payable_id
     */
    public function scopePayable(Builder $query, $payable_type, $payable_id = 0): Builder
    {
        return $query->where('payable_type', $payable_type)->where('payable_id', $payable_id);
    }
}
