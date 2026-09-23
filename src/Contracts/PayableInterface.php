<?php

namespace Wsmallnews\Pay\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * 可支付主体接口（订单、充值单等一切可被支付的业务单据）。
 *
 * 金额口径统一为「最小货币单位整数（分）」；币种由 getPayCurrency() 提供（单据快照）。
 */
interface PayableInterface
{
    /**
     * payable 的 scope_type
     */
    public function getScopeType(): string;

    /**
     * payable 的 scope_id
     */
    public function getScopeId(): int;

    /**
     * payable 的 scope 信息
     *
     * @return array{scope_type: string, scope_id: int}
     */
    public function getScopeInfo(): array;

    /**
     * payable 的 morph type（别名）
     */
    public function morphType(): string;

    /**
     * payable 的 morph id
     */
    public function morphId(): int;

    /**
     * payable 的附加选项（随支付单快照保存）
     */
    public function morphOptions(): array;

    /**
     * 交易币种（ISO 4217，创建单据时快照）
     */
    public function getPayCurrency(): string;

    /**
     * 是否已支付（含已退款订单，不含货到付款）
     */
    public function isPaid(): bool;

    /**
     * 剩余应支付金额（整数分）
     */
    public function getRemainPayFee(): int;

    /**
     * 已支付金额（整数分）
     */
    public function getPaidFee(bool $is_lock = false): int;

    /**
     * 检测并流转支付状态（累计支付 >= 应付时置为已支付）
     */
    public function checkAndPaid(): Model;
}
