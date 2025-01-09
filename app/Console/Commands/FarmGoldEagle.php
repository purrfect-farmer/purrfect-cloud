<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FarmGoldEagle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:gold-eagle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Gold Eagle Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::lock($this->signature)->get(function () {
            // Log Farming Start
            Log::info('[START] Gold Eagle Farming');

            // Start Farming
            Account::where('farmer', 'gold-eagle')
                ->get()
                ->each(function (Account $account) {
                    try {
                        /** API */
                        $api = Http::withHeaders($account->headers)
                            ->withUserAgent(
                                $account->headers['User-Agent'] ?: Helpers::getUserAgent($account->user_id)
                            );

                        /** Get Progress */
                        $progress = $api->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                        /** Tap */
                        if ($progress['energy'] >= 10) {
                            $api->post('https://gold-eagle-api.fly.dev/tap', [
                                'available_taps' => 1,
                                'count' => $progress['energy'],
                                'timestamp' => time(),
                                'salt' => Str::uuid()->toString()
                            ])->json();
                        }
                    } catch (\Throwable $e) {
                        $account->delete();
                    }
                });

            // Log Farming Completion
            Log::info('[END] Gold Eagle Farming');
        });
    }
}
