<?php

namespace Wsmallnews\Pay;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wsmallnews\Pay\Commands\PayCommand;
use Wsmallnews\Pay\Components\PayMethods;
use Wsmallnews\Pay\Models\PayRecord;
use Wsmallnews\Pay\Models\Refund;
use Wsmallnews\Pay\Testing\TestsPay;

class PayServiceProvider extends PackageServiceProvider
{
    public static string $name = 'sn-pay';

    public static string $viewNamespace = 'sn-pay';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('wsmallnews/pay');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../routes'))) {
            $package->hasRoutes($this->getRoutes());
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
            $package->runsMigrations();
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
        // 未配置的支付
        $this->app->singleton('sn-pay', function ($app) {
            return new PayManager($app);
        });
    }

    public function packageBooted(): void
    {
        // 注册模型别名
        Relation::enforceMorphMap([
            'sn_pay_record' => PayRecord::class,
            'sn_pay_refund' => Refund::class,
        ]);

        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/pay/{$file->getFilename()}"),
                ], 'pay-stubs');
            }
        }

        Livewire::component('sn-pay-methods', PayMethods::class);

        // Testing
        Testable::mixin(new TestsPay);
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
        return [
            // AlpineComponent::make('pay', __DIR__ . '/../resources/dist/components/pay.js'),
            // Css::make('pay-styles', __DIR__ . '/../resources/dist/pay.css'),
            // Js::make('pay-scripts', __DIR__ . '/../resources/dist/pay.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            PayCommand::class,
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
        return [
            // 'web'
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            '2025_02_18_174455_create_sn_pay_records_table',
            '2025_02_18_174532_create_sn_pay_refunds_table',
        ];
    }
}
