# Changelog

All notable changes to `env-sync` will be documented in this file.

## v0.0.5 - AWS Secrets Manager Support - 2025-01-12

### 🚀 New Features

This release adds full support for AWS Secrets Manager as a new provider option alongside 1Password.

#### Added

- ☁️ **AWS Secrets Manager Provider** - Complete implementation with all features
- 🌍 **Multi-region Support** - Configure and use different AWS regions
- 🔐 **Flexible Authentication** - Support for AWS profiles, IAM roles, and explicit credentials
- 🏷️ **Smart Secret Naming** - Automatic naming based on Git repository info
- 🔒 **Data Integrity** - Base64 encoding for secure storage
- 🗂️ **Secret Organization** - Automatic tagging for better AWS console navigation
- ♻️ **Safe Deletion** - Soft delete with 30-day recovery window
- 🧪 **Comprehensive Tests** - Full unit test coverage for AWS provider
- 📦 **Optional Dependency** - AWS SDK added as suggested dependency

#### Changed

- 📚 Updated README with complete AWS Secrets Manager documentation
- 💬 Improved error messages with helpful installation instructions
- 🔧 Updated PHPStan baseline for optional dependencies

#### Fixed

- ✅ Resolved PHPStan analysis errors for optional AWS SDK dependency

#### Installation

To use AWS Secrets Manager:

```bash
# Install the package
composer require metacomet-technologies/env-sync:^0.0.5

# Install AWS SDK (required for AWS provider)
composer require aws/aws-sdk-php
```

#### Usage

```bash
# Push to AWS Secrets Manager
php artisan env:push --provider=aws

# Pull from AWS Secrets Manager
php artisan env:pull --provider=aws --region=us-west-2

# Set default provider
ENV_SYNC_PROVIDER=aws
```

**Full Changelog**: https://github.com/Metacomet-Technologies/env-sync/compare/v0.0.4...v0.0.5

## v0.0.4 - Laravel Prompts Integration - 2025-08-12

### ✨ Enhancements

This release introduces Laravel Prompts for a better interactive experience and improves the provider architecture.

#### Added

- 🎨 **Laravel Prompts Integration** - Beautiful interactive menus and prompts
- 🏗️ **Driver-based Configuration** - More flexible provider system
- 🔄 **Interactive Sync Mode** - Enhanced user experience with visual feedback

#### Fixed

- ✅ Resolved 1Password update failures
- 🔧 Fixed git working directory detection issues
- 🐛 Various bug fixes and improvements

**Full Changelog**: https://github.com/Metacomet-Technologies/env-sync/compare/v0.0.3...v0.0.4

## v0.0.3 - Dependency Fix - 2025-08-12

### 🔧 Bug Fix

This release fixes a missing production dependency that could cause issues during package installation.

#### Fixed

- ✅ Added `symfony/process` as an explicit production dependency
- 📦 Package now properly declares all required dependencies
- 🔨 Prevents potential `Class not found` errors for users

#### Technical Details

The package uses `Symfony\Component\Process\Process` in production code (providers and commands) but it wasn't explicitly required in composer.json. This could cause issues if the dependency wasn't pulled in by other packages.

#### Installation

```bash
composer require metacomet-technologies/env-sync:^0.0.3

```
**Full Changelog**: https://github.com/Metacomet-Technologies/env-sync/compare/v0.0.2...v0.0.3

## v0.0.2 - CI/CD Improvements - 2025-08-12

### 🚀 CI/CD Improvements

This release focuses on improving the reliability and performance of our continuous integration pipeline.

#### Improvements

- 📊 **Optimized Test Matrix** - Reduced from 48 to 5 strategic test combinations
- ⚡ **Faster CI** - Tests now complete in ~40 seconds instead of potential hours
- 🔧 **Fixed Compatibility** - Resolved prefer-lowest issues with orchestra/canvas
- 🎯 **Focused Testing** - Removed Windows testing (unnecessary for Laravel packages)

#### Test Coverage

The optimized matrix now tests:

- PHP 8.1 + Laravel 10 (minimum for L10)
- PHP 8.2 + Laravel 11 (minimum for L11)
- PHP 8.2 + Laravel 12 (minimum for L12)
- PHP 8.3 + Laravel 12 (current stable)
- PHP 8.4 + Laravel 12 (bleeding edge)

#### Installation

```bash
composer require metacomet-technologies/env-sync:^0.0.2


```
**Full Changelog**: https://github.com/Metacomet-Technologies/env-sync/compare/v0.0.1...v0.0.2

## v0.0.1 - Initial Release - 2025-08-12

### 🎉 Initial Release

#### Features

- 🔐 **1Password Integration** - Full support for 1Password CLI
- 🏗️ **Provider Pattern** - Extensible architecture for multiple secret managers
- 📦 **Laravel Package** - Easy installation via Composer
- 🧪 **Comprehensive Testing** - Full Pest test coverage with mocks for CI/CD
- 🔄 **Multiple Commands**:
  - `env:push` - Push .env files to secret manager
  - `env:pull` - Pull .env files from secret manager
  - `env:sync` - Interactive sync utility
  

#### Compatibility

- Laravel 10, 11, and 12
- PHP 8.1, 8.2, 8.3, and 8.4

#### Roadmap

- 🔜 AWS Secrets Manager support
- 🔜 BitWarden support

#### Installation

```bash
composer require metacomet-technologies/env-sync



```