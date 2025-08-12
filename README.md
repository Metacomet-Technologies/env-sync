# Laravel Environment Sync

[![Latest Version on Packagist](https://img.shields.io/packagist/v/metacomet-technologies/env-sync.svg?style=flat-square)](https://packagist.org/packages/metacomet-technologies/env-sync)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/metacomet-technologies/env-sync/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metacomet-technologies/env-sync/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/metacomet-technologies/env-sync/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/metacomet-technologies/env-sync/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/metacomet-technologies/env-sync.svg?style=flat-square)](https://packagist.org/packages/metacomet-technologies/env-sync)

A Laravel package for secure synchronization of environment variables across development machines using 1Password and AWS Secrets Manager, with support for additional secret managers on the roadmap.

## Features

- 🔐 **1Password Integration**: Full support for secure environment synchronization
- ☁️ **AWS Secrets Manager**: Store and retrieve environment files from AWS
- 🔄 **Bidirectional Sync**: Push to and pull from your secret manager
- 📁 **Multi-Environment**: Support for local, staging, production, etc.
- 🎯 **Smart Detection**: Auto-detects repository and environment names
- 💾 **Automatic Backups**: Creates backups before overwriting files
- 🏷️ **Consistent Naming**: Uses Git repository info for consistent naming
- ♻️ **Laravel Integration**: Seamless integration with Laravel projects
- 🚀 **Extensible**: Architecture ready for additional providers

## Installation

You can install the package via composer:

```bash
composer require metacomet-technologies/env-sync
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="env-sync-config"
```

## Supported Providers

### ✅ 1Password (Available)
Full integration with complete support for vaults, automatic title generation, and base64 encoding.

**Prerequisites:**
```bash
# macOS
brew install --cask 1password-cli

# Windows and Linux
# https://developer.1password.com/docs/cli/get-started/

# Sign in
eval $(op signin)
```

### ✅ AWS Secrets Manager (Available)
Store and retrieve environment files securely in AWS Secrets Manager with full support for regions, profiles, and IAM roles.

**Prerequisites:**
```bash
# Install AWS SDK for PHP
composer require aws/aws-sdk-php

# Configure AWS credentials (choose one):
# Option 1: AWS CLI
aws configure

# Option 2: Environment variables
export AWS_ACCESS_KEY_ID=your-key
export AWS_SECRET_ACCESS_KEY=your-secret

# Option 3: IAM Role (automatic on EC2/ECS/Lambda)
```

### 🚧 Roadmap

The following providers are planned for future releases:

#### Bitwarden (Coming Soon)
- Open-source password manager
- Self-hosted instance support
- Organization vault support

#### Other Planned Providers
- HashiCorp Vault
- Azure Key Vault
- Google Secret Manager

## Usage

### Push Environment to Secret Manager

```bash
# Push to default provider (1Password or AWS)
php artisan env:push

# Push specific environment
php artisan env:push staging
php artisan env:push production

# Force push even if identical
php artisan env:push --force

# 1Password specific:
php artisan env:push --vault="Company Vault"
php artisan env:push --title="my-custom-title"

# AWS Secrets Manager specific:
php artisan env:push --provider=aws
php artisan env:push --provider=aws --region=us-west-2
php artisan env:push --provider=aws --profile=production
```

### Pull Environment from Secret Manager

```bash
# Pull from default provider
php artisan env:pull

# Pull specific environment
php artisan env:pull staging
php artisan env:pull production

# Force pull even if identical
php artisan env:pull --force

# 1Password specific:
php artisan env:pull --vault="Company Vault"

# AWS Secrets Manager specific:
php artisan env:pull --provider=aws
php artisan env:pull --provider=aws --region=us-west-2
php artisan env:pull --provider=aws --profile=production
```

### Interactive Sync Mode

```bash
# Interactive mode with menu
php artisan env:sync

# For specific environment
php artisan env:sync production

# With custom vault
php artisan env:sync --vault="Company Vault"
```

Interactive mode provides:
- Status checking
- Push/Pull operations
- File comparison
- List all environments
- Visual diff display

## Configuration

Edit `config/env-sync.php`:

```php
return [
    'default' => env('ENV_SYNC_PROVIDER', '1password'),
    
    'providers' => [
        '1password' => [
            'vault' => env('ONEPASSWORD_VAULT', 'Private'),
        ],
        
        'aws' => [
            'region' => env('ENV_SYNC_AWS_REGION', 'us-east-1'),
            'profile' => env('AWS_PROFILE'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('AWS_SECRET_PREFIX', ''),
        ],
    ],
    
    'required_variables' => [
        'APP_KEY',
        'DB_CONNECTION',
        // Add your critical variables
    ],
];
```

## Environment File Mapping

| Environment | File Path |
|------------|----------|
| `local` | `.env` |
| `staging` | `.env.staging` |
| `production` | `.env.production` |
| `testing` | `.env.testing` |

## Naming Conventions

Items are automatically named based on your Git repository:

- **Format**: `{organization}/{repository}/{environment}/.env`
- **Examples**:
  - `metacomet/my-app/local/.env`
  - `metacomet/my-app/production/.env`

## Security

- ✅ Files are encrypted at rest by each provider
- ✅ Base64 encoding prevents data corruption (1Password)
- ✅ Authentication required for all operations
- ✅ Automatic backups before overwriting
- ✅ No sensitive data in command history
- ✅ Provider-specific security features respected

## Current Provider Features

### 1Password
- ✅ Full vault support
- ✅ Automatic title generation based on Git info
- ✅ Base64 encoding for data integrity
- ✅ Interactive sync mode
- ✅ Multi-environment support
- ✅ Automatic backups
- ✅ File comparison and diff display

### AWS Secrets Manager
- ✅ Multi-region support
- ✅ AWS profile and credential support
- ✅ IAM role integration
- ✅ Automatic secret naming based on Git info
- ✅ Base64 encoding for data integrity
- ✅ Secret tagging for organization
- ✅ Soft delete with recovery window
- ✅ Interactive sync mode
- ✅ Multi-environment support
- ✅ Automatic backups

## Workflow Examples

### Initial Setup

```bash
# 1. Install the package
composer require metacomet-technologies/env-sync

# 2. Push your local .env to 1Password
php artisan env:push

# 3. Push other environments
php artisan env:push staging
php artisan env:push production
```

### Team Member Setup

```bash
# 1. Clone repository
git clone git@github.com:your-org/your-app.git

# 2. Install dependencies
composer install

# 3. Pull environment file from 1Password
php artisan env:pull

# 4. Start developing!
```

### After Making Changes

```bash
# 1. Check differences
php artisan env:sync
# Select option 3 (Compare)

# 2. Push changes
php artisan env:push

# 3. Team members pull updates
php artisan env:pull
```

## Migrating from Previous Version

If you were using the Laravel-specific 1Password commands from a previous implementation, the commands remain the same:

| Old Command | New Command |
|------------|------------|
| `php artisan env:push` | `php artisan env:push` |
| `php artisan env:pull` | `php artisan env:pull` |
| `php artisan env:sync` | `php artisan env:sync` |

The package defaults to 1Password, maintaining full backward compatibility. To use AWS Secrets Manager, set:
```bash
ENV_SYNC_PROVIDER=aws
```
Or specify in commands:
```bash
php artisan env:push --provider=aws
```

## Troubleshooting

### 1Password CLI Not Available
```bash
# The commands will show installation instructions:
php artisan env:sync

# macOS installation:
brew install --cask 1password-cli
```

### Authentication Issues
```bash
# 1Password authentication
eval $(op signin)

# AWS authentication
aws configure
# or use environment variables:
export AWS_ACCESS_KEY_ID=your-key
export AWS_SECRET_ACCESS_KEY=your-secret
```

### Files Are Identical
Use `--force` flag to overwrite anyway:
```bash
php artisan env:push --force
php artisan env:pull --force
```
