<?php

namespace Metacomet\EnvSync\Providers;

use Exception;

class AwsSecretsManagerProvider extends BaseProvider
{
    protected ?object $client = null;

    public function getName(): string
    {
        return 'AWS Secrets Manager';
    }

    public function isAvailable(): bool
    {
        // Check if AWS SDK is available
        if (! class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            // Check if AWS CLI is available as fallback
            $process = $this->runProcess(['which', 'aws']);

            return $process->isSuccessful();
        }

        return true;
    }

    public function isAuthenticated(): bool
    {
        $this->checkAwsSdkInstalled();

        try {
            $client = $this->getClient($this->config ?? []);
            // Try to list secrets (with a limit of 1) to verify authentication
            $client->listSecrets(['MaxResults' => 1]);

            return true;
        } catch (\Throwable $e) {
            // Check if it's an AWS exception and an authentication error
            if (method_exists($e, 'getAwsErrorCode')) {
                $errorCode = $e->getAwsErrorCode();
                if (in_array($errorCode, ['UnrecognizedClientException', 'InvalidUserPool.NotFound', 'AccessDeniedException'])) {
                    return false;
                }

                // For other AWS errors, we consider authenticated but may have other issues
                return true;
            }

            return false;
        }
    }

    public function push(array $config): void
    {
        // Check AWS SDK first
        $this->checkAwsSdkInstalled();

        $environment = $config['environment'] ?? 'local';
        $force = $config['force'] ?? false;
        $secretName = $this->generateSecretName($environment, $config['secretName'] ?? null);
        $description = $config['description'] ?? "Environment variables for {$environment} environment";

        $envFile = $this->getEnvFilePath($environment);

        if (! file_exists($envFile)) {
            throw new Exception("Environment file not found: {$envFile}");
        }

        $envContent = file_get_contents($envFile);
        $envBase64 = $this->encodeContent($envContent);
        $client = $this->getClient($config);

        // Check if secret exists
        $exists = $this->secretExists($client, $secretName);

        if ($exists) {
            if (! $force) {
                // Get current secret value
                $currentContent = $this->getSecretValue($client, $secretName);
                if ($currentContent === $envContent) {
                    throw new Exception('Files are identical - no push needed. Use --force to push anyway.');
                }
            }

            // Update existing secret
            try {
                $client->updateSecret([
                    'SecretId' => $secretName,
                    'SecretString' => $envBase64,
                    'Description' => $description,
                ]);
            } catch (\Throwable $e) {
                throw new Exception('Failed to update secret in AWS: '.$e->getMessage());
            }
        } else {
            // Create new secret
            try {
                $client->createSecret([
                    'Name' => $secretName,
                    'Description' => $description,
                    'SecretString' => $envBase64,
                    'Tags' => [
                        ['Key' => 'Environment', 'Value' => $environment],
                        ['Key' => 'Type', 'Value' => 'env'],
                        ['Key' => 'Format', 'Value' => 'base64'],
                        ['Key' => 'ManagedBy', 'Value' => 'laravel-env-sync'],
                    ],
                ]);
            } catch (\Throwable $e) {
                throw new Exception('Failed to create secret in AWS: '.$e->getMessage());
            }
        }
    }

    public function pull(array $config): string
    {
        // Check AWS SDK first
        $this->checkAwsSdkInstalled();

        $environment = $config['environment'] ?? 'local';
        $force = $config['force'] ?? false;
        $skipWrite = $config['skipWrite'] ?? false;
        $secretName = $this->generateSecretName($environment, $config['secretName'] ?? null);

        $client = $this->getClient($config);
        $envContent = $this->getSecretValue($client, $secretName);

        if (! $skipWrite) {
            $envFile = $this->getEnvFilePath($environment);

            if (file_exists($envFile)) {
                $localContent = file_get_contents($envFile);

                if ($localContent === $envContent && ! $force) {
                    throw new Exception('Files are identical - no pull needed. Use --force to pull anyway.');
                }

                if ($localContent !== $envContent || $force) {
                    $this->createBackup($envFile);
                }
            }

            file_put_contents($envFile, $envContent);
        }

        return $envContent;
    }

    public function exists(array $config): bool
    {
        // Check AWS SDK first
        $this->checkAwsSdkInstalled();

        $environment = $config['environment'] ?? 'local';
        $secretName = $this->generateSecretName($environment, $config['secretName'] ?? null);

        $client = $this->getClient($config);

        return $this->secretExists($client, $secretName);
    }

    public function list(array $config): array
    {
        // Check AWS SDK first
        $this->checkAwsSdkInstalled();

        $gitInfo = $this->getGitInfo();
        $prefix = $config['prefix'] ?? '';

        // Use git info for filtering if no prefix specified
        if (empty($prefix) && isset($gitInfo['repo'])) {
            $prefix = $gitInfo['repo'];
        }

        $client = $this->getClient($config);
        $envItems = [];
        $nextToken = null;

        try {
            do {
                $params = ['MaxResults' => 100];
                if ($nextToken) {
                    $params['NextToken'] = $nextToken;
                }

                $result = $client->listSecrets($params);
                $secrets = $result['SecretList'] ?? [];

                foreach ($secrets as $secret) {
                    // Filter secrets related to this project
                    if (empty($prefix) || str_contains($secret['Name'], $prefix)) {
                        // Extract environment from name pattern
                        if (preg_match('/\/([^\/]+)$/', $secret['Name'], $matches)) {
                            $envItems[] = [
                                'id' => $secret['ARN'],
                                'title' => $secret['Name'],
                                'environment' => $matches[1],
                                'updatedAt' => $secret['LastChangedDate'] ?? null,
                                'region' => $config['region'] ?? 'us-east-1',
                            ];
                        }
                    }
                }

                $nextToken = $result['NextToken'] ?? null;
            } while ($nextToken);
        } catch (\Throwable $e) {
            throw new Exception('Failed to list secrets: '.$e->getMessage());
        }

        return $envItems;
    }

    public function delete(array $config): void
    {
        // Check AWS SDK first
        $this->checkAwsSdkInstalled();

        $environment = $config['environment'] ?? 'local';
        $secretName = $this->generateSecretName($environment, $config['secretName'] ?? null);
        $forceDelete = $config['forceDelete'] ?? false;

        $client = $this->getClient($config);

        try {
            $params = ['SecretId' => $secretName];

            if ($forceDelete) {
                // Delete immediately without recovery window
                $params['ForceDeleteWithoutRecovery'] = true;
            } else {
                // Schedule deletion with 30-day recovery window
                $params['RecoveryWindowInDays'] = 30;
            }

            $client->deleteSecret($params);
        } catch (\Throwable $e) {
            throw new Exception('Failed to delete secret: '.$e->getMessage());
        }
    }

    public function getAuthInstructions(): string
    {
        return <<<'EOT'
Configure AWS credentials using one of:
- aws configure
- Export AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY
- Use AWS SSO: aws sso login
EOT;
    }

    public function getInstallInstructions(): string
    {
        return <<<'EOT'
macOS: brew install awscli
Other: https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html
EOT;
    }

    protected function checkAwsSdkInstalled(): void
    {
        if (! class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            throw new Exception(
                "AWS SDK for PHP is not installed. Please install it to use the AWS Secrets Manager provider:\n".
                "composer require aws/aws-sdk-php\n\n".
                'For more information, see: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html'
            );
        }
    }

    protected function getClient(array $config): object
    {
        $this->checkAwsSdkInstalled();

        if ($this->client === null) {
            $awsConfig = [
                'version' => 'latest',
                'region' => $config['region'] ?? 'us-east-1',
            ];

            // Use profile if specified
            if (! empty($config['profile'])) {
                $awsConfig['profile'] = $config['profile'];
            }
            // Or use explicit credentials if provided
            elseif (! empty($config['key']) && ! empty($config['secret'])) {
                $awsConfig['credentials'] = [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ];

                if (! empty($config['token'])) {
                    $awsConfig['credentials']['token'] = $config['token'];
                }
            }
            // Otherwise rely on default credential chain (IAM role, env vars, etc.)

            $className = '\Aws\SecretsManager\SecretsManagerClient';
            $this->client = new $className($awsConfig);
        }

        return $this->client;
    }

    private function generateSecretName(string $environment, ?string $customName = null): string
    {
        if ($customName) {
            return $customName;
        }

        $prefix = $this->config['prefix'] ?? '';
        $gitInfo = $this->getGitInfo();

        // Build the secret name
        $parts = [];

        if (! empty($prefix)) {
            $parts[] = rtrim($prefix, '/');
        }

        if (isset($gitInfo['org']) && isset($gitInfo['repo'])) {
            $parts[] = $gitInfo['org'];
            $parts[] = $gitInfo['repo'];
        } elseif (isset($gitInfo['repo'])) {
            $parts[] = $gitInfo['repo'];
        } else {
            $parts[] = 'env';
        }

        $parts[] = $environment;

        return implode('/', $parts);
    }

    private function secretExists(object $client, string $secretName): bool
    {
        try {
            $client->describeSecret(['SecretId' => $secretName]);

            return true;
        } catch (\Throwable $e) {
            if (method_exists($e, 'getAwsErrorCode') && $e->getAwsErrorCode() === 'ResourceNotFoundException') {
                return false;
            }
            // Re-throw other exceptions
            throw $e;
        }
    }

    private function getSecretValue(object $client, string $secretName): string
    {
        try {
            $result = $client->getSecretValue(['SecretId' => $secretName]);
            $secretString = $result['SecretString'] ?? '';

            // Decode if it's base64 encoded
            return $this->decodeContent($secretString);
        } catch (\Throwable $e) {
            if (method_exists($e, 'getAwsErrorCode') && $e->getAwsErrorCode() === 'ResourceNotFoundException') {
                throw new Exception("Secret not found: {$secretName}");
            }
            throw new Exception('Unable to retrieve secret from AWS: '.$e->getMessage());
        }
    }
}
