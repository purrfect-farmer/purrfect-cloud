<?php

namespace App\Console\Commands;

use App\Facades\Madeline;
use App\Models\Account;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->getAccounts()->mapConcurrently(function (Account $account) {
            $api = Madeline::session($account->session_id);
            try {
                $account->farmers->each(function (Farmer $farmer) use ($api) {
                    try {
                        $config = config('farmer.drops')[$farmer->farmer];
                        $data = Madeline::getTelegramData($api, $config['telegram_link']);

                        /** Update TelegramWebApp */
                        $farmer->telegram_web_app = [
                            ...$farmer->telegram_web_app,
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

            } catch (\Throwable $e) {
                /** Logout */
                $api->logout();

                /** Remove Session */
                $account->forceFill(['session_id' => null])->save();
            }
        });
    }

    protected function getAccounts()
    {
        return Account::with('farmers')
            ->subscribed()
            ->whereNotNull('session_id')
            ->get();
    }
}