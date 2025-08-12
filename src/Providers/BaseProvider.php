<?php

namespace Metacomet\EnvSync\Providers;

use Metacomet\EnvSync\Contracts\SecretProvider;
use Symfony\Component\Process\Process;

abstract class BaseProvider implements SecretProvider
{
    protected function getGitInfo(): array
    {
        try {
            $process = new Process(['git', 'config', '--get', 'remote.origin.url']);
            $process->run();

            if (!$process->isSuccessful()) {
                return ['repo' => basename(getcwd())];
            }

            $remoteUrl = trim($process->getOutput());

            // Extract organization/repo from various Git URL formats
            if (preg_match('/github\.com[:\/]([^\/]+)\/([^\.]+)/', $remoteUrl, $matches)) {
                return [
                    'org' => $matches[1],
                    'repo' => str_replace('.git', '', $matches[2]),
                ];
            }

            if (preg_match('/([^\/]+)\/([^\.]+)\.git$/', $remoteUrl, $matches)) {
                return [
                    'org' => $matches[1],
                    'repo' => $matches[2],
                ];
            }

            // Fallback to just the repo name
            $repo = basename($remoteUrl, '.git');
            return ['repo' => $repo];
        } catch (\Exception $e) {
            // Fallback to directory name if git is not available
            return ['repo' => basename(getcwd())];
        }
    }

    protected function generateTitle(string $environment, ?string $customTitle = null): string
    {
        if ($customTitle) {
            return $customTitle;
        }

        $gitInfo = $this->getGitInfo();

        if (isset($gitInfo['org']) && isset($gitInfo['repo'])) {
            return "{$gitInfo['org']}/{$gitInfo['repo']}/{$environment}/.env";
        }

        if (isset($gitInfo['repo'])) {
            return "{$gitInfo['repo']}/{$environment}/.env";
        }

        return "{$environment}/.env";
    }

    protected function getEnvFilePath(string $environment): string
    {
        if ($environment === 'local' || $environment === 'development') {
            return base_path('.env');
        }

        return base_path(".env.{$environment}");
    }

    protected function createBackup(string $filePath): string
    {
        $backupPath = $filePath . '.backup.' . date('Ymd_His');
        copy($filePath, $backupPath);
        return $backupPath;
    }

    protected function encodeContent(string $content): string
    {
        return base64_encode($content);
    }

    protected function decodeContent(string $content): string
    {
        // Check if content is base64 encoded and decode if necessary
        $decoded = base64_decode($content, true);
        if ($decoded !== false && !preg_match('/^[A-Z_]+=/', $content)) {
            return $decoded;
        }
        return $content;
    }

    protected function runProcess(array $command): Process
    {
        $process = new Process($command);
        $process->setTimeout(60);
        $process->run();
        return $process;
    }

    public function compare(array $config): array
    {
        $environment = $config['environment'] ?? 'local';
        $envFile = $this->getEnvFilePath($environment);

        $localExists = file_exists($envFile);
        $localContent = $localExists ? file_get_contents($envFile) : '';

        try {
            $remoteContent = $this->pull($config + ['skipWrite' => true]);
            $remoteExists = true;
        } catch (\Exception $e) {
            $remoteContent = '';
            $remoteExists = false;
        }

        $areIdentical = $localContent === $remoteContent;

        return [
            'localExists' => $localExists,
            'remoteExists' => $remoteExists,
            'areIdentical' => $areIdentical,
            'localContent' => $localContent,
            'remoteContent' => $remoteContent,
        ];
    }
}