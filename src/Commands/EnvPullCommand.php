<?php

namespace Metacomet\EnvSync\Commands;

use Illuminate\Console\Command;
use Metacomet\EnvSync\ProviderManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error as promptError;
use function Laravel\Prompts\info as promptInfo;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class EnvPullCommand extends Command
{
    protected $signature = 'env:pull
                            {environment? : Environment name (local, staging, production, etc.)}
                            {--provider= : Secret provider (1password, aws, bitwarden)}
                            {--force : Force pull even if files are identical}
                            {--vault= : Vault/Organization/Region depending on provider}
                            {--title= : Custom item title/name}';

    protected $description = 'Pull .env file from your secret manager';

    protected ProviderManager $providerManager;

    public function __construct(ProviderManager $providerManager)
    {
        parent::__construct();
        $this->providerManager = $providerManager;
    }

    public function handle(): int
    {
        // Get environment interactively if not provided
        $environment = $this->argument('environment');
        if (! $environment) {
            $environment = select(
                label: 'Which environment would you like to pull?',
                options: [
                    'local' => 'Local Development',
                    'staging' => 'Staging',
                    'production' => 'Production',
                    'testing' => 'Testing',
                    'custom' => 'Custom (enter manually)',
                ],
                default: 'local'
            );

            if ($environment === 'custom') {
                $environment = text(
                    label: 'Enter the environment name:',
                    placeholder: 'e.g., qa, development',
                    required: true
                );
            }
        }

        // Get provider - use default from config if not provided
        $providerName = $this->option('provider');

        if (! $providerName) {
            // If interactive, allow selection
            if (! $this->option('no-interaction')) {
                $availableProviders = $this->providerManager->getAvailableProviders();
                $providerOptions = [];

                foreach ($availableProviders as $name) {
                    $providerOptions[$name] = ucfirst($name);
                }

                $providerName = select(
                    label: 'Which secret manager would you like to use?',
                    options: $providerOptions,
                    default: $this->providerManager->getDefaultProvider()
                );
            } else {
                // Use default provider
                $providerName = null;
            }
        }

        $force = $this->option('force');
        $vault = $this->option('vault');
        $title = $this->option('title');

        try {
            $provider = $this->providerManager->get($providerName);
            $providerConfig = config("env-sync.providers.{$providerName}") ?: config('env-sync.providers.'.$this->providerManager->getDefaultProvider());

            // Check if provider is available
            if (! $provider->isAvailable()) {
                promptError("{$provider->getName()} CLI not installed");
                note($provider->getInstallInstructions());

                return Command::FAILURE;
            }

            // Check if authenticated
            if (! $provider->isAuthenticated()) {
                promptError("Not authenticated with {$provider->getName()}");
                note($provider->getAuthInstructions());

                if (confirm('Would you like to authenticate now?')) {
                    promptInfo('Please run the authentication command shown above, then try again.');
                }

                return Command::FAILURE;
            }

            // Get vault - use config default if not provided
            if (! $vault) {
                // Try to get default vault from config
                $vault = config("env-sync.providers.{$providerName}.vault");

                // If interactive and no vault specified for 1Password, allow input
                if (! $vault && $providerName === '1password' && ! $this->option('no-interaction')) {
                    $vault = text(
                        label: 'Enter the 1Password vault name:',
                        placeholder: 'e.g., Personal, Company',
                        default: config('env-sync.providers.1password.vault', 'Private'),
                        required: false
                    );
                }
            }

            $config = [
                'environment' => $environment,
                'force' => $force,
            ];

            // Add provider-specific options
            if ($vault) {
                $driverType = $providerConfig['driver'] ?? $providerName;
                if ($driverType === 'aws') {
                    $config['region'] = $vault;
                } elseif ($driverType === 'bitwarden') {
                    $config['organizationId'] = $vault;
                } else {
                    $config['vault'] = $vault;
                }
            }

            if ($title) {
                $driverType = $providerConfig['driver'] ?? $providerName;
                if ($driverType === 'aws') {
                    $config['secretName'] = $title;
                } else {
                    $config['title'] = $title;
                }
            }

            // Check if local file exists and handle interactively
            $envFile = $this->getEnvFilePath($environment);
            if (! $force && file_exists($envFile)) {
                warning("Local .env file already exists: {$envFile}");

                try {
                    $comparison = $provider->compare($config);

                    if ($comparison['remoteExists']) {
                        if ($comparison['areIdentical']) {
                            promptInfo('The local and remote files are identical.');

                            if (! confirm('Do you want to pull anyway?', default: false)) {
                                return Command::SUCCESS;
                            }
                            $config['force'] = true;
                        } else {
                            warning('The local and remote files are different.');

                            if (confirm('Would you like to create a backup of the local file?', default: true)) {
                                $backupPath = $envFile.'.backup.'.date('Ymd_His');
                                copy($envFile, $backupPath);
                                promptInfo("Backup created: {$backupPath}");
                            }

                            if (! confirm('Do you want to overwrite the local file?', default: true)) {
                                return Command::FAILURE;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Comparison failed, just proceed
                }
            }

            // Perform the pull with a spinner (skip spinner in testing)
            if (app()->runningUnitTests()) {
                $provider->pull($config);
            } else {
                spin(
                    fn () => $provider->pull($config),
                    "Pulling {$environment} environment from {$provider->getName()}..."
                );
            }

            promptInfo("✓ Successfully pulled .env from {$provider->getName()}");

            // Validate the .env file
            if (filesize($envFile) > 0) {
                $lineCount = count(file($envFile));
                promptInfo("✓ .env file created with {$lineCount} lines");

                // Check for critical Laravel variables
                $this->validateEnvFile($envFile);
            } else {
                warning('.env file appears to be empty');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            promptError($e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function getEnvFilePath(string $environment): string
    {
        if ($environment === 'local' || $environment === 'development') {
            return base_path('.env');
        }

        return base_path(".env.{$environment}");
    }

    protected function validateEnvFile(string $envFile): void
    {
        $criticalVars = ['APP_KEY', 'DB_CONNECTION'];
        $missingVars = [];
        $fileContent = file_get_contents($envFile);

        foreach ($criticalVars as $var) {
            if (! preg_match("/^{$var}=/m", $fileContent)) {
                $missingVars[] = $var;
            }
        }

        if (! empty($missingVars)) {
            warning('The following critical variables might be missing: '.implode(', ', $missingVars));
        }
    }
}
