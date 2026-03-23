# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel Environment Sync is a Laravel package for secure synchronization of environment variables across development machines using secret managers. It uses a driver-based architecture to support multiple providers (1Password and AWS Secrets Manager implemented; Bitwarden has a skeleton with stub methods).

## Key Commands

### Development
```bash
# Install dependencies
composer install

# Run tests
vendor/bin/pest

# Run a single test file
vendor/bin/pest tests/Unit/ProviderManagerTest.php

# Filter tests by name
vendor/bin/pest --filter=TestNamePattern

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
- `AwsSecretsManagerProvider`: Uses AWS SDK (`aws/aws-sdk-php`, suggested dependency) with region/profile/credentials config
- `BitwardenProvider`: Skeleton with stub methods (planned)

### Testing Strategy
Tests use Pest (not traditional PHPUnit syntax) with `beforeEach`/`it`/`describe` blocks. Mock providers in `tests/Mocks/` avoid external dependencies. Tests replace the `ProviderManager` singleton via `$this->app->singleton()` to inject mock providers. Test .env files are cleaned up in `afterEach` hooks.

## Code Quality

- **PHPStan**: Level 5 with Larastan, baseline in `phpstan-baseline.neon`
- **Pint**: Default Laravel preset (no custom config)
- **CI**: GitHub Actions matrix — PHP 8.1-8.5 × Laravel 10/11/12/13, plus separate PHPStan and Pint workflows

## Configuration

Main configuration file: `config/env-sync.php`
- `default`: Default provider name
- `providers`: Provider-specific settings (1Password vault, AWS region/profile/credentials, Bitwarden org/server)
- `required_variables`: Variables that must exist (validated after pull)
- `backup.enabled`, `backup.max_backups`, `backup.directory`: Backup settings for overwritten .env files

## Environment File Mapping
- `local` → `.env`
- `staging` → `.env.staging`
- `production` → `.env.production`
- `testing` → `.env.testing`

## Naming Convention
Items automatically named as: `{organization}/{repository}/{environment}/.env`
Git info extracted from remote.origin.url