<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FarmDreamcoin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:dreamcoin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Dreamcoin';

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

            /** Send Message */
            Helpers::sendFarmingCompletedMessage('dreamcoin', $startDate, $endDate);
        });
    }


    protected function getApi(Account $account)
    {
        return Http::withHeaders($account->headers)
            ->withHeaders([
                'Origin' => 'https://dreamcoin.ai',
                'Referer' => 'https://dreamcoin.ai/',
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
                $energy = $item['energy'];

                /** @var Collection */
                $availableMultipliers = $item['availableMultipliers']->filter(
                    fn($item) => $item <= $energy
                );

                /** Get Multiplier */
                $multiplier = $availableMultipliers->get(
                    $availableMultipliers->count() > 3 ? 2 : 0
                ) ?? 1;

                /** Deduct Energy */
                $energy -= $multiplier;

                /** Spin Lottery */
                $rewards = $this->getApi($account)
                    ->post(
                        'https://api.dreamcoin.ai/Slot/spin',
                        ['multiplier' => $multiplier]
                    )->json('slotRewards');

                foreach ($rewards as $reward) {
                    switch ($reward['rewardType']) {
                        case 'FreeCase':
                            $freeCaseId = $reward['freeCase'];
                            $this->getApi($account)->get('https://api.dreamcoin.ai/Cases/' . $freeCaseId);
                            $this->getApi($account)->post('https://api.dreamcoin.ai/Cases/' . $freeCaseId . '/open');
                            break;

                        case 'Raid':
                            $rewardNumber = rand(1, 4);
                            $this->getApi($account)->post('https://api.dreamcoin.ai/Raids/claim', [
                                'RewardNumber' => $rewardNumber
                            ]);
                            break;
                    }
                }


                /** Return Energy and Account */
                if ($energy > 0) {
                    return compact(
                        'account',
                        'availableMultipliers',
                        'energy',
                    );
                }
            } catch (\Throwable $e) {
                /** Log Error */
                Log::error('DreamCoin Error', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
            }
        })->filter();
    }

    protected function retrieveAccounts()
    {
        return Account::farmer('dreamcoin')
            ->connected()
            ->get()->map(function (Account $account) {
                try {
                    /** Daily Check-In */
                    $dailyTasks = $this->getApi($account)->get('https://api.dreamcoin.ai/DailyTasks/current')->json('dailyTasks');
                    $today = now()->toDateString();
                    $day = collect($dailyTasks)->first(
                        fn($item) => $item['date'] === $today && $item['isClaimed'] === false
                    );

                    /** Claim Daily-Reward */
                    if ($day) {
                        $this->getApi($account)->post(
                            'https://api.dreamcoin.ai/DailyTasks/claim/' . $day['id']
                        );
                    }

                    /** User */
                    $user = $this->getApi($account)->get('https://api.dreamcoin.ai/Users/current')->json();
                    $balance = $user['balance'];

                    /** Claim Free-Case */
                    $freeCaseId = $user['freeCaseId'];
                    if ($freeCaseId) {
                        $this->getApi($account)->get('https://api.dreamcoin.ai/Cases/' . $freeCaseId);
                        $this->getApi($account)->post('https://api.dreamcoin.ai/Cases/' . $freeCaseId . '/open');
                    }


                    /** Claim Clicks */
                    $currentClicks = $user['clickerLevel']['currentClicks'];
                    if ($currentClicks > 0) {
                        $this->getApi($account)->post(
                            'https://api.dreamcoin.ai/Clicker/collect-reward',
                            ['amount' => $currentClicks]
                        );
                    }

                    /** Upgrade Level */
                    $upgradePrice = $user['clickerLevel']['upgradePrice'];
                    if ($balance >= $upgradePrice) {
                        $this->getApi($account)->post('https://api.dreamcoin.ai/Clicker/upgrade');
                    }

                    /** Energy */
                    $energy = intval($user['energy']['current']);
                    $availableMultipliers = collect($user['availableSpinMultipliers'])
                        ->sort(fn($a, $b) => $b - $a)
                        ->values();

                    /** Return Energy and Account */
                    if ($energy > 0) {
                        return compact(
                            'account',
                            'energy',
                            'availableMultipliers',
                        );
                    }
                } catch (\Throwable $e) {
                    /** Disconnect Account */
                    $account->disconnect();

                    /** Log Error */
                    Log::error('Dreamcoin Error', [
                        'message' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                }
            })->filter();
    }
}
