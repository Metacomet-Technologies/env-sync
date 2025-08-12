<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Secret Manager Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default secret manager provider that will be
    | used when you don't specify a provider explicitly. You may set this to
    | any of the providers defined in the "providers" array below.
    |
    */
    'default' => env('ENV_SYNC_PROVIDER', '1password'),

    /*
    |--------------------------------------------------------------------------
    | Secret Manager Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure settings for each provider. These settings
    | will be used as defaults when using the respective provider.
    |
    | Available drivers: 1password, aws, bitwarden
    |
    */
    'providers' => [

        '1password' => [
            'driver' => '1password',
            'vault' => env('ONEPASSWORD_VAULT', 'Private'),
        ],

        'aws' => [
            'driver' => 'aws',
            'region' => env('ENV_SYNC_AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'profile' => env('AWS_PROFILE'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('AWS_SECRET_PREFIX', ''),
        ],

        'bitwarden' => [
            'driver' => 'bitwarden',
            'organization_id' => env('BITWARDEN_ORG_ID'),
            'server' => env('BITWARDEN_SERVER', 'https://vault.bitwarden.com'),
            'collection_id' => env('BITWARDEN_COLLECTION_ID'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | Define the environments that should be recognized by the package.
    | Each environment maps to a specific .env file pattern.
    |
    */
    'environments' => [
        'local' => '.env',
        'development' => '.env',
        'staging' => '.env.staging',
        'production' => '.env.production',
        'testing' => '.env.testing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure backup behavior when pulling environment files.
    |
    */
    'backup' => [
        'enabled' => true,
        'max_backups' => 5,  // Maximum number of backups to keep
        'directory' => null, // null means same directory as .env file
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define which environment variables are required for your application.
    | These will be checked after pulling from the secret manager.
    |
    */
    'required_variables' => [
        'APP_NAME',
        'APP_ENV',
        'APP_KEY',
        'APP_DEBUG',
        'APP_URL',
        'DB_CONNECTION',
    ],
];
