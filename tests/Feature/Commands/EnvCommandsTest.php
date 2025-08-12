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

    // Replace the real provider with our mock
    $manager = $this->app->make(ProviderManager::class);
    $manager->register('1password', $this->mockProvider);
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
    it('pushes environment file successfully', function () {
        // Pre-create the env file for 'test' environment
        File::put(base_path('.env.test'), $this->testEnvContent);

        $this->artisan('env:push', [
            'environment' => 'test',
            '--provider' => '1password',
        ])
            ->expectsOutput('Pushing test environment to 1Password...')
            ->expectsOutput('✓ Successfully pushed .env to 1Password')
            ->assertSuccessful();

        // Verify the mock provider has the item
        expect($this->mockProvider->exists([
            'environment' => 'test',
            'vault' => 'Metacomet Technologies, LLC',
        ]))->toBeTrue();
    });

    it('fails when environment file does not exist', function () {
        $this->artisan('env:push', [
            'environment' => 'nonexistent',
            '--provider' => '1password',
        ])
            ->assertFailed();
    });

    it('fails when provider is not available', function () {
        $this->mockProvider->setAvailable(false);

        $this->artisan('env:push', [
            'environment' => 'test',
            '--provider' => '1password',
        ])
            ->expectsOutput('1Password CLI not installed')
            ->assertFailed();
    });

    it('fails when not authenticated', function () {
        $this->mockProvider->setAuthenticated(false);

        $this->artisan('env:push', [
            'environment' => 'test',
            '--provider' => '1password',
        ])
            ->expectsOutput('Not authenticated with 1Password')
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

    it('pulls environment file successfully', function () {
        $this->artisan('env:pull', [
            'environment' => 'test',
            '--provider' => '1password',
        ])
            ->expectsOutput('Pulling test environment from 1Password...')
            ->expectsOutput('✓ Successfully pulled .env from 1Password')
            ->assertSuccessful();

        // Verify the file was created
        $pulledFile = base_path('.env.test');
        expect(File::exists($pulledFile))->toBeTrue();
        expect(File::get($pulledFile))->toContain('APP_NAME=PulledApp');
    });

    it('creates backup when pulling over existing file', function () {
        // Create existing file
        File::put(base_path('.env.test'), "EXISTING=content\n");

        $this->artisan('env:pull', [
            'environment' => 'test',
            '--provider' => '1password',
        ])
            ->assertSuccessful();

        // Check that backup was created
        $backupFiles = glob(base_path('.env.test.backup.*'));
        expect($backupFiles)->toHaveCount(1);
        expect(File::get($backupFiles[0]))->toBe("EXISTING=content\n");
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
            '--provider' => '1password',
        ])
            ->expectsOutputToContain('1Password .env Sync Utility')
            ->expectsOutputToContain('Current Status:')
            ->expectsOutputToContain('Local .env:')
            ->expectsOutputToContain('1Password:')
            ->expectsQuestion('Select action (1-6)', '6') // Exit
            ->assertSuccessful();
    });

    it('handles push action', function () {
        File::put(base_path('.env'), $this->testEnvContent);

        $this->artisan('env:sync', [
            '--provider' => '1password',
        ])
            ->expectsQuestion('Select action (1-6)', '1') // Push
            ->expectsOutputToContain('Pushing .env to 1Password')
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
            '--provider' => '1password',
        ])
            ->expectsQuestion('Select action (1-6)', '2') // Pull
            ->expectsOutputToContain('Pulling .env from 1Password')
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
            '--provider' => '1password',
        ])
            ->expectsQuestion('Select action (1-6)', '5') // List
            ->expectsOutputToContain('Listing all environments')
            ->expectsTable(
                ['Environment', 'Title', 'Last Updated'],
                [
                    ['local', 'Metacomet-Technologies/env-sync/local/.env', $this->mockProvider->getItem('Metacomet Technologies, LLC', 'Metacomet-Technologies/env-sync/local/.env')['updatedAt']],
                    ['staging', 'Metacomet-Technologies/env-sync/staging/.env', $this->mockProvider->getItem('Metacomet Technologies, LLC', 'Metacomet-Technologies/env-sync/staging/.env')['updatedAt']],
                ]
            )
            ->expectsQuestion('Continue with another action?', false)
            ->assertSuccessful();
    });
});
