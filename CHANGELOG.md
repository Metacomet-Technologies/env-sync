# Changelog

All notable changes to `env-sync` will be documented in this file.

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