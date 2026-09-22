<?php

namespace Wsmallnews\Pay;

use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wsmallnews\Pay\Commands\PayInstallCommand;
use Wsmallnews\Pay\Livewire\Components\PayMethods;
use Wsmallnews\Pay\Support\Utils;
use Wsmallnews\Support\Features\Modules\Module;
use Wsmallnews\Support\Features\Modules\ModuleRegistry;

class PayServiceProvider extends PackageServiceProvider
{
    public static string $name = 'sn-pay';

    public static string $viewNamespace = 'sn-pay';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands());

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../routes'))) {
            $package->hasRoutes($this->getRoutes());
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        ModuleRegistry::register(new Module(
            id: static::$name,
            namespace: 'Wsmallnews\Pay',
            plugin: PayPlugin::class,
        ));

        // 支付管理器（容器单例，app('sn-pay')）
        $this->app->singleton('sn-pay', function ($app) {
            return new PayManager($app);
        });
    }

    public function packageBooted(): void
    {
        // 注册模型别名
        Relation::enforceMorphMap([
            'sn_pay_record' => Utils::getPayRecordModel(),
            'sn_pay_refund' => Utils::getRefundModel(),
        ]);

        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                if (str_starts_with($file->getFilename(), '.')) {
                    continue;
                }

                $this->publishes([
                    $file->getRealPath() => base_path("stubs/pay/{$file->getFilename()}"),
                ], 'pay-stubs');
            }
        }

        // 注册 livewire 命名空间（自动发现 src/Livewire/ 下的组件）
        Livewire::addNamespace(
            namespace: 'sn-pay',
            classNamespace: 'Wsmallnews\\Pay\\Livewire'
        );

        // 兼容旧别名（shop 等调用方的历史引用）
        Livewire::component('sn-pay-methods', PayMethods::class);
    }

    protected function getAssetPackageName(): ?string
    {
        return 'wsmallnews/pay';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            PayInstallCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return ['web'];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_sn_pay_records_table',
            'create_sn_pay_refunds_table',
        ];
    }
}
