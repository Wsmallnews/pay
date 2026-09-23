<?php

namespace Wsmallnews\Pay\Contracts;

/**
 * 钱包操作契约（余额支付通道的扣款/回款出口）。
 *
 * pay 包只依赖本契约；钱包扩展（balance/point/commission 等任意钱包类型）实现并绑定到容器
 * （$this->app->bind(WalletOperator::class, XxxWalletOperator::class)）后，money 通道即可启用。
 *
 * 换算约定（两跳模型）：
 *   订单币种 --市场汇率--> 记账本位币 --钱包锚定汇率--> 钱包币种
 * 实现方内部完成换算，并把汇率快照（锚定率/市场率/换算后金额）随返回值带回，
 * pay 侧固化进 PayRecord.options.wallet，退款时按快照逆向回款，绝不重新换算。
 */
interface WalletOperator
{
    /**
     * 校验钱包余额是否足够（不扣减）
     *
     * @param  int  $minorAmount  订单币种金额（分）
     */
    public function sufficient(PayerInterface $payer, string $walletType, int $minorAmount, string $orderCurrency): bool;

    /**
     * 扣减钱包（事务内调用，实现方自行保证原子性）
     *
     * @param  int  $minorAmount  订单币种金额（分）
     * @param  array<string, mixed>  $meta  业务上下文（pay_sn 等）
     * @return array<string, mixed> 扣减明细（含汇率快照），如：
     *                              ['wallet_amount' => 7200, 'wallet_currency' => 'POINT',
     *                              'rate' => ['anchor' => '100:1', 'market' => '7.2'], 'transaction_id' => '...']
     */
    public function deduct(PayerInterface $payer, string $walletType, int $minorAmount, string $orderCurrency, array $meta = []): array;

    /**
     * 回款（退款）：按支付时的快照逆向换算退回钱包
     *
     * @param  int  $minorAmount  订单币种退款金额（分）
     * @param  array<string, mixed>  $snapshot  支付时固化在 PayRecord.options.wallet 的快照
     * @return array<string, mixed> 回款明细
     */
    public function credit(PayerInterface $payer, string $walletType, int $minorAmount, string $orderCurrency, array $snapshot, array $meta = []): array;
}
