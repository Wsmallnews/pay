<?php

namespace Wsmallnews\Pay;

use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayConfigInterface;
use Wsmallnews\Pay\Exceptions\PayException;

class RefundManager
{
    // @sn todo 等写到退款时候再补充吧

    /**
     * payable
     */
    protected PayableInterface $payable;

    /**
     * payConfig
     */
    protected PayConfigInterface $payConfig;

    public function setPayable(PayableInterface $payable)
    {
        $this->payable = $payable;

        return $this;
    }

    public function getPayable(PayableInterface $payable)
    {
        $this->payable = $payable;

        return $this;
    }

    /**
     * 设置三方支付配置类
     *
     * @param PayConfigInterface payConfig
     * @return self
     */
    public function setPayConfig(PayConfigInterface $payConfig)
    {
        $this->payConfig = $payConfig;

        return $this;
    }

    /**
     * 设置三方支付配置类
     *
     * @return PayConfigInterface
     */
    public function getPayConfig()
    {
        return $this->payConfig;
    }

    /**
     * 全额退款
     *
     * @param  array  $data
     * @return void
     */
    public function fullRefund($data)
    {
        $pays = $this->payRecord->getAllPaidPays();

        foreach ($pays as $pay) {
            if (in_array($pay->pay_type, ['wechat', 'alipay', 'douyin'])) {     // @sn todo 这里写的不好，后面优化怎么判断是不是第三方支付
                $this->payManager->setThirdPayConfig($this->getThirdPayConfig($pay));
            }
            $this->payManager->refund($pay, $data);
        }
    }

    /**
     * 退款指定金额 （不退积分，包括积分抵扣的积分）
     *
     * @param  string  $remark
     * @return void
     */
    public function refund(string $refund_money, $data = [])
    {
        $pays = $this->payRecord->getCanRefundPays();       // 获取剩余可退款的pays 记录, 只查 isMoney 的记录
        $remain_max_refund_money = $this->payRecord->getRemainRefundMoney($pays);

        if (bccomp($refund_money, $remain_max_refund_money, 2) === 1) {
            // 退款金额超出最大支付金额
            throw new PayException('退款金额超出最大可退款金额');
        }

        $current_refunded_money = '0';      // 本次退款，已退金额累计
        foreach ($pays as $key => $pay) {
            $current_remain_money = bcsub($refund_money, $current_refunded_money, 2);       // 剩余应退款金额
            if ($current_remain_money <= 0) {
                // 退款完成
                break;
            }

            $current_pay_remain_money = bcsub($pay->pay_fee, $pay->refund_fee, 2);  // 当前 pay 记录剩余可退金额
            if ($current_pay_remain_money <= 0) {
                // 当前 pay 支付的金额已经退完了，循环下一个
                continue;
            }

            $current_refund_money = min($current_remain_money, $current_pay_remain_money);  // 取最小值

            if (in_array($pay->pay_type, ['wechat', 'alipay', 'douyin'])) {     // @sn todo 这里写的不好，后面优化怎么判断是不是第三方支付
                $this->payManager->setThirdPayConfig($this->getThirdPayConfig($pay));
            }
            $this->payManager->refund($pay, $data, $current_refund_money);

            $current_refunded_money = bcadd($current_refunded_money, $current_refund_money, 2);
        }

        if (bccomp($refund_money, $current_refunded_money, 2) === 1) {
            // 退款金额超出最大支付金额
            throw new PayException('退款金额超出最大可退款金额');
        }
    }

    // protected $user = null;

    // /**
    //  * 订单适配器
    //  *
    //  * @var OrderAdapterInterface
    //  */
    // protected $orderAdapter = null;

    // protected $payRecord = null;

    // protected $payManager = null;

    // /**
    //  * 三方支付配置类实例
    //  *
    //  * @var ThirdPayConfigInterface
    //  */
    // protected $thirdPayConfig = null;

    // /**
    //  * 实例化
    //  *
    //  * @param mixed $user
    //  */
    // public function __construct(OrderAdapterInterface $orderAdapter = null, $user = null)
    // {
    //     // 优先使用传入的用户
    //     $this->user = is_numeric($user) ? User::get($user) : $user;

    //     $this->orderAdapter = $orderAdapter;

    //     $this->payRecord = new PayRecord($this->orderAdapter);

    //     $this->payManager = new PayManager($this->orderAdapter, $this->user);
    // }

    // /**
    //  * 设置支付配置类
    //  *
    //  * @param ThirdPayConfigInterface|Closure $payConfigManager
    //  */
    // public function setThirdPayConfig($thirdPayConfig)
    // {
    //     $this->thirdPayConfig = $thirdPayConfig;
    //     return $this;
    // }

    // public function getThirdPayConfig($pay)
    // {
    //     return $this->thirdPayConfig instanceof Closure ? ($this->thirdPayConfig)($pay) : $this->thirdPayConfig;
    // }

    // /**
    //  * 全额退款
    //  * @param array $data
    //  *
    //  * @return void
    //  */
    // public function fullRefund($data)
    // {
    //     $pays = $this->payRecord->getAllPaidPays();

    //     foreach ($pays as $pay) {
    //         if (in_array($pay->pay_type, ['wechat', 'alipay', 'douyin'])) {     // @sn todo 这里写的不好，后面优化怎么判断是不是第三方支付
    //             $this->payManager->setThirdPayConfig($this->getThirdPayConfig($pay));
    //         }
    //         $this->payManager->refund($pay, $data);
    //     }
    // }

    // /**
    //  * 退款指定金额 （不退积分，包括积分抵扣的积分）
    //  *
    //  * @param string $refund_money
    //  * @param string $remark
    //  * @return void
    //  */
    // public function refund(string $refund_money, $data = [])
    // {
    //     $pays = $this->payRecord->getCanRefundPays();       // 获取剩余可退款的pays 记录, 只查 isMoney 的记录
    //     $remain_max_refund_money = $this->payRecord->getRemainRefundMoney($pays);

    //     if (bccomp($refund_money, $remain_max_refund_money, 2) === 1) {
    //         // 退款金额超出最大支付金额
    //         throw new PayException('退款金额超出最大可退款金额');
    //     }

    //     $current_refunded_money = '0';      // 本次退款，已退金额累计
    //     foreach ($pays as $key => $pay) {
    //         $current_remain_money = bcsub($refund_money, $current_refunded_money, 2);       // 剩余应退款金额
    //         if ($current_remain_money <= 0) {
    //             // 退款完成
    //             break;
    //         }

    //         $current_pay_remain_money = bcsub($pay->pay_fee, $pay->refund_fee, 2);  // 当前 pay 记录剩余可退金额
    //         if ($current_pay_remain_money <= 0) {
    //             // 当前 pay 支付的金额已经退完了，循环下一个
    //             continue;
    //         }

    //         $current_refund_money = min($current_remain_money, $current_pay_remain_money);  // 取最小值

    //         if (in_array($pay->pay_type, ['wechat', 'alipay', 'douyin'])) {     // @sn todo 这里写的不好，后面优化怎么判断是不是第三方支付
    //             $this->payManager->setThirdPayConfig($this->getThirdPayConfig($pay));
    //         }
    //         $this->payManager->refund($pay, $data, $current_refund_money);

    //         $current_refunded_money = bcadd($current_refunded_money, $current_refund_money, 2);
    //     }

    //     if (bccomp($refund_money, $current_refunded_money, 2) === 1) {
    //         // 退款金额超出最大支付金额
    //         throw new PayException('退款金额超出最大可退款金额');
    //     }
    // }
}
