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
            /** Start Date */
            $startDate = now();

            /** Start Farming */
            Account::where('farmer', 'gold-eagle')
                ->get()
                ->each(function (Account $account) {
                    try {
                        /** Get Progress */
                        $progress = $this->getApi($account)->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                        /** Tap */
                        if ($progress['energy'] >= 10) {
                            $this->getApi($account)->post('https://gold-eagle-api.fly.dev/tap', [
                                'available_taps' => 1,
                                'count' => $progress['energy'],
                                'timestamp' => time(),
                                'salt' => Str::uuid()->toString()
                            ])->json();
                        }
                    } catch (\Throwable $e) {
                        $account->delete();

                        /** Log Error */
                        Log::error('Gold Eagle Error', [
                            'message' => $e->getMessage()
                        ]);
                    }
                });


            /** End Date */
            $endDate = now();

            /** Get Links */
            $links = Helpers::getCloudAccountLinks(
                Account::where('farmer', 'gold-eagle')->get()
            );

            /** Send Message */
            Helpers::sendCloudFarmerMessage('gold-eagle.completed', [
                "<b>🪙 Gold Eagle Farmer</b>",
                "<i>✅ Status: Completed</i>",
                $links,
                "<b>🗓️ Start Date</b>: $startDate",
                "<b>🗓️ End Date</b>: $endDate"
            ]);
        });
    }

    protected function getApi(Account $account)
    {
        return Http::timeout(10)
            ->withHeaders($account->headers)
            ->withUserAgent(
                $account->headers['User-Agent'] ?: Helpers::getUserAgent($account->user_id)
            );
    }
}
