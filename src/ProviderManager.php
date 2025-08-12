<?php

namespace Metacomet\EnvSync;

use Exception;
use Metacomet\EnvSync\Contracts\SecretProvider;
use Metacomet\EnvSync\Providers\AwsSecretsManagerProvider;
use Metacomet\EnvSync\Providers\BitwardenProvider;
use Metacomet\EnvSync\Providers\OnePasswordProvider;

class ProviderManager
{
    protected array $providers = [];

    public function __construct()
    {
        $this->registerDefaultProviders();
    }

    protected function registerDefaultProviders(): void
    {
        $this->register('1password', new OnePasswordProvider());
        $this->register('aws', new AwsSecretsManagerProvider());
        $this->register('bitwarden', new BitwardenProvider());
    }

    public function register(string $key, SecretProvider $provider): void
    {
        $this->providers[$key] = $provider;
    }

    public function get(string $key): SecretProvider
    {
        if (!isset($this->providers[$key])) {
            throw new Exception("Provider '{$key}' not found. Available providers: " . implode(', ', array_keys($this->providers)));
        }

        return $this->providers[$key];
    }

    public function all(): array
    {
        return $this->providers;
    }

    public function getAvailableProviders(): array
    {
        $available = [];
        
        foreach ($this->providers as $key => $provider) {
            if ($provider->isAvailable()) {
                $available[$key] = $provider;
            }
        }

        return $available;
    }

    public function getAuthenticatedProviders(): array
    {
        $authenticated = [];
        
        foreach ($this->getAvailableProviders() as $key => $provider) {
            if ($provider->isAuthenticated()) {
                $authenticated[$key] = $provider;
            }
        }

        return $authenticated;
    }

    public function hasProvider(string $key): bool
    {
        return isset($this->providers[$key]);
    }
}