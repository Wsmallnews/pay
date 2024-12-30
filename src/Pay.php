<?php

namespace Wsmallnews\Pay;

use Wsmallnews\Pay\Contracts\PayConfigInterface;

class Pay
{

    /**
     * payConfig
     *
     * @var PayConfigInterface
     */
    protected PayConfigInterface $payConfig;


    /**
     * 设置三方支付配置类
     *
     * @param PayConfigInterface payConfig
     * @return self
     */
    public function setConfig(PayConfigInterface $payConfig)
    {
        $this->payConfig = $payConfig;
        return $this;
    }


    /**
     * 设置三方支付配置类
     *
     * @return PayConfigInterface
     */
    public function getConfig()
    {
        return $this->payConfig;
    }


    

}
