<?php

namespace Metacomet\EnvSync\Commands;

use Illuminate\Console\Command;
use Metacomet\EnvSync\ProviderManager;

class EnvSyncCommand extends Command
{
    protected $signature = 'env:sync
                            {environment=local : Environment name (local, staging, production, etc.)}
                            {--provider=1password : Secret provider (1password, aws, bitwarden)}
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
        $environment = $this->argument('environment');
        $providerKey = $this->option('provider');
        $vault = $this->option('vault');
        $title = $this->option('title');

        try {
            $provider = $this->providerManager->get($providerKey);

            // Check if provider is available
            if (!$provider->isAvailable()) {
                $this->error("{$provider->getName()} CLI not installed");
                $this->line($provider->getInstallInstructions());
                return Command::FAILURE;
            }

            // Check if authenticated
            if (!$provider->isAuthenticated()) {
                $this->error("Not authenticated with {$provider->getName()}");
                $this->line($provider->getAuthInstructions());
                return Command::FAILURE;
            }

            // Display header
            $this->newLine();
            $this->info('================================');
            $this->info("  {$provider->getName()} .env Sync Utility");
            $this->info('================================');
            $this->newLine();

            while (true) {
                // Check current status
                $this->info('Current Status:');
                $this->line('-------------------');

                $config = [
                    'environment' => $environment,
                ];

                // Add provider-specific options
                if ($vault) {
                    if ($providerKey === 'aws') {
                        $config['region'] = $vault;
                    } elseif ($providerKey === 'bitwarden') {
                        $config['organizationId'] = $vault;
                    } else {
                        $config['vault'] = $vault;
                    }
                }

                if ($title) {
                    if ($providerKey === 'aws') {
                        $config['secretName'] = $title;
                    } else {
                        $config['title'] = $title;
                    }
                }

                // Check local file
                $envFile = $this->getEnvFilePath($environment);
                $localExists = file_exists($envFile);

                if ($localExists) {
                    $localTime = date('Y-m-d H:i:s', filemtime($envFile));
                    $localLines = count(file($envFile));

                    $this->line('Local .env: <fg=green>✓ Found</>');
                    $this->line("  Modified: <fg=yellow>{$localTime}</>");
                    $this->line("  Lines: <fg=yellow>{$localLines}</>");
                } else {
                    $this->line('Local .env: <fg=red>✗ Not found</>');
                }

                // Check remote
                $remoteExists = $provider->exists($config);

                if ($remoteExists) {
                    $this->line("{$provider->getName()}: <fg=green>✓ Found</>");
                } else {
                    $this->line("{$provider->getName()}: <fg=red>✗ Not found</>");
                }

                $this->newLine();
                $this->info('Available Actions:');
                $this->line('-------------------');

                $choices = [
                    '1' => "Push - Upload local .env to {$provider->getName()}",
                    '2' => "Pull - Download .env from {$provider->getName()}",
                    '3' => "Compare - Show differences between local and {$provider->getName()}",
                    '4' => 'Status - Refresh status information',
                    '5' => 'List - Show all environments in provider',
                    '6' => 'Exit',
                ];

                foreach ($choices as $key => $description) {
                    $this->line("<fg=cyan>{$key})</> {$description}");
                }

                $this->newLine();
                $choice = $this->ask('Select action (1-6)');

                switch ($choice) {
                    case '1':
                        $this->newLine();
                        if (!$localExists) {
                            $this->error('Error: Local .env file not found');
                            break;
                        }
                        $this->info("Pushing .env to {$provider->getName()}...");
                        $this->call('env:push', [
                            'environment' => $environment,
                            '--provider' => $providerKey,
                            '--vault' => $vault,
                            '--title' => $title,
                        ]);
                        break;

                    case '2':
                        $this->newLine();
                        $this->info("Pulling .env from {$provider->getName()}...");
                        $this->call('env:pull', [
                            'environment' => $environment,
                            '--provider' => $providerKey,
                            '--vault' => $vault,
                            '--title' => $title,
                        ]);
                        break;

                    case '3':
                        $this->newLine();
                        if (!$localExists) {
                            $this->error('Error: Local .env file not found');
                            break;
                        }
                        if (!$remoteExists) {
                            $this->error("Error: {$provider->getName()} item not found");
                            break;
                        }

                        $this->info("Comparing local and {$provider->getName()} versions...");

                        $compareResult = $provider->compare($config);

                        if ($compareResult['areIdentical']) {
                            $this->info('✓ Files are identical');
                        } else {
                            $this->warn('Differences found:');
                            $this->showDifferences($compareResult['localContent'], $compareResult['remoteContent']);
                        }
                        break;

                    case '4':
                        $this->newLine();
                        // Status refresh happens automatically in the loop
                        continue 2;

                    case '5':
                        $this->newLine();
                        $this->info("Listing all environments in {$provider->getName()}...");
                        
                        $items = $provider->list($config);
                        
                        if (empty($items)) {
                            $this->warn('No environments found');
                        } else {
                            $tableData = [];
                            foreach ($items as $item) {
                                $tableData[] = [
                                    $item['environment'],
                                    $item['title'],
                                    $item['updatedAt'] ?? 'Unknown',
                                ];
                            }
                            
                            $this->table(['Environment', 'Title', 'Last Updated'], $tableData);
                        }
                        break;

                    case '6':
                        $this->info('Goodbye!');
                        return Command::SUCCESS;

                    default:
                        $this->error('Invalid choice');
                        break;
                }

                $this->newLine();
                if (!$this->confirm('Continue with another action?', true)) {
                    break;
                }
                $this->newLine();
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
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
            $this->line('<fg=cyan>(- local, + remote)</>');
            $this->newLine();
            $this->line($process->getOutput());
        }

        unlink($tempLocal);
        unlink($tempRemote);
    }
}