<?php

namespace Wsmallnews\Pay\Components;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Wsmallnews\Support\Concerns\HasColumns;

class PayMethods extends Component implements HasActions, HasForms
{
    use HasColumns;
    use InteractsWithActions;
    use InteractsWithForms;

    public ?Model $user;

    public $payMethods = [];

    public $supportPayMethods = [
        [
            'label' => '余额支付',
            'value' => 'money',
            'icon' => '/tempimg/200.jpg',
        ],
        [
            'label' => '支付宝支付',
            'value' => 'alipay',
            'icon' => '/tempimg/200.jpg',
        ],
        [
            'label' => '微信支付',
            'value' => 'wechat',
            'icon' => '/tempimg/200.jpg',
        ],
    ];

    public $current = null;

    public $type = 'choose';        // manager=管理;choose=选择

    public function mount($columns = null)
    {
        $this->columns($columns);
    }

    public function render()
    {
        return view('sn-pay::livewire.pay-methods', []);
    }
}
