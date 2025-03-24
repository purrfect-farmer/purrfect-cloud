<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmer:expire-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire Subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Subscription::where('ends_at', '<', now())
            ->where('status', 'active')
            ->get()
            ->each->update(['status' => 'expired']);

        $this->info('Expired subscriptions updated.');
    }
}
