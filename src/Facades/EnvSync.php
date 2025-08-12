<?php

namespace Metacomet\EnvSync\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Metacomet\EnvSync\EnvSync
 */
class EnvSync extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Metacomet\EnvSync\EnvSync::class;
    }
}
