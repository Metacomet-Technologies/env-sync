<?php

namespace Metacomet\EnvSync\Providers;

use Exception;
use Symfony\Component\Process\Process;

class OnePasswordProvider extends BaseProvider
{
    public function getName(): string
    {
        return '1Password';
    }

    public function isAvailable(): bool
    {
        $process = $this->runProcess(['which', 'op']);

        return $process->isSuccessful();
    }

    public function isAuthenticated(): bool
    {
        $process = $this->runProcess(['op', 'account', 'list']);

        return $process->isSuccessful();
    }

    public function push(array $config): void
    {
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $force = $config['force'] ?? false;
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        $envFile = $this->getEnvFilePath($environment);

        if (! file_exists($envFile)) {
            throw new Exception("Environment file not found: {$envFile}");
        }

        // Check if item already exists
        $itemId = $this->getItemId($vault, $title);

        $envContent = file_get_contents($envFile);
        $envBase64 = $this->encodeContent($envContent);

        if ($itemId) {
            // Check if content is identical
            if (! $force) {
                $existingContent = $this->getItemContent($itemId);
                if ($existingContent === $envContent) {
                    throw new Exception('Files are identical - no push needed. Use --force to push anyway.');
                }
            }

            // Update existing item by recreating it
            // 1Password CLI v2.x has issues with updating items, so we delete and recreate
            // First, we backup the existing item in case recreation fails

            // Get the existing item as backup
            $backupProcess = new Process(['op', 'item', 'get', $itemId, '--format', 'json']);
            $backupProcess->run();

            if (! $backupProcess->isSuccessful()) {
                throw new Exception('Failed to backup existing item from 1Password: '.$backupProcess->getErrorOutput());
            }

            $backupData = $backupProcess->getOutput();
            $backupItem = json_decode($backupData, true);

            if (! $backupItem) {
                throw new Exception('Failed to parse backup item data from 1Password');
            }

            // Delete the existing item
            $deleteProcess = new Process(['op', 'item', 'delete', $itemId, '--vault', $vault]);
            $deleteProcess->run();

            if (! $deleteProcess->isSuccessful()) {
                throw new Exception('Failed to delete existing item from 1Password: '.$deleteProcess->getErrorOutput());
            }

            // Create new item with updated content
            $itemJson = json_encode([
                'category' => 'SECURE_NOTE',
                'title' => $title,
                'vault' => ['name' => $vault],
                'fields' => [
                    [
                        'id' => 'notesPlain',
                        'type' => 'STRING',
                        'purpose' => 'NOTES',
                        'label' => 'notesPlain',
                        'value' => $envBase64,
                    ],
                ],
                'tags' => ['env', 'laravel', 'development', 'base64'],
            ]);

            $process = new Process(['op', 'item', 'create', '-']);
            $process->setInput($itemJson);
            $process->run();

            // If creation fails, attempt to restore the backup
            if (! $process->isSuccessful()) {
                $restoreError = $process->getErrorOutput();

                // Attempt to restore the original item
                $restoreJson = json_encode([
                    'category' => $backupItem['category'] ?? 'SECURE_NOTE',
                    'title' => $backupItem['title'],
                    'vault' => ['name' => $vault],
                    'fields' => $backupItem['fields'] ?? [],
                    'tags' => $backupItem['tags'] ?? [],
                ]);

                $restoreProcess = new Process(['op', 'item', 'create', '-']);
                $restoreProcess->setInput($restoreJson);
                $restoreProcess->run();

                if (! $restoreProcess->isSuccessful()) {
                    // Save backup to file as last resort
                    $backupFile = sys_get_temp_dir()."/1password_backup_{$itemId}_".time().'.json';
                    file_put_contents($backupFile, $backupData);

                    throw new Exception(
                        'CRITICAL: Failed to recreate item AND failed to restore backup. '.
                        "Original error: {$restoreError}. ".
                        "Restore error: {$restoreProcess->getErrorOutput()}. ".
                        "Backup data saved to: {$backupFile}"
                    );
                }

                throw new Exception(
                    "Failed to update item in 1Password (original has been restored): {$restoreError}"
                );
            }
        } else {
            // Create new item
            $itemJson = json_encode([
                'category' => 'SECURE_NOTE',
                'title' => $title,
                'vault' => ['name' => $vault],
                'fields' => [
                    [
                        'id' => 'notesPlain',
                        'type' => 'STRING',
                        'purpose' => 'NOTES',
                        'label' => 'notesPlain',
                        'value' => $envBase64,
                    ],
                ],
                'tags' => ['env', 'laravel', 'development', 'base64'],
            ]);

            $process = new Process(['op', 'item', 'create', '-']);
            $process->setInput($itemJson);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new Exception('Failed to create item in 1Password: '.$process->getErrorOutput());
            }
        }
    }

    public function pull(array $config): string
    {
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $force = $config['force'] ?? false;
        $skipWrite = $config['skipWrite'] ?? false;
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        $itemId = $this->getItemId($vault, $title);

        if (! $itemId) {
            throw new Exception("Item '{$title}' not found in vault '{$vault}'");
        }

        $envContent = $this->getItemContent($itemId);

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
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        return $this->getItemId($vault, $title) !== null;
    }

    public function list(array $config): array
    {
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $gitInfo = $this->getGitInfo();

        $process = $this->runProcess(['op', 'item', 'list', '--vault', $vault, '--format', 'json']);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed to list items from vault: '.$vault);
        }

        $items = json_decode($process->getOutput(), true) ?? [];
        $envItems = [];

        foreach ($items as $item) {
            // Filter items related to this project
            if (isset($gitInfo['repo']) && str_contains($item['title'], $gitInfo['repo'])) {
                // Extract environment from title
                if (preg_match('/\/([^\/]+)\/\.env$/', $item['title'], $matches)) {
                    $envItems[] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'environment' => $matches[1],
                        'updatedAt' => $item['updated_at'] ?? null,
                        'vault' => $vault,
                    ];
                }
            }
        }

        return $envItems;
    }

    public function delete(array $config): void
    {
        $environment = $config['environment'] ?? 'local';
        $vault = $config['vault'] ?? 'Metacomet Technologies, LLC';
        $title = $this->generateTitle($environment, $config['title'] ?? null);

        $itemId = $this->getItemId($vault, $title);

        if (! $itemId) {
            throw new Exception("Item '{$title}' not found in vault '{$vault}'");
        }

        $process = $this->runProcess(['op', 'item', 'delete', $itemId, '--vault', $vault]);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed to delete item from 1Password: '.$process->getErrorOutput());
        }
    }

    public function getAuthInstructions(): string
    {
        return 'Run: eval $(op signin)';
    }

    public function getInstallInstructions(): string
    {
        return <<<'EOT'
macOS: brew install --cask 1password-cli
Other: https://developer.1password.com/docs/cli/get-started/
EOT;
    }

    private function getItemId(string $vault, string $title): ?string
    {
        $process = $this->runProcess(['op', 'item', 'list', '--vault', $vault, '--format', 'json']);

        if (! $process->isSuccessful()) {
            return null;
        }

        $items = json_decode($process->getOutput(), true) ?? [];

        foreach ($items as $item) {
            if ($item['title'] === $title) {
                return $item['id'];
            }
        }

        return null;
    }

    private function getItemContent(string $itemId): string
    {
        $process = $this->runProcess(['op', 'item', 'get', $itemId, '--fields', 'notesPlain']);

        if (! $process->isSuccessful()) {
            throw new Exception('Unable to retrieve content from 1Password');
        }

        return $this->decodeContent($process->getOutput());
    }
}
