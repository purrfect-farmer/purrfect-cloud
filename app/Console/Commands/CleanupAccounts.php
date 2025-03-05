<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class CleanupAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup Accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Account::withoutEvents(function () {
            Account::whereNotIn(
                'farmer',
                collect(config('farmer.drops'))
                    ->filter(fn($drop) => $drop['enabled'])
                    ->keys()
            )->delete();
        });
        $this->info("Accounts Deleted");
    }
}
