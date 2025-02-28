<?php

namespace Wsmallnews\Pay\Contracts;

/**
 * 支付配置 interface
 */
interface PayConfigInterface
{
    /**
     * 仅仅获取支付配置参数
     */
    public function getPayConfig($tenant = 'default'): array;

    /**
     * 获取最终设置支付配置的 config
     */
    public function getFinalConfig(): array;

    // /**
    //  * 获取对应平台的支付方法名
    //  */
    // public function getPayMethod($platform): string;
}
