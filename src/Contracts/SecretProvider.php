<?php

namespace Metacomet\EnvSync\Contracts;

interface SecretProvider
{
    /**
     * Get the provider name
     */
    public function getName(): string;

    /**
     * Check if the provider CLI/tools are available
     */
    public function isAvailable(): bool;

    /**
     * Check if authenticated with the provider
     */
    public function isAuthenticated(): bool;

    /**
     * Push environment file to the provider
     */
    public function push(array $config): void;

    /**
     * Pull environment file from the provider
     */
    public function pull(array $config): string;

    /**
     * Check if the item exists in the provider
     */
    public function exists(array $config): bool;

    /**
     * Compare local and remote versions
     */
    public function compare(array $config): array;

    /**
     * List all items for this project
     */
    public function list(array $config): array;

    /**
     * Delete an item from the provider
     */
    public function delete(array $config): void;

    /**
     * Get authentication instructions
     */
    public function getAuthInstructions(): string;

    /**
     * Get installation instructions
     */
    public function getInstallInstructions(): string;
}
