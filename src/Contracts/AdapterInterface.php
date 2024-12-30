<?php

namespace Wsmallnews\Pay\Contracts;

use Wsmallnews\Pay\Models\PayRecord;
use Wsmallnews\Pay\Models\Refund;

/**
 * express sender interface
 */
interface AdapterInterface
{

    /**
     * @return string
     */
    public function getType(): string;


    /**
     * 支付
     *
     * @param float|string $amount
     * @return array
     */
    public function pay($amount): array;



    public function refund(PayRecord $payRecord, Refund $refund);
}
