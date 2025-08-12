<?php

namespace Metacomet\EnvSync\Commands;

use Illuminate\Console\Command;
use Metacomet\EnvSync\ProviderManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error as promptError;
use function Laravel\Prompts\info as promptInfo;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class EnvSyncCommand extends Command
{
    protected $signature = 'env:sync
                            {environment? : Environment name (local, staging, production, etc.)}
                            {--provider= : Secret provider (1password, aws, bitwarden)}
                            {--vault= : Vault/Organization/Region depending on provider}
                            {--title= : Custom item title/name}';

    protected $description = 'Interactive sync utility for .env and secret managers';

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
                label: 'Which environment would you like to sync?',
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

            promptInfo("{$provider->getName()} .env Sync Utility");

            while (true) {
                $config = [
                    'environment' => $environment,
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

                // Check local file
                $envFile = $this->getEnvFilePath($environment);
                $localExists = file_exists($envFile);

                // Check remote
                $remoteExists = $provider->exists($config);

                // Build status table data
                $statusData = [];

                if ($localExists) {
                    $localTime = date('Y-m-d H:i:s', filemtime($envFile));
                    $localLines = count(file($envFile));
                    $statusData[] = ['Local .env', '✓ Found', "{$localLines} lines, modified {$localTime}"];
                } else {
                    $statusData[] = ['Local .env', '✗ Not found', ''];
                }

                if ($remoteExists) {
                    $statusData[] = [$provider->getName(), '✓ Found', ''];
                } else {
                    $statusData[] = [$provider->getName(), '✗ Not found', ''];
                }

                table(['Resource', 'Status', 'Details'], $statusData);

                $actions = [];

                if ($localExists) {
                    $actions['push'] = "Push - Upload local .env to {$provider->getName()}";
                }

                if ($remoteExists) {
                    $actions['pull'] = "Pull - Download .env from {$provider->getName()}";
                }

                if ($localExists && $remoteExists) {
                    $actions['compare'] = "Compare - Show differences between local and {$provider->getName()}";
                }

                $actions['list'] = "List - Show all environments in {$provider->getName()}";
                $actions['refresh'] = 'Refresh - Update status information';
                $actions['exit'] = 'Exit';

                $choice = select(
                    label: 'What would you like to do?',
                    options: $actions,
                    default: 'exit'
                );

                switch ($choice) {
                    case 'push':
                        if (! $localExists) {
                            promptError('Local .env file not found');
                            break;
                        }

                        $this->call('env:push', [
                            'environment' => $environment,
                            '--provider' => $providerName,
                            '--vault' => $vault,
                            '--title' => $title,
                            '--no-interaction' => true,
                        ]);
                        break;

                    case 'pull':
                        if (! $remoteExists) {
                            promptError("{$provider->getName()} item not found");
                            break;
                        }

                        $this->call('env:pull', [
                            'environment' => $environment,
                            '--provider' => $providerName,
                            '--vault' => $vault,
                            '--title' => $title,
                            '--no-interaction' => true,
                        ]);
                        break;

                    case 'compare':
                        if (! $localExists) {
                            promptError('Local .env file not found');
                            break;
                        }
                        if (! $remoteExists) {
                            promptError("{$provider->getName()} item not found");
                            break;
                        }

                        promptInfo("Comparing local and {$provider->getName()} versions...");

                        $compareResult = $provider->compare($config);

                        if ($compareResult['areIdentical']) {
                            promptInfo('✓ Files are identical');
                        } else {
                            warning('Differences found');
                            $this->showDifferences($compareResult['localContent'], $compareResult['remoteContent']);
                        }
                        break;

                    case 'list':
                        promptInfo("Listing all environments in {$provider->getName()}...");

                        $items = $provider->list($config);

                        if (empty($items)) {
                            warning('No environments found');
                        } else {
                            $tableData = [];
                            foreach ($items as $item) {
                                $tableData[] = [
                                    $item['environment'],
                                    $item['title'],
                                    $item['updatedAt'] ?? 'Unknown',
                                ];
                            }

                            table(['Environment', 'Title', 'Last Updated'], $tableData);
                        }
                        break;

                    case 'refresh':
                        // Status refresh happens automatically in the loop
                        continue 2;

                    case 'exit':
                        outro('Goodbye!');

                        return Command::SUCCESS;
                }

                if (! confirm('Continue with another action?', true)) {
                    break;
                }
            }

            outro('Thanks for using env-sync!');

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

    protected function showDifferences(string $localContent, string $remoteContent): void
    {
        // Create temp files for diff
        $tempLocal = tempnam(sys_get_temp_dir(), 'env_local_');
        $tempRemote = tempnam(sys_get_temp_dir(), 'env_remote_');

        file_put_contents($tempLocal, $localContent);
        file_put_contents($tempRemote, $remoteContent);

        // Show diff
        $process = new \Symfony\Component\Process\Process([
            'diff', '-u', $tempLocal, $tempRemote,
            '--label', 'Local .env',
            '--label', 'Remote',
        ]);
        $process->run();

        if ($process->getOutput()) {
            note('(- local, + remote)');
            $this->line($process->getOutput());
        }

        unlink($tempLocal);
        unlink($tempRemote);
    }
}
