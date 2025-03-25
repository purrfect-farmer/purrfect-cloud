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
    protected $description = 'Update Account Subscription';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** Date */
        $date = Carbon::createFromDate($this->argument('date'));

        /** Account */
        $account = Account::with('activeSubscription')->where(
            'user_id',
            $this->argument('user_id')
        )->first();

        if (!$account) {
            /** Get Choice */
            $choice = $this->choice(
                'Account does not exist! Would you like to create it?',
                ['Yes', 'No'],
                0
            );

            /** Create Account */
            if ($choice === 'Yes') {
                /** Create */
                $account = Account::create(
                    ['user_id' => $this->argument('user_id')]
                );

                /** Show Info */
                $this->info('Account was successfully created!');
            } else {
                return $this->warn('Nothing to do!');
            }
        }

        /** Update Subscription */
        if ($account->wasRecentlyCreated === false && $account->activeSubscription) {
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
