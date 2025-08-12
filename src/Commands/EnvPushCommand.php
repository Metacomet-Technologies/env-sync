<?php

namespace Metacomet\EnvSync\Commands;

use Illuminate\Console\Command;
use Metacomet\EnvSync\ProviderManager;

class EnvPushCommand extends Command
{
    protected $signature = 'env:push
                            {environment=local : Environment name (local, staging, production, etc.)}
                            {--provider=1password : Secret provider (1password, aws, bitwarden)}
                            {--force : Force push even if files are identical}
                            {--vault= : Vault/Organization/Region depending on provider}
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
        $environment = $this->argument('environment');
        $providerKey = $this->option('provider');
        $force = $this->option('force');
        $vault = $this->option('vault');
        $title = $this->option('title');

        try {
            $provider = $this->providerManager->get($providerKey);

            // Check if provider is available
            if (! $provider->isAvailable()) {
                $this->error("{$provider->getName()} CLI not installed");
                $this->line($provider->getInstallInstructions());

                return Command::FAILURE;
            }

            // Check if authenticated
            if (! $provider->isAuthenticated()) {
                $this->error("Not authenticated with {$provider->getName()}");
                $this->line($provider->getAuthInstructions());

                return Command::FAILURE;
            }

            $this->info("Pushing {$environment} environment to {$provider->getName()}...");

            $config = [
                'environment' => $environment,
                'force' => $force,
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

            $provider->push($config);

            $this->info("✓ Successfully pushed .env to {$provider->getName()}");

            // Display summary
            $this->newLine();
            $this->table(
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
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
