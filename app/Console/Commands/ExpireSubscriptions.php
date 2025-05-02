<?php

namespace App\Console\Commands;

use App\Facades\Madeline;
use App\Helpers;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-subscriptions';

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
        Subscription::with('account')
            ->expiredButActive()
            ->get()
            ->each(function (Subscription $subscription) {
                /** Update The Subscription */
                $subscription->forceFill(['status' => 'expired'])->save();

                /** Get Account */
                $account = $subscription->account;

                /** Logout the Telegram Session */
                if ($account->session_id) {
                    try {
                        Madeline::session($account->session_id)->logout();
                    } catch (\Throwable $e) {
                        /** Log Error */
                        Log::error('Logout Telegram Session', [
                            'account' => $account,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                /** Unset Session and Proxy */
                $account->forceFill([
                    'proxy' => null,
                    'session_id' => null
                ])->save();

                /** Remove User From Group */
                try {
                    Helpers::removeUserFromGroup($account->user_id);
                } catch (\Throwable $e) {
                    /** Log Error */
                    Log::error('Kick Member', [
                        'account' => $account,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        $this->info('Expired subscriptions updated.');
    }
}
