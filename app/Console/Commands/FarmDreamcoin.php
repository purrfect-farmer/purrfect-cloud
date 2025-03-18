<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\Farmer;
use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * @param array $data
     * @return void
     */
    protected function setAuth(Account $account, $data)
    {
        /** Init Data */
        $initData = $data['initData'];

        /** Init Data Unsafe */
        $initDataUnsafe = $data['initDataUnsafe'];

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


                    /** Rewards */
                    $rewardsList = $this->getApi($account)->get('https://api.dreamcoin.ai/FreeReward/current')->json();
                    $rewards = collect($rewardsList)->reduce(
                        fn($result, $tasks, $key) => $result->concat(
                            collect($tasks)->filter(
                                fn($task) => !Helpers::isTelegramLink($task['actionUrl'])
                            )->map(fn($item) => [
                                ...$item,
                                'taskGroup' => $key
                            ])
                        ),
                        collect([])
                    );

                    /** @var Collection */
                    $uncompletedRewards = $rewards->filter(fn($task) => !$task['isCompleted']);


                    if ($uncompletedRewards->isNotEmpty()) {
                        /** Get Random Task */
                        $task = $uncompletedRewards->random();

                        if ($task['taskGroup'] === 'dailyFreeRewards') {
                            /** Task Group: dailyFreeRewards */
                            $this->getApi($account)->post('https://api.dreamcoin.ai/FreeReward/claimDaily/' . $task['id']);
                        } else {
                            /** Task Group: freeRewards */
                            $this->getApi($account)->post('https://api.dreamcoin.ai/FreeReward/claim/' . $task['id']);
                        }
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

                    /** Refetch Auth or Disconnect Account */
                    $this->refetchAuthOrDisconnect($account);
                }
            })->filter();
    }
}
