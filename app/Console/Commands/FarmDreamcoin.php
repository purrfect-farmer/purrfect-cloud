<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Helpers;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FarmDreamcoin extends Command
{
    use FarmerTrait;

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
            /** Retrieve Farmers */
            $farmers = $this->retrieveFarmers();

            /** Tap */
            while ($farmers->isNotEmpty()) {
                $farmers = $this->farmFarmers($farmers);
            }
        });
    }

    /**
     *  Set Authorization
     * @param \App\Models\Farmer $farmer
     * @return void
     */
    protected function setAuth(Farmer $farmer)
    {
        /** Init Data */
        $initData = $farmer->getInitData();

        /** Init Data Unsafe */
        $initDataUnsafe = $farmer->getInitDataUnsafe();

        /** Get Access Token */
        $accessToken = $this->getBaseApi($farmer)
            ->post(
                'https://api.dreamcoin.ai/Auth/telegram',
                [
                    'auth_date' => $initDataUnsafe['auth_date'],
                    'hash' => $initDataUnsafe['hash'],
                    'id' => $initDataUnsafe['user']['id'],
                    'first_name' => $initDataUnsafe['user']['first_name'] ?? '',
                    'last_name' => $initDataUnsafe['user']['last_name'] ?? '',
                    'username' => $initDataUnsafe['user']['username'] ?? '',
                    'photo_url' => $initDataUnsafe['user']['photo_url'] ?? '',
                    'raw_init_data' => $initData
                ]
            )
            ->json('token');

        /** Set Headers */
        $farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    protected function farmFarmers($farmers)
    {
        return $farmers->mapConcurrently(function ($item) {
            try {
                $farmer = $item['farmer'];
                $energy = $item['energy'];

                /** @var Collection */
                $availableMultipliers = $item['availableMultipliers']->filter(
                    fn($item) => $item <= $energy
                )->values();

                /** Get Multiplier */
                $multiplier = $availableMultipliers->get(0) ?? 1;

                /** Deduct Energy */
                $energy -= $multiplier;

                /** Spin Lottery */
                $rewards = $this->getApi($farmer)
                    ->post(
                        'https://api.dreamcoin.ai/Slot/spin',
                        ['multiplier' => $multiplier]
                    )->json('slotRewards');

                foreach ($rewards as $reward) {
                    switch ($reward['rewardType']) {
                        case 'FreeCase':
                            $freeCaseId = $reward['freeCase'];
                            $this->getApi($farmer)->get('https://api.dreamcoin.ai/Cases/' . $freeCaseId);
                            $this->getApi($farmer)->post('https://api.dreamcoin.ai/Cases/' . $freeCaseId . '/open');
                            break;

                        case 'Raid':
                            $rewardNumber = rand(1, 4);
                            $this->getApi($farmer)->post('https://api.dreamcoin.ai/Raids/claim', [
                                'RewardNumber' => $rewardNumber
                            ]);
                            break;
                    }
                }


                /** Return Energy and Farmer */
                if ($energy > 0) {
                    return compact(
                        'farmer',
                        'availableMultipliers',
                        'energy',
                    );
                }
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $item['farmer']);
            }
        })->filter();
    }

    protected function retrieveFarmers()
    {
        return $this->getFarmers()->mapConcurrently(function (Farmer $farmer) {
            try {
                /** Daily Check-In */
                $dailyTasks = $this->getApi($farmer)->get('https://api.dreamcoin.ai/DailyTasks/current')->json('dailyTasks');
                $today = now()->toDateString();
                $day = collect($dailyTasks)->first(
                    fn($item) => $item['date'] === $today && $item['isClaimed'] === false
                );

                /** Claim Daily-Reward */
                if ($day) {
                    $this->getApi($farmer)->post(
                        'https://api.dreamcoin.ai/DailyTasks/claim/' . $day['id']
                    );
                }


                /** Rewards */
                $rewardsList = $this->getApi($farmer)->get('https://api.dreamcoin.ai/FreeReward/current')->json();
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
                        $this->getApi($farmer)->post('https://api.dreamcoin.ai/FreeReward/claimDaily/' . $task['id']);
                    } else {
                        /** Task Group: freeRewards */
                        $this->getApi($farmer)->post('https://api.dreamcoin.ai/FreeReward/claim/' . $task['id']);
                    }
                }



                /** User */
                $user = $this->getApi($farmer)->get('https://api.dreamcoin.ai/Users/current')->json();
                $balance = $user['balance'];

                /** Claim Free-Case */
                $freeCaseId = $user['freeCaseId'];
                if ($freeCaseId) {
                    $this->getApi($farmer)->get('https://api.dreamcoin.ai/Cases/' . $freeCaseId);
                    $this->getApi($farmer)->post('https://api.dreamcoin.ai/Cases/' . $freeCaseId . '/open');
                }


                /** Claim Clicks */
                $currentClicks = $user['clickerLevel']['currentClicks'];
                if ($currentClicks > 0) {
                    $this->getApi($farmer)->post(
                        'https://api.dreamcoin.ai/Clicker/collect-reward',
                        ['amount' => $currentClicks]
                    );
                }

                /** Upgrade Level */
                $upgradePrice = $user['clickerLevel']['upgradePrice'];
                if ($balance >= $upgradePrice) {
                    $this->getApi($farmer)->post('https://api.dreamcoin.ai/Clicker/upgrade');
                }

                /** Energy */
                $energy = intval($user['energy']['current']);
                $availableMultipliers = collect($user['availableSpinMultipliers'])
                    ->sort(fn($a, $b) => $b - $a)
                    ->values();

                /** Return Energy and Farmer */
                if ($energy > 0) {
                    return compact(
                        'farmer',
                        'energy',
                        'availableMultipliers',
                    );
                }
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $farmer);

                /** Refetch Auth or Disconnect Farmer */
                $this->refetchAuthOrDisconnect($farmer);
            }
        })->filter();
    }
}
