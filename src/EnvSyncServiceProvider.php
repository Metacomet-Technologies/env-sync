<?php

namespace Metacomet\EnvSync;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Metacomet\EnvSync\Commands\EnvSyncCommand;

class EnvSyncServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('env-sync')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_env_sync_table')
            ->hasCommand(EnvSyncCommand::class);
    }
}
