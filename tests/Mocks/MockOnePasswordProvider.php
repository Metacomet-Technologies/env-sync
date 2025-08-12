<?php

namespace Metacomet\EnvSync\Tests\Mocks;

use Metacomet\EnvSync\Providers\OnePasswordProvider;

/**
 * Mock OnePassword Provider for testing
 *
 * This mock simulates 1Password behavior without requiring the actual CLI
 */
class MockOnePasswordProvider extends OnePasswordProvider
{
    private array $storage = [];

    private bool $available = true;

    private bool $authenticated = true;

    public function __construct(
        bool $available = true,
        bool $authenticated = true
    ) {
        $this->available = $available;
        $this->authenticated = $authenticated;

        // Pre-populate with some test data
        $this->storage['Private']['test-repo/local/.env'] = [
            'id' => 'mock-local-id',
            'content' => "APP_NAME=TestApp\nAPP_ENV=local\nAPP_KEY=base64:test\nDB_CONNECTION=sqlite\n",
            'updatedAt' => '2024-01-01T00:00:00Z',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function push(array $config): void
    {
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $force = $config['force'] ?? false;
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        $envFile = $this->getEnvFilePath($environment);

        if (! file_exists($envFile)) {
            throw new \Exception("Environment file not found: {$envFile}");
        }

        $envContent = file_get_contents($envFile);

        // Check if item exists
        $exists = isset($this->storage[$vault][$title]);

        if ($exists && ! $force) {
            $existingContent = base64_decode($this->storage[$vault][$title]['content']);
            if ($existingContent === $envContent) {
                throw new \Exception('Files are identical - no push needed. Use --force to push anyway.');
            }
        }

        // Store the content
        $this->storage[$vault][$title] = [
            'id' => 'mock-'.md5($title),
            'content' => base64_encode($envContent),
            'updatedAt' => date('c'),
        ];
    }

    public function pull(array $config): string
    {
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $force = $config['force'] ?? false;
        $skipWrite = $config['skipWrite'] ?? false;
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        if (! isset($this->storage[$vault][$title])) {
            throw new \Exception("Item '{$title}' not found in vault '{$vault}'");
        }

        $envContent = base64_decode($this->storage[$vault][$title]['content']);

        if (! $skipWrite) {
            $envFile = $this->getEnvFilePath($environment);

            if (file_exists($envFile)) {
                $localContent = file_get_contents($envFile);

                if ($localContent === $envContent && ! $force) {
                    throw new \Exception('Files are identical - no pull needed. Use --force to pull anyway.');
                }

                if ($localContent !== $envContent || $force) {
                    $this->createBackup($envFile);
                }
            }

            file_put_contents($envFile, $envContent);
        }

        return $envContent;
    }

    public function exists(array $config): bool
    {
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        return isset($this->storage[$vault][$title]);
    }

    public function list(array $config): array
    {
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $gitInfo = $this->getGitInfo();

        $items = [];

        if (! isset($this->storage[$vault])) {
            return $items;
        }

        foreach ($this->storage[$vault] as $title => $data) {
            // Filter items related to this project
            if (isset($gitInfo['repo']) && str_contains($title, $gitInfo['repo'])) {
                // Extract environment from title
                if (preg_match('/\/([^\/]+)\/\.env$/', $title, $matches)) {
                    $items[] = [
                        'id' => $data['id'],
                        'title' => $title,
                        'environment' => $matches[1],
                        'updatedAt' => $data['updatedAt'],
                        'vault' => $vault,
                    ];
                }
            }
        }

        return $items;
    }

    public function delete(array $config): void
    {
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        if (! isset($this->storage[$vault][$title])) {
            throw new \Exception("Item '{$title}' not found in vault '{$vault}'");
        }

        unset($this->storage[$vault][$title]);
    }

    /**
     * Test helper methods
     */
    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function setAuthenticated(bool $authenticated): void
    {
        $this->authenticated = $authenticated;
    }

    public function addItem(string $vault, string $title, string $content): void
    {
        $this->storage[$vault][$title] = [
            'id' => 'mock-'.md5($title),
            'content' => base64_encode($content),
            'updatedAt' => date('c'),
        ];
    }

    public function getStorage(): array
    {
        return $this->storage;
    }

    public function clearStorage(): void
    {
        $this->storage = [];
    }

    public function hasItem(string $vault, string $title): bool
    {
        return isset($this->storage[$vault][$title]);
    }

    public function getItem(string $vault, string $title): ?array
    {
        return $this->storage[$vault][$title] ?? null;
    }
}
