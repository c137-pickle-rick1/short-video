<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class ShortVideoAuthCommand extends Command
{
    protected $signature = 'shortvideo:auth';

    protected $description = 'Open the interactive X login browser via the Node/Playwright sidecar.';

    public function handle(): int
    {
        $command = [
            (string) config('shortvideo.sidecar.node_binary'),
            (string) config('shortvideo.sidecar.cli_path'),
            'open-auth-browser',
        ];

        $cdpUrl = trim((string) config('shortvideo.browser_cdp_url'));
        if ($cdpUrl !== '') {
            $command[] = '--cdp-url';
            $command[] = $cdpUrl;
        } else {
            $command[] = '--browser-profile-dir';
            $command[] = (string) config('shortvideo.browser_profile_dir');
        }

        $storageStatePath = (string) config('shortvideo.storage_state_path');
        if ($storageStatePath !== '') {
            $command[] = '--storage-state-path';
            $command[] = $storageStatePath;
        }

        $process = new Process($command, config('shortvideo.repo_root'));
        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        $this->info('Opening auth browser. Log into X, then stop the command with Ctrl+C.');
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
