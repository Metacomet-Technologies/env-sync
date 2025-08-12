<?php

namespace Metacomet\EnvSync\Commands;

use Illuminate\Console\Command;

class EnvSyncCommand extends Command
{
    public $signature = 'env-sync';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
