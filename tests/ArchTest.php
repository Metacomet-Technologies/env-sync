<?php

arch('contracts are interfaces')
    ->expect('Metacomet\EnvSync\Contracts')
    ->toBeInterfaces();

arch('providers extend base provider')
    ->expect('Metacomet\EnvSync\Providers')
    ->classes()
    ->toExtend('Metacomet\EnvSync\Providers\BaseProvider')
    ->ignoring('Metacomet\EnvSync\Providers\BaseProvider');

arch('providers implement secret provider interface')
    ->expect('Metacomet\EnvSync\Providers')
    ->classes()
    ->toImplement('Metacomet\EnvSync\Contracts\SecretProvider')
    ->ignoring('Metacomet\EnvSync\Providers\BaseProvider');

arch('commands extend laravel command')
    ->expect('Metacomet\EnvSync\Commands')
    ->toExtend('Illuminate\Console\Command');

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('providers have required methods')
    ->expect('Metacomet\EnvSync\Providers')
    ->classes()
    ->toHaveMethod('getName')
    ->toHaveMethod('isAvailable')
    ->toHaveMethod('isAuthenticated')
    ->toHaveMethod('push')
    ->toHaveMethod('pull')
    ->toHaveMethod('exists')
    ->toHaveMethod('list')
    ->toHaveMethod('delete')
    ->toHaveMethod('getAuthInstructions')
    ->toHaveMethod('getInstallInstructions');

arch('no env calls in source code')
    ->expect('env')
    ->not->toBeUsed();
