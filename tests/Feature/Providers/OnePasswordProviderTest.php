<?php

use Metacomet\EnvSync\Contracts\SecretProvider;
use Metacomet\EnvSync\Providers\OnePasswordProvider;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->provider = new OnePasswordProvider;
    $this->testEnvFile = sys_get_temp_dir().'/.env.test.'.uniqid();
    $this->testEnvContent = "APP_NAME=TestApp\nAPP_ENV=testing\nAPP_KEY=base64:test\nDB_CONNECTION=sqlite\n";

    // Create a test env file
    file_put_contents($this->testEnvFile, $this->testEnvContent);
});

afterEach(function () {
    // Clean up test files
    if (file_exists($this->testEnvFile)) {
        unlink($this->testEnvFile);
    }

    // Clean up any backup files
    $backupPattern = $this->testEnvFile.'.backup.*';
    foreach (glob($backupPattern) as $file) {
        unlink($file);
    }
});

it('has the correct provider name', function () {
    expect($this->provider->getName())->toBe('1Password');
});

it('implements SecretProvider interface', function () {
    expect($this->provider)->toBeInstanceOf(SecretProvider::class);
});

it('returns boolean for isAvailable', function () {
    expect($this->provider->isAvailable())->toBeBool();
});

it('returns boolean for isAuthenticated', function () {
    expect($this->provider->isAuthenticated())->toBeBool();
});

it('returns auth instructions containing op signin', function () {
    $instructions = $this->provider->getAuthInstructions();

    expect($instructions)
        ->toBeString()
        ->toContain('op signin');
});

it('returns install instructions containing 1password-cli', function () {
    $instructions = $this->provider->getInstallInstructions();

    expect($instructions)
        ->toBeString()
        ->toContain('1password-cli');
});

it('throws exception when pushing to non-existent env file', function () {
    $this->provider->push([
        'environment' => 'nonexistent',
    ]);
})->throws(Exception::class, 'Environment file not found');

it('throws exception when pulling non-existent item', function () {
    // Create a test class that extends OnePasswordProvider with getItemId accessible
    $provider = new class extends OnePasswordProvider
    {
        public function getItemId(string $vault, string $title): ?string
        {
            return null;
        }
    };

    expect(fn () => $provider->pull([
        'environment' => 'test',
        'vault' => 'TestVault',
    ]))->toThrow(Exception::class, 'not found in vault');
});

it('returns boolean for exists method', function () {
    $result = $this->provider->exists([
        'environment' => 'test',
        'vault' => 'TestVault',
    ]);

    expect($result)->toBeBool();
});

it('returns array from list method', function () {
    // Mock the provider to simulate successful list
    $provider = Mockery::mock(OnePasswordProvider::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $mockProcess = Mockery::mock(Process::class);
    $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
    $mockProcess->shouldReceive('getOutput')->andReturn(json_encode([
        [
            'id' => 'test-id',
            'title' => 'test-repo/local/.env',
            'updated_at' => '2024-01-01T00:00:00Z',
        ],
    ]));

    $provider->shouldReceive('runProcess')->andReturn($mockProcess);
    $provider->shouldReceive('getGitInfo')->andReturn(['repo' => 'test-repo']);

    $result = $provider->list(['vault' => 'TestVault']);

    expect($result)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($result[0]['id'])->toBe('test-id')
        ->and($result[0]['environment'])->toBe('local');
});

it('throws exception when deleting non-existent item', function () {
    // Create a test class that extends OnePasswordProvider with getItemId accessible
    $provider = new class extends OnePasswordProvider
    {
        public function getItemId(string $vault, string $title): ?string
        {
            return null;
        }
    };

    expect(fn () => $provider->delete([
        'environment' => 'test',
        'vault' => 'TestVault',
    ]))->toThrow(Exception::class, 'not found in vault');
});

it('returns correct structure from compare method', function () {
    // Mock the provider for compare test
    $provider = Mockery::mock(OnePasswordProvider::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('getEnvFilePath')->andReturn($this->testEnvFile);
    $provider->shouldReceive('pull')->andReturn($this->testEnvContent);

    $result = $provider->compare(['environment' => 'test']);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['localExists', 'remoteExists', 'areIdentical', 'localContent', 'remoteContent'])
        ->and($result['localExists'])->toBeTrue()
        ->and($result['remoteExists'])->toBeTrue()
        ->and($result['areIdentical'])->toBeTrue();
});

describe('1Password CLI integration', function () {
    it('checks for op command availability', function () {
        $process = new Process(['which', 'op']);
        $process->run();

        expect($this->provider->isAvailable())->toBe($process->isSuccessful());
    });

    it('validates authentication state correctly', function () {
        $process = new Process(['op', 'account', 'list']);
        $process->run();

        expect($this->provider->isAuthenticated())->toBe($process->isSuccessful());
    });
});
