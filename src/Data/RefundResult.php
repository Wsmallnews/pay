<?php

namespace Wsmallnews\Pay\Data;

use Wsmallnews\Pay\Enums\RefundStatus;
use Wsmallnews\Pay\Models\Refund;

/**
 * 退款结果。
 */
class RefundResult
{
    public function __construct(
        public readonly Refund $refund,
        public readonly RefundStatus $status,
        public readonly mixed $sdkResult = null,
        public readonly array $options = [],
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === RefundStatus::Completed;
    }
}
