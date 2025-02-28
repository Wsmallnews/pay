<?php

namespace Wsmallnews\Pay;

use Wsmallnews\Pay\Adapters\MoneyAdapter;
use Wsmallnews\Pay\Adapters\WechatAdapter;
use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayConfigInterface;
use Wsmallnews\Pay\Contracts\PayerInterface;
use Wsmallnews\Pay\Exceptions\PayException;

class PayManager
{
    /**
     * payable 被付款项目，订单等
     */
    protected PayableInterface $payable;

    /**
     * payer   付款人 用户等
     */
    protected PayerInterface $payer;

    /**
     * payConfig
     */
    protected $payConfig;

    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * 驱动列表
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * 注册的自定义 驱动列表
     *
     * @var array
     */
    protected $customCreators = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function payer(PayerInterface $payer)
    {
        $this->payer = $payer;

        return $this;
    }

    public function getPayer(): ?PayerInterface
    {
        return $this->payer;
    }

    public function payable(PayableInterface $payable)
    {
        $this->payable = $payable;

        return $this;
    }

    public function getPayable(): ?PayableInterface
    {
        return $this->payable;
    }

    /**
     * 设置三方支付配置类
     *
     * @param array payConfig
     * @return self
     */
    public function config(array $payConfig)
    {
        $this->payConfig = $payConfig;

        return $this;
    }

    /**
     * 设置三方支付配置类
     *
     * @return PayConfigInterface
     */
    public function getConfig($adapter_type = null)
    {
        if (! is_null($adapter_type)) {
            ($this->payConfig[$adapter_type] ?? []) || throw new PayException("未找到驱动 [{$adapter_type}] 的配置");

            return $this->payConfig[$adapter_type];
        }

        return $this->payConfig;
    }

    /**
     * 获取一个 driver 实例
     *
     * @param  string|null  $name
     * @return AdapterInterface
     */
    public function driver($name)
    {
        $name || new PayException('未选择驱动');

        return $this->drivers[$name] = $this->get($name);
    }

    /**
     * 尝试从缓存中获取 driver 实例
     *
     * @param  string  $name
     * @return AdapterInterface
     */
    protected function get($name)
    {
        return $this->drivers[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve driver
     *
     * @param  string  $name
     * @param  array|null  $config
     * @return AdapterInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name);
        }

        $driverMethod = 'create' . ucfirst($name) . 'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new PayException("当前驱动 [{$name}] 不支持.");
        }

        return $this->{$driverMethod}();
    }

    /**
     * Call a custom driver creator.
     *
     * @return Sender
     */
    protected function callCustomCreator($name)
    {
        return $this->customCreators[$name]();
    }

    /**
     * 创建一个 wechat 发货实例
     *
     * @param  array  $config
     * @return PayOperator
     */
    public function createWechatDriver()
    {
        $adapter = new WechatAdapter($this);
        // $adapter = new WechatAdapter($config);

        return new PayOperator($this, $adapter);
    }

    public function createMoneyDriver()
    {
        $adapter = new MoneyAdapter($this);

        return new PayOperator($this, $adapter);
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
}
