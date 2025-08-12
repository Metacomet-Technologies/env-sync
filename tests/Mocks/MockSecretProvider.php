<?php

namespace Metacomet\EnvSync\Tests\Mocks;

use Metacomet\EnvSync\Contracts\SecretProvider;

/**
 * Mock implementation of SecretProvider for testing
 */
class MockSecretProvider implements SecretProvider
{
    private bool $isAvailable;

    private bool $isAuthenticated;

    private array $storage = [];

    private string $name;

    public function __construct(
        string $name = 'Mock Provider',
        bool $isAvailable = true,
        bool $isAuthenticated = true
    ) {
        $this->name = $name;
        $this->isAvailable = $isAvailable;
        $this->isAuthenticated = $isAuthenticated;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function isAuthenticated(): bool
    {
        return $this->isAuthenticated;
    }

    public function push(array $config): void
    {
        $environment = $config['environment'] ?? 'local';
        $content = $config['content'] ?? '';

        if (empty($content)) {
            throw new \Exception('No content provided');
        }

        $this->storage[$environment] = [
            'content' => $content,
            'updatedAt' => new \DateTime,
        ];
    }

    public function pull(array $config): string
    {
        $environment = $config['environment'] ?? 'local';

        if (! isset($this->storage[$environment])) {
            throw new \Exception("Environment '{$environment}' not found");
        }

        return $this->storage[$environment]['content'];
    }

    public function exists(array $config): bool
    {
        $environment = $config['environment'] ?? 'local';

        return isset($this->storage[$environment]);
    }

    public function compare(array $config): array
    {
        $environment = $config['environment'] ?? 'local';
        $localContent = $config['localContent'] ?? '';

        $remoteExists = $this->exists($config);
        $remoteContent = $remoteExists ? $this->pull($config) : '';

        return [
            'localExists' => ! empty($localContent),
            'remoteExists' => $remoteExists,
            'areIdentical' => $localContent === $remoteContent,
            'localContent' => $localContent,
            'remoteContent' => $remoteContent,
        ];
    }

    public function list(array $config): array
    {
        $items = [];

        foreach ($this->storage as $environment => $data) {
            $items[] = [
                'id' => 'mock-'.$environment,
                'title' => "mock/{$environment}/.env",
                'environment' => $environment,
                'updatedAt' => $data['updatedAt']->format('c'),
            ];
        }

        return $items;
    }

    public function delete(array $config): void
    {
        $environment = $config['environment'] ?? 'local';

        if (! isset($this->storage[$environment])) {
            throw new \Exception("Environment '{$environment}' not found");
        }

        unset($this->storage[$environment]);
    }

    public function getAuthInstructions(): string
    {
        return 'Mock provider authentication: No action required';
    }

    public function getInstallInstructions(): string
    {
        return 'Mock provider installation: No installation required';
    }

    /**
     * Helper methods for testing
     */
    public function setAvailable(bool $available): void
    {
        $this->isAvailable = $available;
    }

    public function setAuthenticated(bool $authenticated): void
    {
        $this->isAuthenticated = $authenticated;
    }

    public function setStorage(array $storage): void
    {
        $this->storage = $storage;
    }

    public function getStorage(): array
    {
        return $this->storage;
    }

    public function hasEnvironment(string $environment): bool
    {
        return isset($this->storage[$environment]);
    }

    public function getEnvironmentContent(string $environment): ?string
    {
        return $this->storage[$environment]['content'] ?? null;
    }
}
