<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-subscriptions {date}';

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
        $date = Carbon::createFromDate($this->argument('date'));

        /** Create Subscriptions */
        Account::with('activeSubscription')->get()->each(function ($account) use ($date) {
            if ($account->activeSubscription) {
                $account->activeSubscription->forceFill(['ends_at' => $date])->save();
            } else {
                $account->subscriptions()->create([
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => $date,
                ]);
            }
        });

        $this->info('Subscriptions successfully updated!');
        $this->info($date);
    }
}
