<?php

namespace App\Console\Commands;

use App\Facades\Madeline;
use App\Helpers;
use App\Models\Account;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UpdateWebAppData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmer:update-web-app-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update WebAppData of accounts with session';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = now();
        $this->getAccounts()->mapConcurrently(function (Account $account) {
            try {
                $api = Madeline::session($account->session_id);
                try {
                    /** Update Farmers */
                    $account->farmers->each(function (Farmer $farmer) use ($api, $account) {
                        /** Get Config */
                        $config = config('farmer.drops')[$farmer->farmer];

                        /** Get Web App Data */
                        $data = Madeline::getTelegramData($api, $config['telegram_link']);

                        try {
                            /** Update TelegramWebApp */
                            $farmer->update([
                                'is_connected' => true,
                                'telegram_web_app' => [
                                    'initData' => $data['initData']
                                ]
                            ]);
                        } catch (\Throwable $e) {
                            /** Log Error */
                            $this->logError(
                                title: 'SAVING WEB_APP_DATA',
                                config: $config,
                                account: $account,
                                error: $e
                            );
                        }
                    });

                    /** Get User Details */
                    $data = Madeline::getTelegramData(
                        $api,
                        config('farmer.farmer_bot_link')
                    );

                    /** Save User Details */
                    try {
                        $account->update([
                            'data' => array_merge(
                                $account->data ?? [],
                                ['user' => $data['initDataUnsafe']['user']]
                            )
                        ]);
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError(
                            title: 'SAVING ACCOUNT USER',
                            account: $account,
                            error: $e
                        );
                    }


                } catch (\Throwable $e) {
                    /** Logout */
                    try {
                        $api->logout();
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError(
                            title: 'TELEGRAM SESSION LOGOUT',
                            account: $account,
                            error: $e
                        );
                    }

                    throw $e;
                }
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError(
                    title: 'TELEGRAM WEBAPP DATA',
                    account: $account,
                    error: $e
                );

                /** Update Session */
                try {
                    $account->update(['session_id' => null]);
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError(
                        title: 'REMOVING SESSION',
                        account: $account,
                        error: $e
                    );
                }
            }
        });

        $endDate = now();
        $links = $this->getCloudUserLinks();

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            'telegram:update-web-app-data',
            [
                "<b>🌐 Telegram WebAppData</b>",
                "<i>✅ Status: Completed</i>",
                $links,
                "<b>🗓️ Start Date</b>: $startDate",
                "<b>🗓️ End Date</b>: $endDate",
            ],
            ['message_thread_id' => config('farmer.announcement_thread_id')]
        );
    }

    /**
     * Get Accounts
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    protected function getAccounts()
    {
        return $this->getAccountsBuilder()->get();
    }

    /**
     * Get Accounts Builder
     * @return \Illuminate\Database\Eloquent\Builder<Account>
     */
    protected function getAccountsBuilder()
    {
        return Account::with('farmers')
            ->subscribed()
            ->whereNotNull('session_id');
    }

    /**
     * Log Error
     * @param string $title
     * @param \App\Models\Account $account
     * @param \Throwable $error
     * @param array|null $config
     * @return void
     */
    protected function logError($title, Account $account, $error, $config = null)
    {
        /** Log Error */
        Log::error(($config ? $config['title'] . ' ' : '') . 'Error (' . $title . ')', [
            'title' => $account->getFarmerTitle(),
            'user_id' => $account->user_id ?? null,
            'username' => $account->getUsername(),
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }


    /**
     * Get User Links
     * @return string
     */
    public function getCloudUserLinks()
    {
        $accounts = Account::subscribed()->get();
        $links = Helpers::getCloudUserLinks(
            $accounts->map(fn(Account $account) => [
                'id' => $account->user_id,
                'status' => $account->session_id ? '✅' : '❌',
                'session' => $account->session_id ? '🟨' : '🟪',
                'username' => $account->data['user']['username'] ?? '',
                'title' => $account->getFarmerTitle(),
            ])
        );

        return "\n<blockquote><i>WebAppData updated!</i></blockquote>$links";
    }
}
