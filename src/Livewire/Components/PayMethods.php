<?php

namespace Wsmallnews\Pay\Livewire\Components;

use Illuminate\Database\Eloquent\Model;
use Wsmallnews\Pay\Support\Utils as PayUtils;
use Wsmallnews\Support\Concerns\HasColumns;

class PayMethods extends Base
{
    use HasColumns;

    public ?Model $user;

    /**
     * 支付方式筛选（可选）：['wechat:scan', 'money:balance'] 形式的 channel:method 键列表；
     * 空数组 = 展示配置里全部可用方式
     *
     * @var array<int, string>
     */
    public array $only = [];

    /**
     * 支持的支付方式清单（配置驱动：sn-pay.channels 的 enabled + methods 白名单）
     *
     * @var array<int, array{channel: string, method: string, label: mixed, icon: mixed, method_label: string, value: string}>
     */
    public array $supportPayMethods = [];

    public ?string $current = null;

    public string $type = 'choose';        // manager=管理;choose=选择

    public function mount($columns = null, $only = [])
    {
        $this->columns($columns);
        $this->only = (array) $only;

        $this->supportPayMethods = array_values(array_filter(
            PayUtils::getAvailableMethods(),
            fn (array $method) => blank($this->only) || in_array($method['channel'] . ':' . $method['method'], $this->only)
        ));

        // 视图消费统一 value 键（channel:method 复合标识）
        $this->supportPayMethods = array_map(function (array $method) {
            $method['value'] = $method['channel'] . ':' . $method['method'];

            return $method;
        }, $this->supportPayMethods);
    }

    public function render()
    {
        return view('sn-pay::livewire.components.pay-methods', []);
    }
}
