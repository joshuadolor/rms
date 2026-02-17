<?php

namespace App\Console\Commands;

use App\Services\E2eCleanupService;
use Illuminate\Console\Command;

class E2eCleanupUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'e2e:cleanup-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete users whose email matches E2E test patterns (safe only when APP_ENV=local)';

    /**
     * Execute the console command.
     */
    public function handle(E2eCleanupService $cleanup): int
    {
        $env = config('app.env');

        if ($env !== 'local' && $env !== 'testing') {
            $this->error("e2e:cleanup-users is only allowed when APP_ENV=local or APP_ENV=testing. Current APP_ENV={$env}. No users were deleted.");

            return self::FAILURE;
        }

        $deleted = $cleanup->deleteE2eUsers();

        $this->info("Deleted {$deleted} user(s) matching E2E email patterns.");

        return self::SUCCESS;
    }
}
