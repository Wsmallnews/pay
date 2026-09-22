<?php

namespace Wsmallnews\Pay\Livewire\Components;

use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Wsmallnews\Support\Concerns\HasColumns;

class PayMethods extends Base
{
    use HasColumns;

    public ?Model $user;

    /**
     * 可用的支付方式（调用方筛选传入，如 ['money', 'alipay']）
     */
    public array $payMethods = [];

    /**
     * 支持的支付方式清单（value 对应 PayManager 驱动名）
     */
    public array $supportPayMethods = [
        [
            'label' => 'sn-pay::pay.methods.money',
            'value' => 'money',
            'icon' => Heroicon::OutlinedBanknotes,
        ],
        [
            'label' => 'sn-pay::pay.methods.alipay',
            'value' => 'alipay',
            'icon' => Heroicon::OutlinedBuildingLibrary,
        ],
        [
            'label' => 'sn-pay::pay.methods.wechat',
            'value' => 'wechat',
            'icon' => Heroicon::OutlinedChatBubbleLeftRight,
        ],
    ];

    public ?string $current = null;

    public string $type = 'choose';        // manager=管理;choose=选择

    public function mount($columns = null)
    {
        $this->columns($columns);
    }

    public function render()
    {
        return view('sn-pay::livewire.components.pay-methods', []);
    }
}
