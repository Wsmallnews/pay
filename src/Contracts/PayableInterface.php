<?php

namespace Wsmallnews\Pay\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * 可支付拓展接口
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
     * @return array
     */
    public function getScopeInfo(): array;


    /**
     * payable 的 type
     */
    public function morphType(): string;

    /**
     * payable 的 id
     */
    public function morphId(): int;

    /**
     * payable 的 Options
     *
     * @return int
     */
    public function morphOptions(): array;

    /**
     * 是否已支付 （包含退款的订单，不包含货到付款的）
     */
    public function isPaid(): bool;

    /**
     * 获取订单剩余应支付金额
     */
    public function getRemainPayFee(): string;

    
    /**
     * 检测是否支付
     */
    public function checkAndPaid(): Model;

    // /**
    //  * 获取订单
    //  *
    //  * @return Model
    //  */
    // public function getOrder(): Model;

    // /**
    //  * 获取 scopeType
    //  *
    //  * @return Model
    //  */
    // public function getScopeType(): string;

    // /**
    //  * 获取下单店铺
    //  *
    //  * @return int
    //  */
    // public function getStoreId(): int;

    // /**
    //  * 获取下单用户 id
    //  *
    //  * @return int
    //  */
    // public function getUserId(): int;

    // /**
    //  * 获取下单用户
    //  *
    //  * @return Model|null
    //  */
    // public function getUser(): ?Model;

    // /**
    //  * 获取订单id
    //  *
    //  * @return int
    //  */
    // public function getOrderId(): int;

    // /**
    //  * 获取订单id
    //  *
    //  * @return int
    //  */
    // public function getOrderSn(): string;

    // /**
    //  * 获取订单类型
    //  *
    //  * @return string
    //  */
    // public function getOrderType(): string;

    // /**
    //  * 获取订单的收货地址
    //  *
    //  * @return Model|null
    //  */
    // public function getAddress(): ?Model;

    // /**
    //  * 获取订单的发票
    //  *
    //  * @return Model|null
    //  */
    // public function getInvoice(): ?Model;

    // /**
    //  * 获取订单应支付金额
    //  *
    //  * @return string
    //  */
    // public function getPayFee(): string;

    // /**
    //  * 获取订单应支付积分
    //  *
    //  * @return int
    //  */
    // public function getScoreAmount(): int;

    // /**
    //  * 获取订单已经支付金额
    //  *
    //  * @return string
    //  */
    // public function getPaidFee(): string;

    // /**
    //  * 获取订单剩余可退款金额
    //  *
    //  * @return string
    //  */
    // public function getRemainRefundMoney(): string;

    // /**
    //  * 主订单是否可发货
    //  *
    //  * @return boolean
    //  */
    // public function isCanSend(): bool;

    // /**
    //  * 订单项发货
    //  *
    //  * @param array|object $relate
    //  * @param array $data
    //  * @param string $msg
    //  * @return Model
    //  */
    // public function send($relate, $data = [], $msg = ''): Model;

    // /**
    //  * 订单本次发货完成
    //  *
    //  * @param string $delivery_type  发货方式
    //  * @param array $data           发货数据
    //  * @param string $msg
    //  * @return Void
    //  */
    // public function currentSendCompleted($delivery_type, $data, $msg = ''): Void;

    // /**
    //  * 订单项取消发货
    //  *
    //  * @param array|object $relate
    //  * @param array $data
    //  * @param string $msg
    //  * @return Model
    //  */
    // public function sendCancel($relate, $msg = ''): Model;

    // /**
    //  * 订单本次取消发货完成
    //  *
    //  * @param string $delivery_type  发货方式
    //  * @param array $data           发货数据
    //  * @param string $msg
    //  * @return Void
    //  */
    // public function currentSendCancel($delivery_type, $data, $msg = ''): Void;

    // /**
    //  * 关联项退款
    //  */
    // public function refund($relate, $data = [], $msg = '');

    // /**
    //  * 本次的所有 relates 退款完成
    //  *
    //  * @return void
    //  */
    // public function currentRefundCompleted($data, $msg = ''): Void;

}
