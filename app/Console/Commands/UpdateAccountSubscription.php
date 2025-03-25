<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateAccountSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-account-subscription {user_id} {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** Date */
        $date = Carbon::createFromDate($this->argument('date'));

        /** Account */
        $account = Account::with('activeSubscription')->where('user_id', $this->argument('user_id'))->firstOrFail();

        /** Update Subscription */
        if ($account->activeSubscription) {
            $account->activeSubscription->forceFill(['ends_at' => $date])->save();
        } else {
            $account->subscriptions()->create([
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $date,
            ]);
        }

        $this->info('Subscription was successfully updated!');
        $this->info($date);
    }
}
