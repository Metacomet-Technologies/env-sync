<?php

namespace Metacomet\EnvSync;

use Metacomet\EnvSync\Commands\EnvPullCommand;
use Metacomet\EnvSync\Commands\EnvPushCommand;
use Metacomet\EnvSync\Commands\EnvSyncCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EnvSyncServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('env-sync')
            ->hasConfigFile()
            ->hasCommands([
                EnvPushCommand::class,
                EnvPullCommand::class,
                EnvSyncCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ProviderManager::class, function () {
            return new ProviderManager();
        });
    }
}
