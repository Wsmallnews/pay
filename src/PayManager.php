<?php

namespace Wsmallnews\Pay;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Wsmallnews\Pay\Adapters\AlipayAdapter;
use Wsmallnews\Pay\Adapters\MoneyAdapter;
use Wsmallnews\Pay\Adapters\WechatAdapter;
use Wsmallnews\Pay\Contracts\AdapterInterface;
use Wsmallnews\Pay\Contracts\PayableInterface;
use Wsmallnews\Pay\Contracts\PayConfigInterface;
use Wsmallnews\Pay\Contracts\PayerInterface;
use Wsmallnews\Pay\Exceptions\PayException;
use Wsmallnews\Pay\Support\ConfigPayConfig;

/**
 * 支付管理器（容器单例 sn-pay）。
 *
 * 用法：
 *   app('sn-pay')->payer($member)->payable($order)->channel('wechat', 'h5')->pay(null, ['openid' => ...]);
 *
 * 扩展点：
 *   - PayManager::extend('huifu', HuifuAdapter::class / Closure)：注册自定义渠道（聚合平台、境外渠道等）
 *   - PayManager::config($payConfig)：临时替换配置源；容器绑定 PayConfigInterface 可全局替换（如数据库配置源）
 */
class PayManager
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;

    protected ?PayableInterface $payable = null;

    protected ?PayerInterface $payer = null;

    /**
     * 调用方临时注入的配置源
     */
    protected ?PayConfigInterface $payConfig = null;

    /**
     * 已解析的渠道操作器实例
     *
     * @var array<string, PayOperator>
     */
    protected array $operators = [];

    /**
     * 注册的自定义渠道创建器
     *
     * @var array<string, Closure|string>
     */
    protected array $customCreators = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    // ============================== 上下文 ==============================

    public function payer(PayerInterface $payer): static
    {
        $this->payer = $payer;

        return $this;
    }

    public function getPayer(): ?PayerInterface
    {
        return $this->payer;
    }

    public function payable(PayableInterface $payable): static
    {
        $this->payable = $payable;

        return $this;
    }

    public function getPayable(): ?PayableInterface
    {
        return $this->payable;
    }

    /**
     * 临时替换支付配置源（仅影响当前请求）
     */
    public function config(PayConfigInterface $payConfig): static
    {
        $this->payConfig = $payConfig;

        return $this;
    }

    /**
     * 配置源解析：调用方注入 → 容器绑定 → 默认读 sn-pay.php
     */
    public function getConfig(): PayConfigInterface
    {
        if ($this->payConfig) {
            return $this->payConfig;
        }

        if ($this->app->bound(PayConfigInterface::class)) {
            return $this->app->make(PayConfigInterface::class);
        }

        return $this->app->make(ConfigPayConfig::class);
    }

    // ============================== 渠道 ==============================

    /**
     * 获取渠道操作器（channel + method 定位，如 channel('wechat', 'h5')）
     */
    public function channel(string $channel, ?string $method = null): PayOperator
    {
        if ($channel === '') {
            throw new PayException('Pay channel is required.');
        }

        $key = $channel . ':' . ($method ?? '');

        if (! isset($this->operators[$key])) {
            $this->operators[$key] = new PayOperator($this, $this->resolveAdapter($channel), $method);
        }

        return $this->operators[$key];
    }

    /**
     * 注册自定义渠道（对齐 Laravel cache/queue driver 的 extend 惯例）
     *
     * @param  Closure|string  $adapter  适配器类名或创建闭包 fn (PayManager $manager, ?string $method) => AdapterInterface
     */
    public static function extend(string $channel, Closure | string $adapter): void
    {
        app('sn-pay')->customCreators[$channel] = $adapter;
    }

    /**
     * 注册自定义渠道（实例方法形态，供服务提供者链式调用）
     */
    public function addChannel(string $channel, Closure | string $adapter): static
    {
        $this->customCreators[$channel] = $adapter;

        return $this;
    }

    protected function resolveAdapter(string $channel): AdapterInterface
    {
        if (isset($this->customCreators[$channel])) {
            $creator = $this->customCreators[$channel];

            if ($creator instanceof Closure) {
                return $creator($this, null);
            }

            return $this->app->make($creator, ['payManager' => $this]);
        }

        $driverMethod = 'create' . ucfirst($channel) . 'Adapter';

        if (! method_exists($this, $driverMethod)) {
            throw new PayException("Unsupported pay channel [{$channel}], register it via PayManager::extend() first.");
        }

        return $this->{$driverMethod}();
    }

    // ============================== 内置渠道适配器 ==============================

    protected function createWechatAdapter(): AdapterInterface
    {
        return new WechatAdapter($this);
    }

    protected function createAlipayAdapter(): AdapterInterface
    {
        return new AlipayAdapter($this);
    }

    protected function createMoneyAdapter(): AdapterInterface
    {
        return new MoneyAdapter($this);
    }
}
