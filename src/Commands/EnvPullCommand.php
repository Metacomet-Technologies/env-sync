<?php

namespace Metacomet\EnvSync\Commands;

use Illuminate\Console\Command;
use Metacomet\EnvSync\ProviderManager;

class EnvPullCommand extends Command
{
    protected $signature = 'env:pull
                            {environment=local : Environment name (local, staging, production, etc.)}
                            {--provider=1password : Secret provider (1password, aws, bitwarden)}
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
        $environment = $this->argument('environment');
        $providerKey = $this->option('provider');
        $force = $this->option('force');
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

            $this->info("Pulling {$environment} environment from {$provider->getName()}...");

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

            $provider->pull($config);

            $this->info("✓ Successfully pulled .env from {$provider->getName()}");

            // Validate the .env file
            $envFile = $this->getEnvFilePath($environment);
            if (filesize($envFile) > 0) {
                $lineCount = count(file($envFile));
                $this->info("✓ .env file created with {$lineCount} lines");

                // Check for critical Laravel variables
                $this->validateEnvFile($envFile);
            } else {
                $this->warn('.env file appears to be empty');
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

    protected function validateEnvFile(string $envFile): void
    {
        $criticalVars = ['APP_KEY', 'DB_CONNECTION'];
        $missingVars = [];
        $fileContent = file_get_contents($envFile);

        foreach ($criticalVars as $var) {
            if (!preg_match("/^{$var}=/m", $fileContent)) {
                $missingVars[] = $var;
            }
        }

        if (!empty($missingVars)) {
            $this->warn('The following critical variables might be missing:');
            foreach ($missingVars as $var) {
                $this->line("  - {$var}");
            }
        }
    }
}