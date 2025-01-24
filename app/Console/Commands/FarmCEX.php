<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FarmCEX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:cex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm CEX.IO Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::lock($this->signature)->get(function () {
            /** Start Date */
            $startDate = now();

            /** Start Farming */
            Account::where('farmer', 'cex')
                ->get()
                ->each(function (Account $account) {
                    try {
                        /** Get User */
                        $user = $this->makeCEXRequest($account, 'https://app.cexptap.com/api/v2/getUserInfo', []);

                        /** Energy */
                        $energy = intval($user['multiTapsEnergy']);

                        /** Tap */
                        if ($energy >= 10) {
                            $percent = 60 + rand(0, 20);
                            $taps = floor(
                                ($energy * $percent) / 100
                            );
                            $balance = $energy - $taps;

                            /** Data */
                            $data = [
                                'tapsToClaim' => strval($taps),
                                'tapsEnergy' => strval($balance),
                                'tapsTs' => intval(floor(microtime(true) * 1000)),
                            ];

                            /** Send Taps */
                            $result = $this->makeCEXRequest(
                                $account,
                                'https://app.cexptap.com/api/v2/claimMultiTaps',
                                $data
                            );
                        }
                    } catch (\Throwable $e) {
                        $account->delete();

                        /** Log Error */
                        Log::error('CEX Error', [
                            'message' => $e->getMessage()
                        ]);
                    }
                });


            /** End Date */
            $endDate = now();

            /** Get Links */
            $links = Helpers::getCloudAccountLinks(
                Account::where('farmer', 'cex')->get()
            );

            /** Send Message */
            Helpers::sendCloudFarmerMessage('cex.completed', [
                "<b>🏦 CEX Farmer</b>",
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
            ->withHeaders([
                'Origin' => 'https://app.cexptap.com',
                'Referer' => 'https://app.cexptap.com/',
                'X-Requested-With' => 'org.telegram.messenger'
            ])
            ->withUserAgent(
                $account->headers['User-Agent'] ?? Helpers::getUserAgent($account->user_id)
            );
    }

    protected function makeCEXRequest(
        Account $account,
        string $url,
        mixed $data
    ) {

        /** Get Result */
        return $this->getApi($account)
            ->post(
                $url,
                [
                    'platform' => 'android',
                    'authData' => $account->telegram_web_app['initData'],
                    'devAuthData' => $account->telegram_web_app['initDataUnsafe']['user']['id'],
                    'data' => $data
                ]
            )
            ->json('data');
    }
}
