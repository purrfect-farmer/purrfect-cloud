<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\Farmer;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class FarmDreamcoin extends Command
{
    use Farmer;

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
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://dreamcoin.ai';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Retrieve Accounts */
            $accounts = $this->retrieveAccounts();

            /** Tap */
            while ($accounts->isNotEmpty()) {
                $accounts = $this->farmAccounts($accounts);
            }
        });
    }

    /**
     *  Set Authorization
     * @param \App\Models\Account $account
     * @return void
     */
    protected function setAuth(Account $account)
    {
        /** Init Data */
        $initData = $account->telegram_web_app['initData'];

        /** Init Data Unsafe */
        $initDataUnsafe = $account->telegram_web_app['initDataUnsafe'];

        /** Get Access Token */
        $accessToken = $this->getBaseApi($account)
            ->post(
                'https://api.dreamcoin.ai/Auth/telegram',
                [
                    'auth_date' => $initDataUnsafe['auth_date'],
                    'hash' => $initDataUnsafe['hash'],
                    'id' => $initDataUnsafe['user']['id'],
                    'first_name' => $initDataUnsafe['user']['first_name'],
                    'last_name' => $initDataUnsafe['user']['last_name'],
                    'username' => $initDataUnsafe['user']['username'],
                    'photo_url' => $initDataUnsafe['user']['photo_url'],
                    'raw_init_data' => $initData
                ]
            )
            ->json('token');

        /** Set Headers */
        $account->setAuthorizationHeader('Bearer ' . $accessToken);
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
                $this->logError($e);
            }
        })->filter();
    }

    protected function retrieveAccounts()
    {
        return Account::farmer('dreamcoin')
            ->connected()
            ->get()->map(function (Account $account) {
                try {
                    /** Set Auth */
                    $this->setAuth($account);

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
                    /** Log Error */
                    $this->logError($e);

                    /** Disconnect Account */
                    $account->disconnect();
                }
            })->filter();
    }
}
