# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel Environment Sync is a Laravel package for secure synchronization of environment variables across development machines using secret managers. It uses a driver-based architecture to support multiple providers (currently 1Password, with AWS Secrets Manager and Bitwarden planned).

## Key Commands

### Development
```bash
# Install dependencies
composer install

# Run tests
vendor/bin/pest

# Run tests with coverage
vendor/bin/pest --coverage

# Code formatting
vendor/bin/pint

# Static analysis
vendor/bin/phpstan analyse

# Prepare testbench for package discovery
composer run prepare
```

### Package Commands (when installed in Laravel project)
```bash
# Push environment to secret manager
php artisan env:push [environment] [--force] [--vault=] [--title=]

# Pull environment from secret manager  
php artisan env:pull [environment] [--force] [--vault=]

# Interactive sync mode
php artisan env:sync [environment] [--provider=] [--vault=] [--title=]
```

## Architecture

### Provider System
The package uses a driver-based architecture with a central `ProviderManager` (src/ProviderManager.php:11) that manages different secret provider implementations. All providers implement the `SecretProvider` interface (src/Contracts/SecretProvider.php:5).

**Provider Manager Flow:**
1. `ProviderManager::get()` resolves provider by name or uses default
2. Creates provider instance from `DRIVER_MAP` constant
3. Passes configuration from `config/env-sync.php`
4. Returns cached instance for subsequent calls

**Base Provider** (src/Providers/BaseProvider.php:8) provides common functionality:
- Git repository detection for automatic naming
- Environment file path resolution
- Backup creation before modifications
- Base64 encoding/decoding for data integrity

### Command Structure
Three Artisan commands handle user interactions:
- `EnvPushCommand`: Pushes local .env to provider
- `EnvPullCommand`: Pulls .env from provider
- `EnvSyncCommand`: Interactive mode with Laravel Prompts integration

Commands use dependency injection to receive `ProviderManager` and handle provider-specific configuration through options.

### Provider Implementations
Each provider extends `BaseProvider` and implements provider-specific logic:
- `OnePasswordProvider`: Uses `op` CLI with vault support
- `AwsSecretsManagerProvider`: Placeholder for AWS integration
- `BitwardenProvider`: Placeholder for Bitwarden integration

### Testing Strategy
Tests use mock providers (`tests/Mocks/MockSecretProvider.php`) to avoid external dependencies. The `ProviderManager::register()` method allows injecting test providers.

## Configuration

Main configuration file: `config/env-sync.php`
- `default`: Default provider name
- `providers`: Provider-specific settings
- `required_variables`: Variables that must exist

## Environment File Mapping
- `local` → `.env`
- `staging` → `.env.staging`
- `production` → `.env.production`
- `testing` → `.env.testing`

## Naming Convention
Items automatically named as: `{organization}/{repository}/{environment}/.env`
Git info extracted from remote.origin.url