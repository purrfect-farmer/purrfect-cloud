<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

class FarmDigger extends Command
{
    use FarmerTrait;

    const CHEST_TYPES = [
        7 => 'usdt_chest',
        3 => 'adamant_chest',
        2 => 'gold_chest',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:digger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Digger Automatically';

    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://diggergame.app';

    /**
     * The delay in seconds for all requests.
     *
     * @var int
     */
    protected $delay = 1;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Retrieve Farmers */
            $farmers = $this->retrieveFarmers();

            /** Taps */
            $taps = $farmers->filter(fn($item) => $item['energy'] > 0);

            /** Claim Taps */
            while ($taps->isNotEmpty()) {
                $taps = $this->farmFarmers($taps);
            }
        });
    }

    /**
     *  Set Authorization
     * @param \App\Models\Farmer $farmer
     * @return Farmer
     */
    protected function setAuth(Farmer $farmer)
    {
        /** Init Data */
        $initData = $farmer->getInitData();

        /** Get Access Token */
        $accessToken = $this->getBaseApi($farmer)
            ->post(
                'https://api.diggergame.app/api/auth',
                [
                    'init_data' => $initData,
                    'platform' => 'android',
                ]
            )
            ->json('result.auth.token');

        /** Set Headers */
        return $farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    /** Farm Farmers */
    protected function farmFarmers($farmers)
    {
        return $farmers->mapConcurrently(function ($item) {
            try {
                $farmer = $item['farmer'];
                $energy = $item['energy'];
                $uid = $item['uid'];

                $taps = min($energy, 10);
                $energy -= $taps;

                /** Tap */
                $this->getApi($farmer)
                    ->post(
                        'https://api.diggergame.app/api/play/tap',
                        [
                            'uid' => $uid,
                            'cnt' => $taps
                        ]
                    );

                /** Return Energy and Farmer */
                if ($energy > 0) {
                    return compact(
                        'farmer',
                        'energy',
                        'uid',
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
                try {
                    /** Dig */
                    $this->getApi($farmer)
                        ->post('https://api.diggergame.app/api/play/dig', [
                            'init_data' => $farmer->getInitData(),
                            'platform' => 'android'
                        ]);
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $farmer);
                }

                /** Get Tasks */
                $tasks = collect(
                    $this->getApi($farmer)
                        ->get('https://api.diggergame.app/api/user-task/list')
                        ->json('result')
                );


                $pendingTasks = $tasks->filter(fn($item) => $item['status'] === 'progress');
                $unclaimedTasks = $tasks->filter(fn($item) => $item['status'] === 'waiting_reward');

                /** Start a random Task */
                if ($pendingTasks->isNotEmpty()) {
                    $this->getApi($farmer)
                        ->post('https://api.diggergame.app/api/user-task/update', [
                            'type' => $pendingTasks->random()['type']
                        ])
                        ->json('result');
                }

                /** Claim a random Task */
                if ($unclaimedTasks->isNotEmpty()) {
                    $this->getApi($farmer)
                        ->post('https://api.diggergame.app/api/user-task/check', [
                            'type' => $unclaimedTasks->random()['type']
                        ])
                        ->json('result');
                }




                /** Get User */
                $user = $this->getApi($farmer)
                    ->get('https://api.diggergame.app/api/me')
                    ->json('result');

                /** Balance */
                $balance = $user['coin_cnt'];

                /** Cards */
                $cards = $this->getApi($farmer)
                    ->get('https://api.diggergame.app/api/user/card/list')
                    ->json('result');

                /** Upgradable Cards */
                $upgradableCards = collect($cards)->filter(
                    fn($item) => isset($item['next_level']) && $item['next_level']['price'] <= $balance
                );

                /** Level Zero Cards */
                $levelZeroCards = $upgradableCards->filter(
                    fn($item) => !isset($item['cur_level'])
                );

                /** Collection */
                $collection = $levelZeroCards->isNotEmpty()
                    ? $levelZeroCards
                    : $upgradableCards;

                /** Random Card */
                $selectedCard = $collection->isNotEmpty() ? $collection->random() : null;

                if ($selectedCard) {
                    /** Buy or Upgrade Card */
                    $this->getApi($farmer)->post(
                        'https://api.diggergame.app/api/user/card/buy',
                        ['card_id' => $selectedCard['card']['id']]
                    );
                }




                /** Get Chest Status */
                $chestStatus = collect(
                    $this->getApi($farmer)
                        ->get('https://api.diggergame.app/api/content/chest/status')
                        ->json('result.chest_statuses')
                );

                /** Viewable Chests */
                $viewableChests = $chestStatus->filter(
                    fn($item) => (
                        $item['remaining_cooldown_sec'] === 0 &&
                        $item['ads_watched'] < $item['ads_required']
                    )
                );

                if ($viewableChests->isNotEmpty()) {
                    /** Select Chest */
                    $chest = $viewableChests->random();

                    /** Loop */
                    for ($i = $chest['ads_watched']; $i < $chest['ads_required']; $i++) {

                        /** Get Reward */
                        $reward = $this->getApi($farmer)
                            ->post('https://api.diggergame.app/api/content/intent', [
                                'platform' => '2',
                                'type' => static::CHEST_TYPES[$chest['chest_id']],
                            ])
                            ->json('result.uid');

                        /** Sleep */
                        Sleep::for(10)->seconds();

                        /** Claim Reward */
                        $this->getApi($farmer)
                            ->post(
                                'https://api.diggergame.app/api/content/update',
                                [
                                    'status' => 'reward',
                                    'uid' => $reward,
                                ]
                            );
                    }
                }

                /** Get Chests */
                $chests = collect(
                    $this->getApi($farmer)
                        ->get('https://api.diggergame.app/api/user-chest/list')
                        ->json('result')
                )
                    ->filter(fn($item) => $item['status'] === 'progress' && isset($item['chest']))
                    ->sort(fn($a, $b) => $b['chest']['id'] - $a['chest']['id'])
                    ->values();


                /** Current Chest */
                $currentChest = $chests->isNotEmpty() ? $chests->first() : null;

                /** UID */
                $uid = $currentChest ? $currentChest['uid'] : null;

                /** Energy */
                $energy = $currentChest ?
                    $currentChest['open_tap_cnt'] - $currentChest['current_tap_cnt'] : 0;

                /** Return Energy and Farmer */
                if ($energy > 0) {
                    return compact(
                        'farmer',
                        'uid',
                        'energy',
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
