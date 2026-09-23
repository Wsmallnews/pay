<div class="w-full" x-data="snPayMethods({})">
    {{-- 支付方式选择网格（原生 Tailwind grid：支付方式数量少，固定响应式两列足够） --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach ($supportPayMethods as $method)
            @if (blank($only) || in_array($method['value'], $only))
                <button type="button"
                    @class([
                        'w-full flex items-center gap-3 rounded-lg border p-3 text-start transition-colors cursor-pointer',
                        'border-gray-200 dark:border-gray-700 hover:border-primary-400 dark:hover:border-primary-500' => $current !== $method['value'],
                        'border-primary-500 dark:border-primary-400 ring-1 ring-primary-500/30' => $current === $method['value'],
                    ])
                    @if ($type === 'choose' && ($current !== $method['value']))
                        @click="choose('{{ $method['value'] }}')"
                    @endif
                    :aria-pressed="current === '{{ $method['value'] }}' ? 'true' : 'false'"
                >
                    <span @class([
                        'flex items-center justify-center size-11 rounded-md shrink-0',
                        'sn-primary-bg text-white' => $current === $method['value'],
                        'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' => $current !== $method['value'],
                    ])>
                        <x-filament::icon :icon="$method['icon']" class="size-6" aria-hidden="true" />
                    </span>

                    <span class="text-sm font-medium">{{ __($method['label']) }}</span>
                </button>
            @endif
        @endforeach
    </div>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('snPayMethods', () => ({
                choose(value) {
                    // 选中后向上分发支付启动事件（channel:method 复合标识，由收银台监听处理）
                    const [channel, method] = value.split(':');

                    this.$wire.dispatch('pay-start', {
                        channel,
                        method,
                    });
                },
            }));
        });
    </script>
@endonce
