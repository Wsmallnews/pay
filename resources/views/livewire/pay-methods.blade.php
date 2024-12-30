@php

@endphp

<div class="w-full" x-data="payMethodsManager({
})">

    <x-filament::grid
        :default="$this->getColumns('default')"
        :sm="$this->getColumns('sm')"
        :md="$this->getColumns('md')"
        :lg="$this->getColumns('lg')"
        :xl="$this->getColumns('xl')"
        :two-xl="$this->getColumns('2xl')"
        class="gap-4"
    >
        @foreach($supportPayMethods as $key => $method)
            @if (in_array($method['value'], $payMethods))
                <div
                    @class([
                        'w-full flex border rounded-lg p-2.5',
                        "border-gray-200" => $current != $method['value'],
                        'border-primary-500' => $current == $method['value'],
                    ])
                    @if($type == 'choose' && ($current != $method['value']))
                    @click="choose('{{$method['value']}}')"
                    @endif
                >
                    <div class="w-14 h-14 overflow-hidden rounded-md">
                        <img class="w-full h-full object-contain" src="{{$method['icon']}}" alt="">
                    </div>

                    <div class="flex flex-col">
                        {{$method['label']}}
                    </div>
                </div>
            @endif
        @endforeach
    </x-filament::grid>
</div>

@assets
<script>
    function payMethodsManager({
    }) {
        return {
            init () {
            },
            choose(method) {
                this.$wire.dispatch('pay-start', {
                    payMethod: method,
                });
            }
        }
    }
</script>
@endassets