<?php

namespace Wsmallnews\Pay;

use Wsmallnews\Pay\Adapters\MoneyAdapter;
use Wsmallnews\Pay\Adapters\WechatAdapter;
use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayConfigInterface;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Support\AdapterManager;

class PayManager extends AdapterManager
{
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
     * 创建一个 wechat 发货实例
     *
     * @param  array  $config
     * @return PayOperator
     */
    public function createWechatDriver()
    {
        $adapter = new WechatAdapter($this, $this->payConfig);
        // $adapter = new WechatAdapter($config);

        return new PayOperator($adapter, $this->payable);
    }

    public function createMoneyDriver()
    {
        $adapter = new MoneyAdapter($this, $this->payConfig);

        return new PayOperator($adapter, $this->payable);
    }

    public function __call($method, $parameters)
    {
        $driver = $parameters[0] ?? '';
        if (! $driver) {
            throw new PayException('驱动不存在');
        }
        unset($parameters[0]);

        return $this->driver($driver)->$method(...$parameters);
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return '';
    }

    /**
     * Get the filesystem connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["sn-pay.disks.{$name}"] ?: [];
    }
}
