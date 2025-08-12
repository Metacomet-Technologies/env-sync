# Changelog

All notable changes to `env-sync` will be documented in this file.

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