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
                    $account->farmers->each(function (Farmer $farmer) use ($api) {
                        try {
                            $config = config('farmer.drops')[$farmer->farmer];
                            $data = Madeline::getTelegramData($api, $config['telegram_link']);

                            /** Update TelegramWebApp */
                            $farmer->telegram_web_app = [
                                'initData' => $data['initData']
                            ];

                            /** Mark as connected */
                            $farmer->is_connected = true;

                            /** Save */
                            $farmer->save();
                        } catch (\Throwable $e) {
                            /** Log Error */
                            Log::error($config['title'] . ' Error (Updating WebAppData)', [
                                'user_id' => $farmer->user_id ?? null,
                                'username' => $farmer->getInitDataUnsafe()['user']['username'] ?? null,
                                'message' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);

                            /** Throw Error */
                            throw $e;
                        }
                    });

                    /** Update Account */
                    $data = Madeline::getTelegramData(
                        $api,
                        config('farmer.farmer_bot_link')
                    );

                    $account->update([
                        'data' => array_merge(
                            $account->data ?? [],
                            ['user' => $data['initDataUnsafe']['user']]
                        )
                    ]);

                } catch (\Throwable $e) {
                    /** Logout */
                    try {
                        $api->logout();
                    } catch (\Throwable $e) {
                        Log::error(
                            'TELEGRAM SESSION LOGOUT: ' . $e->getMessage(),
                            ['user_id' => $account->user_id ?? null]
                        );
                    }

                    throw $e;
                }
            } catch (\Throwable $e) {
                /** Update Session */
                $account->update(['session_id' => null]);
            }
        });

        $endDate = now();
        $links = $this->getCloudUserLinks();

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

    protected function getAccounts()
    {
        return $this->getAccountsBuilder()->get();
    }

    protected function getAccountsBuilder()
    {
        return Account::with('farmers')
            ->subscribed()
            ->whereNotNull('session_id');
    }


    /**
     * Get User Links
     * @return string
     */
    public function getCloudUserLinks()
    {
        $links = Helpers::getCloudUserLinks(
            Account::subscribed()
                ->get()
                ->map(fn(Account $account) => [
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
