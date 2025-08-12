<?php

use Metacomet\EnvSync\Contracts\SecretProvider;
use Metacomet\EnvSync\ProviderManager;
use Metacomet\EnvSync\Providers\OnePasswordProvider;

beforeEach(function () {
    $this->manager = new ProviderManager;
});

it('has default providers registered', function () {
    $providers = $this->manager->all();

    expect($providers)
        ->toBeArray()
        ->toHaveKeys(['1password', 'aws', 'bitwarden']);
});

it('gets the correct provider', function () {
    $provider = $this->manager->get('1password');

    expect($provider)
        ->toBeInstanceOf(SecretProvider::class)
        ->toBeInstanceOf(OnePasswordProvider::class)
        ->and($provider->getName())->toBe('1Password');
});

it('throws exception for unknown provider', function () {
    expect(fn () => $this->manager->get('unknown'))
        ->toThrow(Exception::class, "Provider 'unknown' not found");
});

it('registers new provider successfully', function () {
    $mockProvider = Mockery::mock(SecretProvider::class);
    $mockProvider->shouldReceive('getName')->andReturn('Custom Provider');

    $this->manager->register('custom', $mockProvider);

    expect($this->manager->hasProvider('custom'))->toBeTrue()
        ->and($this->manager->get('custom'))->toBe($mockProvider);
});

it('correctly checks if provider exists', function () {
    expect($this->manager->hasProvider('1password'))->toBeTrue()
        ->and($this->manager->hasProvider('aws'))->toBeTrue()
        ->and($this->manager->hasProvider('bitwarden'))->toBeTrue()
        ->and($this->manager->hasProvider('nonexistent'))->toBeFalse();
});

describe('Provider filtering', function () {
    it('filters available providers', function () {
        $availableProvider = Mockery::mock(SecretProvider::class);
        $availableProvider->shouldReceive('isAvailable')->andReturn(true);

        $unavailableProvider = Mockery::mock(SecretProvider::class);
        $unavailableProvider->shouldReceive('isAvailable')->andReturn(false);

        $manager = new ProviderManager;
        $manager->register('available', $availableProvider);
        $manager->register('unavailable', $unavailableProvider);

        $available = $manager->getAvailableProviders();

        expect($available)
            ->toHaveKey('available')
            ->not->toHaveKey('unavailable');
    });

    it('filters authenticated providers', function () {
        $authenticatedProvider = Mockery::mock(SecretProvider::class);
        $authenticatedProvider->shouldReceive('isAvailable')->andReturn(true);
        $authenticatedProvider->shouldReceive('isAuthenticated')->andReturn(true);

        $unauthenticatedProvider = Mockery::mock(SecretProvider::class);
        $unauthenticatedProvider->shouldReceive('isAvailable')->andReturn(true);
        $unauthenticatedProvider->shouldReceive('isAuthenticated')->andReturn(false);

        $unavailableProvider = Mockery::mock(SecretProvider::class);
        $unavailableProvider->shouldReceive('isAvailable')->andReturn(false);
        $unavailableProvider->shouldReceive('isAuthenticated')->andReturn(true);

        $manager = new ProviderManager;
        $manager->register('authenticated', $authenticatedProvider);
        $manager->register('unauthenticated', $unauthenticatedProvider);
        $manager->register('unavailable', $unavailableProvider);

        $authenticated = $manager->getAuthenticatedProviders();

        expect($authenticated)
            ->toHaveKey('authenticated')
            ->not->toHaveKey('unauthenticated')
            ->not->toHaveKey('unavailable');
    });
});

it('returns all registered providers', function () {
    $mockProvider1 = Mockery::mock(SecretProvider::class);
    $mockProvider2 = Mockery::mock(SecretProvider::class);

    $manager = new ProviderManager;
    $manager->register('test1', $mockProvider1);
    $manager->register('test2', $mockProvider2);

    $all = $manager->all();

    expect($all)
        ->toHaveKeys(['test1', 'test2', '1password', 'aws', 'bitwarden']);
});
