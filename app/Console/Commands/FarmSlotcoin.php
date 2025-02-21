<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FarmSlotcoin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:slotcoin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Slotcoin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::lock($this->signature)->get(function () {
            /** Start Date */
            $startDate = now();


            /** Retrieve Accounts */
            $accounts = $this->retrieveAccounts();

            /** Tap */
            while ($accounts->isNotEmpty()) {
                $accounts = $this->farmAccounts($accounts);
            }

            /** End Date */
            $endDate = now();

            /** Get Links */
            $links = Helpers::getCloudAccountLinks(
                Account::where('farmer', 'slotcoin')->get()
            );

            /** Send Message */
            Helpers::sendCloudFarmerMessage('slotcoin.completed', [
                "<b>🎰 Slotcoin Farmer</b>",
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
                'Origin' => 'https://app.slotcoin.app',
                'Referer' => 'https://app.slotcoin.app/',
                'X-Requested-With' => 'org.telegram.messenger'
            ])
            ->withUserAgent(
                $account->headers['User-Agent'] ?? Helpers::getUserAgent($account->user_id)
            );
    }

    protected function farmAccounts($accounts)
    {
        return $accounts->map(function ($item) {
            try {
                $account = $item['account'];
                $ticketsCount = $item['ticketsCount'];
                $energy = $item['energy'];
                $bid = $item['bid'];


                if ($ticketsCount > 0) {
                    /** Subtract Ticket */
                    $ticketsCount -= 1;

                    /** Spin Ticket */
                    $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/daily/spin');
                }

                /** Deduct Energy */
                $energy -= $bid;

                /** Spin Lottery */
                $this->getApi($account)
                    ->post(
                        'https://api.slotcoin.app/v1/clicker/api/spin',
                    );

                /** Return Energy and Account */
                if ($energy >= $bid || $ticketsCount > 0) {
                    return compact(
                        'account',
                        'ticketsCount',
                        'energy',
                        'bid',
                    );
                }
            } catch (\Throwable $e) {
                /** Log Error */
                Log::error('Slotcoin Error', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
            }
        })->filter();
    }

    protected function retrieveAccounts()
    {
        return Account::where('farmer', 'slotcoin')
            ->get()->map(function (Account $account) {
                try {
                    /** Daily Check-In */
                    $dailyCheckIn = $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/check-in/info')->json();
                    $timeToClaim = intval($dailyCheckIn['time_to_claim']);

                    /** Claim Daily Check-In */
                    if ($timeToClaim <= 0) {
                        $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/check-in/claim');
                    }

                    /** Get Info */
                    $info = $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/api/info')->json();

                    /** Tickets */
                    $ticketsCount = intval($info['user']['daily_roulette_count']);

                    /** Energy */
                    $energy = intval($info['user']['spins']);
                    $bid = intval($info['user']['bid']);

                    /** Return Energy and Account */
                    if ($energy >= $bid || $ticketsCount > 0) {
                        return compact(
                            'account',
                            'ticketsCount',
                            'energy',
                            'bid',
                        );
                    }
                } catch (\Throwable $e) {
                    if (app()->isProduction()) {
                        $account->delete();
                    }

                    /** Log Error */
                    Log::error('Slotcoin Error', [
                        'message' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                }
            })->filter();
    }
}
