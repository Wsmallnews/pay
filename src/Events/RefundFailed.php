<?php

namespace Wsmallnews\Pay\Events;

use Wsmallnews\Pay\Models\Refund;

/**
 * 退款失败。
 */
class RefundFailed
{
    public function __construct(
        public readonly Refund $refund,
        public readonly string $reason,
    ) {}
}
