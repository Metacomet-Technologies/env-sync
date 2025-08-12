<?php

use Metacomet\EnvSync\Providers\BaseProvider;
use Symfony\Component\Process\Process;

// Create a concrete implementation for testing
class TestProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'Test Provider';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    public function push(array $config): void
    {
        // Test implementation
    }

    public function pull(array $config): string
    {
        return "TEST=value\n";
    }

    public function exists(array $config): bool
    {
        return true;
    }

    public function list(array $config): array
    {
        return [];
    }

    public function delete(array $config): void
    {
        // Test implementation
    }

    public function getAuthInstructions(): string
    {
        return 'Test auth instructions';
    }

    public function getInstallInstructions(): string
    {
        return 'Test install instructions';
    }
}

beforeEach(function () {
    $this->provider = new TestProvider;
    $this->testEnvFile = sys_get_temp_dir().'/.env.test.'.uniqid();

    // Create a test env file
    file_put_contents($this->testEnvFile, "TEST=value\n");
});

afterEach(function () {
    if (file_exists($this->testEnvFile)) {
        unlink($this->testEnvFile);
    }

    // Clean up any backup files
    $backupPattern = dirname($this->testEnvFile).'/.env.*.backup.*';
    foreach (glob($backupPattern) as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

describe('Git integration', function () {
    it('returns git repository info', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('getGitInfo');
        $method->setAccessible(true);

        $gitInfo = $method->invoke($this->provider);

        expect($gitInfo)
            ->toBeArray()
            ->toHaveKey('repo');
    });

    it('generates title with custom value', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('generateTitle');
        $method->setAccessible(true);

        $title = $method->invoke($this->provider, 'production', 'custom-title');

        expect($title)->toBe('custom-title');
    });

    it('generates title without custom value', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('generateTitle');
        $method->setAccessible(true);

        $title = $method->invoke($this->provider, 'production');

        expect($title)
            ->toBeString()
            ->toContain('production/.env');
    });
});

describe('Environment file handling', function () {
    it('returns correct path for local environment', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('getEnvFilePath');
        $method->setAccessible(true);

        $path = $method->invoke($this->provider, 'local');

        expect($path)
            ->toEndWith('.env')
            ->not->toContain('.env.local');
    });

    it('returns correct path for development environment', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('getEnvFilePath');
        $method->setAccessible(true);

        $path = $method->invoke($this->provider, 'development');

        expect($path)
            ->toEndWith('.env')
            ->not->toContain('.env.development');
    });

    it('returns correct path for other environments', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('getEnvFilePath');
        $method->setAccessible(true);

        $path = $method->invoke($this->provider, 'staging');

        expect($path)->toEndWith('.env.staging');
    });
});

describe('Backup functionality', function () {
    it('creates backup file successfully', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('createBackup');
        $method->setAccessible(true);

        $backupPath = $method->invoke($this->provider, $this->testEnvFile);

        expect($backupPath)
            ->toContain('.backup.')
            ->and(file_exists($backupPath))->toBeTrue();

        // Clean up
        unlink($backupPath);
    });

    it('throws exception for non-existent file', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('createBackup');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($this->provider, '/nonexistent/file.env'))
            ->toThrow(Exception::class);
    });
});

describe('Content encoding', function () {
    it('encodes content to base64', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('encodeContent');
        $method->setAccessible(true);

        $content = "TEST=value\nKEY=secret";
        $encoded = $method->invoke($this->provider, $content);

        expect($encoded)
            ->toBe(base64_encode($content))
            ->and(base64_decode($encoded))->toBe($content);
    });

    it('decodes base64 content', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('decodeContent');
        $method->setAccessible(true);

        $original = "TEST=value\nKEY=secret";
        $encoded = base64_encode($original);

        $decoded = $method->invoke($this->provider, $encoded);

        expect($decoded)->toBe($original);
    });

    it('handles plain text content', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('decodeContent');
        $method->setAccessible(true);

        $content = "APP_KEY=value\nDB_HOST=localhost";

        $decoded = $method->invoke($this->provider, $content);

        expect($decoded)->toBe($content);
    });
});

describe('Process execution', function () {
    it('runs process and returns result', function () {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('runProcess');
        $method->setAccessible(true);

        $process = $method->invoke($this->provider, ['echo', 'test']);

        expect($process)
            ->toBeInstanceOf(Process::class)
            ->and($process->isSuccessful())->toBeTrue()
            ->and(trim($process->getOutput()))->toBe('test');
    });
});

describe('Compare functionality', function () {
    it('compares identical local and remote files', function () {
        $provider = Mockery::mock(TestProvider::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $provider->shouldReceive('getEnvFilePath')->andReturn($this->testEnvFile);
        $provider->shouldReceive('pull')->andReturn("TEST=value\n");

        $result = $provider->compare(['environment' => 'test']);

        expect($result)
            ->toHaveKeys(['localExists', 'remoteExists', 'areIdentical'])
            ->and($result['localExists'])->toBeTrue()
            ->and($result['remoteExists'])->toBeTrue()
            ->and($result['areIdentical'])->toBeTrue();
    });

    it('handles missing remote file', function () {
        $provider = Mockery::mock(TestProvider::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $provider->shouldReceive('getEnvFilePath')->andReturn($this->testEnvFile);
        $provider->shouldReceive('pull')->andThrow(new Exception('Not found'));

        $result = $provider->compare(['environment' => 'test']);

        expect($result)
            ->and($result['localExists'])->toBeTrue()
            ->and($result['remoteExists'])->toBeFalse()
            ->and($result['areIdentical'])->toBeFalse();
    });
});
