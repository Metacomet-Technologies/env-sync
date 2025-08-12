<?php

namespace Metacomet\EnvSync;

use Exception;
use Metacomet\EnvSync\Contracts\SecretProvider;
use Metacomet\EnvSync\Providers\AwsSecretsManagerProvider;
use Metacomet\EnvSync\Providers\BitwardenProvider;
use Metacomet\EnvSync\Providers\OnePasswordProvider;

class ProviderManager
{
    /**
     * Map of driver names to their provider classes
     */
    protected const DRIVER_MAP = [
        '1password' => OnePasswordProvider::class,
        'aws' => AwsSecretsManagerProvider::class,
        'bitwarden' => BitwardenProvider::class,
    ];

    protected array $providers = [];
    protected array $customProviders = [];

    /**
     * Register a custom provider instance (primarily for testing)
     */
    public function register(string $name, SecretProvider $provider): void
    {
        $this->customProviders[$name] = $provider;
        $this->providers[$name] = $provider;
    }

    /**
     * Get a provider by name
     */
    public function get(?string $name = null): SecretProvider
    {
        $name = $name ?: $this->getDefaultProvider();

        // Return cached provider if exists
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        // Check for custom registered providers first (for testing)
        if (isset($this->customProviders[$name])) {
            $this->providers[$name] = $this->customProviders[$name];
            return $this->providers[$name];
        }

        // Create and cache the provider
        $this->providers[$name] = $this->createProvider($name);

        return $this->providers[$name];
    }

    /**
     * Create a new provider instance
     */
    protected function createProvider(string $name): SecretProvider
    {
        $config = $this->getProviderConfig($name);

        if (! $config) {
            throw new Exception("Provider '{$name}' is not configured.");
        }

        $driverName = $config['driver'] ?? $name;
        $driverClass = $this->getDriverClass($driverName);

        if (! $driverClass) {
            throw new Exception("Driver '{$driverName}' is not supported.");
        }

        if (! class_exists($driverClass)) {
            throw new Exception("Driver class '{$driverClass}' does not exist.");
        }

        $provider = new $driverClass;

        // Pass configuration to provider if it has a setConfig method
        if (method_exists($provider, 'setConfig')) {
            $provider->setConfig($config);
        }

        return $provider;
    }

    /**
     * Get the driver class for a given driver name
     */
    protected function getDriverClass(string $driverName): ?string
    {
        return self::DRIVER_MAP[$driverName] ?? null;
    }

    /**
     * Get configuration for a specific provider
     */
    protected function getProviderConfig(string $name): ?array
    {
        return config("env-sync.providers.{$name}");
    }

    /**
     * Get the default provider name
     */
    public function getDefaultProvider(): string
    {
        return config('env-sync.default', '1password');
    }

    /**
     * Get all available driver types
     */
    public function getAvailableDrivers(): array
    {
        return array_keys(self::DRIVER_MAP);
    }

    /**
     * Check if a provider exists
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->customProviders[$name]) || ! is_null($this->getProviderConfig($name));
    }

    /**
     * Check if a driver is supported
     */
    public function hasDriver(string $driverName): bool
    {
        return isset(self::DRIVER_MAP[$driverName]);
    }

    /**
     * Get all provider instances
     */
    public function all(): array
    {
        $providers = [];
        
        // Include custom providers
        foreach ($this->customProviders as $name => $provider) {
            $providers[$name] = $provider;
        }
        
        // Include configured providers
        foreach ($this->getAvailableProviders() as $name) {
            if (!isset($providers[$name])) {
                $providers[$name] = $this->get($name);
            }
        }

        return $providers;
    }

    /**
     * Get authenticated providers
     */
    public function getAuthenticatedProviders(): array
    {
        $authenticated = [];
        foreach ($this->all() as $name => $provider) {
            if ($provider->isAvailable() && $provider->isAuthenticated()) {
                $authenticated[$name] = $provider;
            }
        }
        return $authenticated;
    }

    /**
     * Get available providers - returns just the names
     */
    public function getAvailableProviders(): array
    {
        $available = [];
        
        // Check custom providers first
        if (!empty($this->customProviders)) {
            foreach ($this->customProviders as $name => $provider) {
                if ($provider->isAvailable()) {
                    $available[] = $name;
                }
            }
            return $available;
        }
        
        // Otherwise return configured providers
        return array_keys(config('env-sync.providers', []));
    }
    
    /**
     * Get available providers with instances - for testing
     */
    public function getAvailableProvidersWithInstances(): array
    {
        $available = [];
        
        foreach ($this->customProviders as $name => $provider) {
            if ($provider->isAvailable()) {
                $available[$name] = $provider;
            }
        }
        
        $config = config('env-sync.providers', []);
        foreach ($config as $name => $settings) {
            if (!isset($available[$name])) {
                try {
                    $provider = $this->get($name);
                    if ($provider->isAvailable()) {
                        $available[$name] = $provider;
                    }
                } catch (\Exception $e) {
                    // Provider not available
                }
            }
        }
        
        return $available;
    }

    /**
     * Alias for get() method for better semantics
     */
    public function connection(?string $name = null): SecretProvider
    {
        return $this->get($name);
    }

    /**
     * Alias for getAvailableProviders()
     */
    public function getAvailableConnections(): array
    {
        return $this->getAvailableProviders();
    }

    /**
     * Alias for getDefaultProvider()
     */
    public function getDefaultConnection(): string
    {
        return $this->getDefaultProvider();
    }
}
