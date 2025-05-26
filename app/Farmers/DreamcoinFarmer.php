<?php
namespace App\Farmers;

use App\Helpers;

class DreamcoinFarmer extends BaseFarmer
{
    protected $key = 'dreamcoin';
    protected $origin = 'https://dreamcoin.ai';

    protected function setAuth()
    {
        /** Init Data */
        $initData = $this->farmer->getInitData();

        /** Init Data Unsafe */
        $initDataUnsafe = $this->farmer->getInitDataUnsafe();

        /** Get Access Token */
        $accessToken = $this->getBaseApi()
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
        return $this->farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    public function process()
    {
        try {
            /** Daily Check-In */
            $dailyTasks = $this->getApi()->get('https://api.dreamcoin.ai/DailyTasks/current')->json('dailyTasks');
            $today = now()->toDateString();
            $day = collect($dailyTasks)->first(
                fn($item) => $item['date'] === $today && $item['isClaimed'] === false
            );

            /** Claim Daily-Reward */
            if ($day) {
                $this->getApi()->post(
                    'https://api.dreamcoin.ai/DailyTasks/claim/' . $day['id']
                );
            }


            /** Rewards */
            $rewardsList = $this->getApi()->get('https://api.dreamcoin.ai/FreeReward/current')->json();
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

            /** @var \Illuminate\Support\Collection */
            $uncompletedRewards = $rewards->filter(fn($task) => !$task['isCompleted']);


            if ($uncompletedRewards->isNotEmpty()) {
                /** Get Random Task */
                $task = $uncompletedRewards->random();

                if ($task['taskGroup'] === 'dailyFreeRewards') {
                    /** Task Group: dailyFreeRewards */
                    $this->getApi()->post('https://api.dreamcoin.ai/FreeReward/claimDaily/' . $task['id']);
                } else {
                    /** Task Group: freeRewards */
                    $this->getApi()->post('https://api.dreamcoin.ai/FreeReward/claim/' . $task['id']);
                }
            }



            /** User */
            $user = $this->getApi()->get('https://api.dreamcoin.ai/Users/current')->json();
            $balance = $user['balance'];

            /** Claim Free-Case */
            $freeCaseId = $user['freeCaseId'];
            if ($freeCaseId) {
                $this->getApi()->get('https://api.dreamcoin.ai/Cases/' . $freeCaseId);
                $this->getApi()->post('https://api.dreamcoin.ai/Cases/' . $freeCaseId . '/open');
            }


            /** Claim Clicks */
            $currentClicks = $user['clickerLevel']['currentClicks'];
            if ($currentClicks > 0) {
                $this->getApi()->post(
                    'https://api.dreamcoin.ai/Clicker/collect-reward',
                    ['amount' => $currentClicks]
                );
            }

            /** Upgrade Level */
            $upgradePrice = $user['clickerLevel']['upgradePrice'];
            if ($balance >= $upgradePrice) {
                $this->getApi()->post('https://api.dreamcoin.ai/Clicker/upgrade');
            }

            /** Energy */
            $energy = intval($user['energy']['current']);
            $multipliers = collect($user['availableSpinMultipliers'])
                ->sort(fn($a, $b) => $b - $a)
                ->values();

            while ($energy > 0) {
                /** @var \Illuminate\Support\Collection */
                $availableMultipliers = $multipliers->filter(
                    fn($item) => $item <= $energy
                )->values();

                /** Get Multiplier */
                $multiplier = $availableMultipliers->get(0) ?? 1;

                /** Deduct Energy */
                $energy -= $multiplier;

                /** Spin Lottery */
                $rewards = $this->getApi()
                    ->post(
                        'https://api.dreamcoin.ai/Slot/spin',
                        ['multiplier' => $multiplier]
                    )->json('slotRewards');

                foreach ($rewards as $reward) {
                    switch ($reward['rewardType']) {
                        case 'FreeCase':
                            $freeCaseId = $reward['freeCase'];
                            $this->getApi()->get('https://api.dreamcoin.ai/Cases/' . $freeCaseId);
                            $this->getApi()->post('https://api.dreamcoin.ai/Cases/' . $freeCaseId . '/open');
                            break;

                        case 'Raid':
                            $rewardNumber = rand(1, 4);
                            $this->getApi()->post('https://api.dreamcoin.ai/Raids/claim', [
                                'RewardNumber' => $rewardNumber
                            ]);
                            break;
                    }
                }
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Refetch Auth or Disconnect Farmer */
            $this->refetchAuthOrDisconnect();
        }
    }
}