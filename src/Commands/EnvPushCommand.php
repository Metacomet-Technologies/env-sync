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
use function Laravel\Prompts\table as promptTable;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class EnvPushCommand extends Command
{
    protected $signature = 'env:push
                            {environment? : Environment name (local, staging, production, etc.)}
                            {--provider= : Secret provider (1password, aws, bitwarden)}
                            {--force : Force push even if files are identical}
                            {--vault= : Override vault/organization/region from config}
                            {--title= : Custom item title/name}';

    protected $description = 'Push .env file to your secret manager';

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
                label: 'Which environment would you like to push?',
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

            // Get vault - use provider config if not overridden
            if (! $vault && $providerConfig) {
                $vault = $providerConfig['vault'] ??
                         $providerConfig['region'] ??
                         $providerConfig['organization_id'] ?? null;
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

            // Check if item exists and handle interactively
            if (! $force) {
                $exists = $provider->exists($config);

                if ($exists) {
                    warning('An item already exists for this environment.');

                    try {
                        $comparison = $provider->compare($config);

                        if ($comparison['areIdentical']) {
                            promptInfo('The local and remote files are identical.');

                            if (! confirm('Do you want to push anyway?', default: false)) {
                                return Command::SUCCESS;
                            }
                            $config['force'] = true;
                        } else {
                            warning('The local and remote files are different.');

                            if (! confirm('Do you want to overwrite the remote version?', default: true)) {
                                return Command::FAILURE;
                            }
                        }
                    } catch (\Exception $e) {
                        // Comparison failed, just proceed
                    }
                }
            }

            // Perform the push with a spinner (skip spinner in testing)
            if (app()->runningUnitTests()) {
                $provider->push($config);
            } else {
                spin(
                    fn () => $provider->push($config),
                    "Pushing {$environment} environment to {$provider->getName()}..."
                );
            }

            promptInfo("✓ Successfully pushed .env to {$provider->getName()}");

            // Display summary
            promptTable(
                ['Property', 'Value'],
                [
                    ['Provider', $provider->getName()],
                    ['Environment', $environment],
                    ['Last synced', now()->toIso8601String()],
                    ['From host', gethostname()],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            promptError($e->getMessage());

            return Command::FAILURE;
        }
    }
}
