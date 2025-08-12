<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default secret provider that will be used
    | when you don't specify a provider explicitly. Supported providers:
    | "1password", "aws", "bitwarden"
    |
    */
    'default' => env('ENV_SYNC_PROVIDER', '1password'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure settings for each provider. These settings
    | will be used as defaults when using the respective provider.
    |
    */
    'providers' => [
        '1password' => [
            'vault' => env('ONEPASSWORD_VAULT', 'Private'),
        ],

        // Future providers (roadmap):
        // 'aws' => [
        //     'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        //     'profile' => env('AWS_PROFILE'),
        // ],
        // 'bitwarden' => [
        //     'organization_id' => env('BITWARDEN_ORG_ID'),
        //     'server' => env('BITWARDEN_SERVER'),
        // ],
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
        'max_backups' => 5, // Maximum number of backups to keep
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
