<?php
namespace App\Farmers;

use Illuminate\Support\Sleep;

class DiggerFarmer extends BaseFarmer
{

    protected $key = 'digger';
    protected $origin = 'https://diggergame.app';

    protected $delay = 1;

    const CHEST_TYPES = [
        7 => 'usdt_chest',
        3 => 'adamant_chest',
        2 => 'gold_chest',
    ];

    protected function setAuth()
    {
        /** Init Data */
        $initData = $this->farmer->getInitData();

        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->post(
                'https://api.diggergame.app/api/auth',
                [
                    'init_data' => $initData,
                    'platform' => 'android',
                ]
            )
            ->json('result.auth.token');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    public function process()
    {
        try {
            try {
                /** Dig */
                $this->getApi()
                    ->post('https://api.diggergame.app/api/play/dig', [
                        'init_data' => $this->farmer->getInitData(),
                        'platform' => 'android'
                    ]);
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e);
            }

            /** Get Tasks */
            $tasks = collect(
                $this->getApi()
                    ->get('https://api.diggergame.app/api/user-task/list')
                    ->json('result')
            );


            $pendingTasks = $tasks->filter(fn($item) => $item['status'] === 'progress');
            $unclaimedTasks = $tasks->filter(fn($item) => $item['status'] === 'waiting_reward');

            /** Start a random Task */
            if ($pendingTasks->isNotEmpty()) {
                $this->getApi()
                    ->post('https://api.diggergame.app/api/user-task/update', [
                        'type' => $pendingTasks->random()['type']
                    ])
                    ->json('result');
            }

            /** Claim a random Task */
            if ($unclaimedTasks->isNotEmpty()) {
                $this->getApi()
                    ->post('https://api.diggergame.app/api/user-task/check', [
                        'type' => $unclaimedTasks->random()['type']
                    ])
                    ->json('result');
            }




            /** Get User */
            $user = $this->getApi()
                ->get('https://api.diggergame.app/api/me')
                ->json('result');

            /** Balance */
            $balance = $user['coin_cnt'];

            /** Cards */
            $cards = $this->getApi()
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
                $this->getApi()->post(
                    'https://api.diggergame.app/api/user/card/buy',
                    ['card_id' => $selectedCard['card']['id']]
                );
            }




            /** Get Chest Status */
            $chestStatus = collect(
                $this->getApi()
                    ->get('https://api.diggergame.app/api/content/chest/status')
                    ->json('result.chest_statuses')
            );

            /** Viewable Chests */
            $viewableChests = $chestStatus->filter(
                fn($item) => (
                    isset(static::CHEST_TYPES[$item['chest_id']]) &&
                    $item['remaining_cooldown_sec'] === 0 &&
                    $item['ads_watched'] < $item['ads_required']
                )
            );

            if ($viewableChests->isNotEmpty()) {
                /** Select Chest */
                $chest = $viewableChests->random();

                /** Loop */
                for ($i = $chest['ads_watched']; $i < $chest['ads_required']; $i++) {
                    $this->watchAd(static::CHEST_TYPES[$chest['chest_id']] ?? null);
                }
            }

            /** Spin */
            $cooldown = $this->getApi()
                ->get('https://api.diggergame.app/api/wheel/remainingColdDown')
                ->json('result');

            /** Spin Wheel */
            for ($i = 0; $i < $cooldown['ticket_count']; $i++) {
                $result = $this->getApi()->get('https://api.diggergame.app/api/wheel/getWinItem')->json('result');
            }

            /** Watch ticket ADs */
            $currentTicketCount = $this->getApi()
                ->get('https://api.diggergame.app/api/wheel/currentTicketCount')
                ->json('result');


            if ($currentTicketCount['possible']) {
                for ($i = $currentTicketCount['count_AD']; $i < 5; $i++) {
                    $this->watchAd('ticket');
                }
            }

            /** Get Chests */
            $chests = collect(
                $this->getApi()
                    ->get('https://api.diggergame.app/api/user-chest/list')
                    ->json('result')
            )
                ->filter(fn($item) => isset($item['chest']) && $item['status'] === 'progress')
                ->sort(fn($a, $b) => $b['chest']['id'] - $a['chest']['id'])
                ->values();


            /** Current Chest */
            $currentChest = $chests->isNotEmpty() ? $chests->first() : null;

            /** UID */
            $uid = $currentChest ? $currentChest['uid'] : null;

            /** Energy */
            $energy = $currentChest ?
                $currentChest['open_tap_cnt'] - $currentChest['current_tap_cnt'] : 0;

            while ($energy > 0) {
                $taps = min($energy, 10);
                $energy -= $taps;

                /** Tap */
                $this->getApi()
                    ->post(
                        'https://api.diggergame.app/api/play/tap',
                        [
                            'uid' => $uid,
                            'cnt' => $taps
                        ]
                    );
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }

    protected function watchAd($type)
    {
        /** Get Reward */
        $reward = $this->getApi()
            ->post('https://api.diggergame.app/api/content/intent', [
                'platform' => '2',
                'type' => $type,
            ])
            ->json('result.uid');

        /** Sleep */
        Sleep::for(30)->seconds();

        /** Claim Reward */
        $this->getApi()
            ->post(
                'https://api.diggergame.app/api/content/update',
                [
                    'status' => 'reward',
                    'uid' => $reward,
                ]
            );
    }
}