# Testing Documentation

## Overview

This package includes comprehensive test coverage using Pest PHP. All tests are designed to run without external dependencies by using mock providers.

## Running Tests

```bash
# Run all tests
vendor/bin/pest

# Run tests in parallel (faster)
vendor/bin/pest --parallel

# Run with coverage
vendor/bin/pest --coverage

# Run specific test file
vendor/bin/pest tests/Feature/Commands/EnvCommandsTest.php

# Run tests matching a filter
vendor/bin/pest --filter="OnePasswordProvider"
```

## Test Structure

```
tests/
├── Feature/
│   ├── Commands/
│   │   └── EnvCommandsTest.php      # Tests for Laravel commands
│   └── Providers/
│       └── OnePasswordProviderTest.php  # Integration tests for 1Password
├── Unit/
│   ├── Providers/
│   │   └── BaseProviderTest.php     # Tests for base provider functionality
│   └── ProviderManagerTest.php      # Tests for provider management
├── Mocks/
│   ├── MockSecretProvider.php       # Generic mock implementation
│   └── MockOnePasswordProvider.php  # 1Password-specific mock
├── ArchTest.php                     # Architecture tests
├── Pest.php                         # Pest configuration
└── TestCase.php                     # Base test case
```

## Mock Providers

The test suite includes mock providers that simulate the behavior of real secret managers without requiring their CLIs to be installed.

### MockOnePasswordProvider

This mock simulates 1Password behavior:
- Stores secrets in memory during tests
- Simulates authentication states
- Provides test helpers for setup
- Works identically to the real provider

### MockSecretProvider

A generic mock that can simulate any provider:
- Configurable availability and authentication
- In-memory storage
- Perfect for testing provider-agnostic code

## CI/CD Integration

Tests run automatically in GitHub Actions on:
- Every push to main branch
- Every pull request
- Multiple PHP versions (8.1, 8.2, 8.3)
- Multiple operating systems (Ubuntu, Windows)

### Why Tests Pass in CI

The tests are designed to pass in CI environments because:

1. **No External Dependencies**: Mock providers eliminate the need for:
   - 1Password CLI
   - AWS CLI
   - Bitwarden CLI
   - Any external API calls

2. **Isolated Testing**: Each test:
   - Creates its own temporary files
   - Cleans up after itself
   - Doesn't affect other tests

3. **Deterministic Behavior**: Mocks provide:
   - Predictable responses
   - Controlled error conditions
   - Consistent timing

## Writing New Tests

When adding new features, follow these patterns:

### Testing Commands

```php
it('performs some action', function () {
    // Arrange: Set up mock provider
    $this->mockProvider->addItem('vault', 'title', 'content');
    
    // Act: Run the command
    $this->artisan('env:push', ['environment' => 'test'])
        ->expectsOutput('Expected output')
        ->assertSuccessful();
    
    // Assert: Verify the result
    expect($this->mockProvider->hasItem('vault', 'title'))->toBeTrue();
});
```

### Testing Providers

```php
it('handles some scenario', function () {
    $provider = new MockOnePasswordProvider();
    $provider->setAvailable(true);
    $provider->setAuthenticated(true);
    
    $result = $provider->pull(['environment' => 'test']);
    
    expect($result)->toBeString()->toContain('APP_NAME');
});
```

## Architecture Tests

The package includes architecture tests that ensure:
- Contracts are interfaces
- Providers extend BaseProvider
- Commands extend Laravel Command
- No debug functions in production code
- No `env()` calls outside config

## Coverage

To generate a coverage report:

```bash
vendor/bin/pest --coverage

# Generate HTML report
vendor/bin/pest --coverage-html coverage

# Generate coverage for CI
vendor/bin/pest --coverage-clover coverage.xml
```

## Troubleshooting

### Tests Failing Locally

1. Ensure dependencies are installed:
   ```bash
   composer install
   ```

2. Clear any cached test files:
   ```bash
   rm -rf tests/tmp
   ```

3. Check PHP version compatibility:
   ```bash
   php -v  # Should be 8.1 or higher
   ```

### Mock Provider Not Working

1. Ensure the mock is properly registered:
   ```php
   $manager = $this->app->make(ProviderManager::class);
   $manager->register('1password', new MockOnePasswordProvider());
   ```

2. Check that you're using the mock in tests:
   ```php
   beforeEach(function () {
       $this->mockProvider = new MockOnePasswordProvider();
       // Register mock...
   });
   ```

## Best Practices

1. **Always use mocks for external services** - Don't rely on actual CLI tools in tests
2. **Clean up after tests** - Remove temporary files and reset state
3. **Test both success and failure paths** - Use mocks to simulate errors
4. **Keep tests fast** - Use in-memory operations where possible
5. **Write descriptive test names** - Use Pest's `it()` syntax for clarity