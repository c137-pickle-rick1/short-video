<?php

namespace App\ShortVideo\Services;

use App\ShortVideo\Support\ShortVideoException;
use Symfony\Component\Process\Process;

class SidecarClient
{
    /**
     * @return array<string, mixed>
     */
    public function discoverSource(string $handle, ?string $sourceUserId = null, ?string $sinceId = null): array
    {
        $arguments = [
            'discover-source',
            '--handle',
            $handle,
            '--mode',
            (string) config('shortvideo.discovery_mode'),
            '--max-scroll-rounds',
            (string) config('shortvideo.discovery_scroll_rounds'),
        ];

        if (is_string($sourceUserId) && trim($sourceUserId) !== '') {
            $arguments[] = '--source-user-id';
            $arguments[] = trim($sourceUserId);
        }

        if (is_string($sinceId) && trim($sinceId) !== '') {
            $arguments[] = '--since-id';
            $arguments[] = trim($sinceId);
        }

        return $this->runJsonCommand($arguments, null, 180.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tweets
     * @return array<string, mixed>
     */
    public function resolveTweets(array $tweets): array
    {
        if ($tweets === []) {
            return ['results' => []];
        }

        return $this->runJsonCommand(
            [
                'resolve-tweets',
                '--mode',
                (string) config('shortvideo.discovery_mode'),
            ],
            json_encode($tweets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            null
        );
    }

    public function openAuthBrowser(): void
    {
        $this->runJsonCommand(['open-auth-browser'], null, null);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<string, mixed>
     */
    private function runJsonCommand(array $arguments, ?string $input = null, ?float $timeout = 180.0): array
    {
        $process = new Process($this->command($arguments), config('shortvideo.repo_root'), $this->environment());
        $process->setTimeout($timeout);

        if ($input !== null) {
            $process->setInput($input);
        }

        $process->run();

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $payload = $stdout !== '' ? json_decode($stdout, true) : null;

        if (! is_array($payload)) {
            if (! $process->isSuccessful()) {
                throw new ShortVideoException(
                    'sidecar_failed',
                    $stderr !== '' ? $stderr : 'Node sidecar exited without valid JSON output.',
                    ['stdout' => $stdout, 'stderr' => $stderr]
                );
            }

            return [];
        }

        if (($payload['ok'] ?? false) !== true) {
            $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];

            throw new ShortVideoException(
                (string) ($error['code'] ?? 'sidecar_failed'),
                (string) ($error['message'] ?? 'Node sidecar reported an error.'),
                [
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'details' => $error['details'] ?? null,
                ]
            );
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function command(array $arguments): array
    {
        $command = [
            (string) config('shortvideo.sidecar.node_binary'),
            (string) config('shortvideo.sidecar.cli_path'),
            ...$arguments,
            '--concurrency',
            '4',
            '--browser-profile-dir',
            (string) config('shortvideo.browser_profile_dir'),
            '--x-api-max-pages',
            (string) config('shortvideo.x_api.max_pages'),
            '--x-api-page-size',
            (string) config('shortvideo.x_api.page_size'),
        ];

        if ((bool) config('shortvideo.x_api.include_replies')) {
            $command[] = '--x-api-include-replies';
        }

        if ((bool) config('shortvideo.x_api.include_retweets')) {
            $command[] = '--x-api-include-retweets';
        }

        $cdpUrl = trim((string) config('shortvideo.browser_cdp_url'));
        if ($cdpUrl !== '') {
            $command[] = '--cdp-url';
            $command[] = $cdpUrl;
        }

        $storageStatePath = (string) config('shortvideo.storage_state_path');
        if ($storageStatePath !== '') {
            $command[] = '--storage-state-path';
            $command[] = $storageStatePath;
        }

        return $command;
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        return [
            'X_API_BEARER_TOKEN' => (string) config('shortvideo.x_api.bearer_token', ''),
        ];
    }
}
