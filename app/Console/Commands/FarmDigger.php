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

            /** Rewards */
            $rewards = $farmers->filter(fn($item) => isset($item['reward']));

            /** Claim Rewards */
            if ($rewards->isNotEmpty()) {
                $this->claimRewards($rewards);
            }

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
     * @param array $data
     * @return void
     */
    protected function setAuth(Farmer $farmer, $data)
    {
        /** Init Data */
        $initData = $data['initData'];

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
        $farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }


    protected function claimRewards($farmers)
    {
        /** Sleep */
        Sleep::for(10)->seconds();

        /** Claim Reward */
        $farmers->each(function ($item) {
            try {
                $farmer = $item['account'];
                $reward = $item['reward'];

                /** Claim Reward */
                $this->getApi($farmer)
                    ->post(
                        'https://api.diggergame.app/api/content/update',
                        [
                            'status' => 'reward',
                            'uid' => $reward,
                        ]
                    );
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $item['account']);
            }
        });
    }

    /** Farm Farmers */
    protected function farmFarmers($farmers)
    {
        return $farmers->map(function ($item) {
            try {
                $farmer = $item['account'];
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
                        'account',
                        'energy',
                        'uid',
                    );
                }
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $item['account']);
            }
        })->filter();
    }

    protected function retrieveFarmers()
    {
        return Farmer::farmer('digger')
            ->connected()
            ->get()->map(function (Farmer $farmer) {
                try {
                    /** Dig */
                    try {
                        $this->getApi($farmer)
                            ->post('https://api.diggergame.app/api/play/dig', [
                                'init_data' => $farmer->telegram_web_app['initData'],
                                'platform' => 'android'
                            ]);
                    } catch (\Throwable $e) {
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

                    $viewableChests = $chestStatus->filter(
                        fn($item) => (
                            $item['remaining_cooldown_sec'] === 0 &&
                            $item['ads_watched'] < $item['ads_required']
                        )
                    );

                    $reward = $viewableChests->isNotEmpty() ?
                        (
                            $this->getApi($farmer)
                            ->post('https://api.diggergame.app/api/content/intent', [
                                'platform' => '2',
                                'type' => static::CHEST_TYPES[$viewableChests->random()['chest_id']],
                            ])
                            ->json('result.uid')
                        ) : null;


                    /** Get Chests */
                    $chests = collect(
                        $this->getApi($farmer)
                            ->get('https://api.diggergame.app/api/user-chest/list')
                            ->json('result')
                    )
                        ->filter(fn($item) => $item['status'] === 'progress')
                        ->sort(fn($a, $b) => $b['chest']['id'] - $a['chest']['id']);


                    /** Current Chest */
                    $currentChest = $chests->isNotEmpty() ? $chests->first() : null;

                    /** UID */
                    $uid = $currentChest ? $currentChest['uid'] : null;

                    /** Energy */
                    $energy = $currentChest ?
                        $currentChest['open_tap_cnt'] - $currentChest['current_tap_cnt'] : 0;

                    /** Return Energy and Farmer */
                    if ($energy > 0 || $reward) {
                        return compact(
                            'account',
                            'uid',
                            'energy',
                            'reward'
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
