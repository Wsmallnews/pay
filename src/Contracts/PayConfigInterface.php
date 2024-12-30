<?php

namespace Wsmallnews\Pay\Contracts;

use Closure;

/**
 * 支付配置 interface
 */
interface PayConfigInterface
{

    /**
     * 仅仅获取支付配置参数
     */
    public function getPayConfig($pay_method): array;


    /**
     * 获取最终设置支付配置的 config
     */
    public function getFinalConfig($pay_method): array;


    /**
     * 获取支付方法名
     */
    public function getPayMethod($pay_method): string;
    
}
