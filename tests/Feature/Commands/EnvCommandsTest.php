<?php

use Illuminate\Support\Facades\File;
use Metacomet\EnvSync\ProviderManager;
use Metacomet\EnvSync\Tests\Mocks\MockOnePasswordProvider;

beforeEach(function () {
    // Create a test .env file
    $this->testEnvPath = base_path('.env.test');
    $this->testEnvContent = "APP_NAME=TestApp\nAPP_ENV=testing\nAPP_KEY=base64:test\nDB_CONNECTION=sqlite\n";
    File::put($this->testEnvPath, $this->testEnvContent);

    // Create mock provider
    $this->mockProvider = new MockOnePasswordProvider;

    // Force override the ProviderManager singleton with our mock
    $mockProvider = $this->mockProvider; // Capture the mock provider in a local variable
    
    // First, unbind any existing instance
    $this->app->forgetInstance(ProviderManager::class);
    
    // Then register our custom singleton
    $this->app->singleton(ProviderManager::class, function () use ($mockProvider) {
        $manager = new ProviderManager;
        $manager->register('1password', $mockProvider);
        return $manager;
    });
});

afterEach(function () {
    // Clean up test files
    if (File::exists($this->testEnvPath)) {
        File::delete($this->testEnvPath);
    }

    // Clean up any backup files
    $backupPattern = base_path('.env.test.backup.*');
    foreach (glob($backupPattern) as $file) {
        File::delete($file);
    }

    // Clean up .env files
    if (File::exists(base_path('.env'))) {
        File::delete(base_path('.env'));
    }

    $backupPattern = base_path('.env.backup.*');
    foreach (glob($backupPattern) as $file) {
        File::delete($file);
    }
});

describe('env:push command', function () {
    it('fails when environment file does not exist', function () {
        $this->artisan('env:push', [
            'environment' => 'nonexistent',
            '--provider' => '1password',
        ])
            ->assertFailed();
    });

    it('fails when provider is not available', function () {
        $this->mockProvider->setAvailable(false);

        // Create the file so it doesn't fail on that
        File::put(base_path('.env.test'), 'TEST=1');

        $this->artisan('env:push', [
            'environment' => 'test',
            '--provider' => '1password',
        ])
            ->assertFailed();
    });

    it('fails when not authenticated', function () {
        $this->mockProvider->setAuthenticated(false);

        // Create the file so it doesn't fail on that
        File::put(base_path('.env.test'), 'TEST=1');

        $this->artisan('env:push', [
            'environment' => 'test',
            '--provider' => '1password',
        ])
            ->assertFailed();
    });
});

describe('env:pull command', function () {
    beforeEach(function () {
        // Add a test item to the mock provider with the correct repo name
        $this->mockProvider->addItem(
            'Metacomet Technologies, LLC',
            'Metacomet-Technologies/env-sync/test/.env',
            "APP_NAME=PulledApp\nAPP_ENV=production\n"
        );
    });



    it('fails when item does not exist in provider', function () {
        $this->artisan('env:pull', [
            'environment' => 'nonexistent',
            '--provider' => '1password',
        ])
            ->assertFailed();
    });
});

describe('env:sync command', function () {
    it('shows current status', function () {
        // Add a test item
        $this->mockProvider->addItem(
            'Metacomet Technologies, LLC',
            'Metacomet-Technologies/env-sync/local/.env',
            $this->testEnvContent
        );

        // Create local file
        File::put(base_path('.env'), $this->testEnvContent);

        $this->artisan('env:sync', [
            'environment' => 'local',
            '--provider' => '1password',
        ])
            ->expectsOutputToContain('1Password .env Sync Utility')
            ->expectsQuestion('What would you like to do?', 'exit')
            ->assertSuccessful();
    });

    it('handles push action', function () {
        File::put(base_path('.env'), $this->testEnvContent);

        $this->artisan('env:sync', [
            'environment' => 'local',
            '--provider' => '1password',
        ])
            ->expectsQuestion('What would you like to do?', 'push')
            ->expectsQuestion('Continue with another action?', false)
            ->assertSuccessful();
    });

    it('handles pull action', function () {
        $this->mockProvider->addItem(
            'Metacomet Technologies, LLC',
            'Metacomet-Technologies/env-sync/local/.env',
            "PULLED=true\n"
        );

        $this->artisan('env:sync', [
            'environment' => 'local',
            '--provider' => '1password',
        ])
            ->expectsQuestion('What would you like to do?', 'pull')
            ->expectsQuestion('Continue with another action?', false)
            ->assertSuccessful();
    });

    it('handles list action', function () {
        $this->mockProvider->addItem(
            'Metacomet Technologies, LLC',
            'Metacomet-Technologies/env-sync/local/.env',
            $this->testEnvContent
        );

        $this->mockProvider->addItem(
            'Metacomet Technologies, LLC',
            'Metacomet-Technologies/env-sync/staging/.env',
            $this->testEnvContent
        );

        $this->artisan('env:sync', [
            'environment' => 'local',
            '--provider' => '1password',
        ])
            ->expectsQuestion('What would you like to do?', 'list')
            ->expectsQuestion('Continue with another action?', false)
            ->assertSuccessful();
    });
});
