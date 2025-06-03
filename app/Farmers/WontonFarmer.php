<?php
namespace App\Farmers;

use Illuminate\Support\Sleep;
use App\Helpers;

class WontonFarmer extends BaseFarmer
{

    protected $key = 'wonton';
    protected $origin = 'https://www.wonton.restaurant';
    protected $delay = 5;
    protected $shouldSetAuth = true;

    protected function setAuth()
    {
        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->post('https://wonton.food/api/v1/user/auth', [
                'initData' => $this->farmer->getInitData(),
                'inviteCode' => 'K45JQRG7',
                'newUserPromoteCode' => ''
            ])
            ->json('tokens.accessToken');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader('bearer ' . $accessToken);
    }

    public function process()
    {
        try {
            /** Daily Check-In */
            $this->getApi()->get('https://wonton.food/api/v1/checkin')->json();

            /** Farming Status */
            $farming = $this->getApi()->get('https://wonton.food/api/v1/user/farming-status')->json();

            /** Should Start Farming? */
            $shoudStartFarming = !isset($farming['finishAt']) || $farming['claimed'];

            if ($shoudStartFarming) {
                /** Start Farming */
                $this->getApi()->post('https://wonton.food/api/v1/user/start-farming')->json();
            }
            /** Can Claim */ else if (now()->isAfter($farming['finishAt'])) {
                /** Claim Previous Farming */
                $this->getApi()->post('https://wonton.food/api/v1/user/farming-claim')->json();

                /** Start Farming */
                $this->getApi()->post('https://wonton.food/api/v1/user/start-farming')->json();
            }

            /** Use Top Shop-Items */
            $shopItems = collect(
                $this->getApi()
                    ->get('https://wonton.food/api/v1/shop/list')
                    ->json('shopItems')
            );

            $items = $shopItems->filter(fn($item) => intval($item['inventory']) > 0)->values();
            $skins = $items->filter(fn($item) => intval($item['farmingPower']) !== 0)->values();
            $bowls = $items->filter(fn($item) => intval($item['farmingPower']) === 0)->values();



            $selectedSkin = $skins->first(fn($item) => $item['inUse']);
            $selectedBowl = $bowls->first(fn($item) => $item['bowlDisplay']);

            /** Top Skin */
            $topSkin =
                $skins->isNotEmpty()
                ? $skins->reduce(
                    fn($result, $current) =>
                    collect($current['stats'])->map('intval')->max() >
                    collect($result['stats'])->map('intval')->max()
                    ? $current
                    : $result,
                    $skins[0]
                )
                : null;

            /** Top Bowl */
            $topBowl =
                $bowls->isNotEmpty()
                ? $bowls->reduce(
                    fn($result, $current) =>
                    intval($current['value']) > intval($result['value'])
                    ? $current
                    : $result,
                    $bowls[0]
                )
                : null;


            /** Use Top Skin */
            if ($topSkin && $topSkin['inUse'] === false) {
                $this->getApi()
                    ->post('https://wonton.food/api/v1/shop/use-item', [
                        'itemId' => $topSkin['id']
                    ])->json();

                $selectedSkin = $topSkin;
            }

            /** Use Top Bowl */
            if ($topBowl && $topBowl['bowlDisplay'] === false) {
                $this->getApi()
                    ->post('https://wonton.food/api/v1/shop/use-item', [
                        'itemId' => $topBowl['id']
                    ])->json();

                $selectedBowl = $topBowl;
            }


            /** Tasks */
            $tasksData = $this->getApi()
                ->get('https://wonton.food/api/v1/task/list')
                ->json();
            $taskProgress = $tasksData['taskProgress'];
            $tasks = collect(
                $tasksData['tasks']
            );

            $pendingTasks = $tasks->filter(fn($item) => $item['status'] === 0);
            $unclaimedTasks = $tasks->filter(fn($item) => $item['status'] === 1);

            /** Start a random Task */
            if ($pendingTasks->isNotEmpty()) {
                $this->getApi()
                    ->post('https://wonton.food/api/v1/task/verify', [
                        'taskId' => $pendingTasks->random()['id']
                    ])->json();
            }

            /** Claim a random Task */
            if ($unclaimedTasks->isNotEmpty()) {
                $this->getApi()
                    ->post('https://wonton.food/api/v1/task/claim', [
                        'taskId' => $unclaimedTasks->random()['id']
                    ])->json();
            }

            /** Claim Task Progress */
            if ($taskProgress >= 3) {
                $this->getApi()
                    ->get('https://wonton.food/api/v1/task/claim-progress')
                    ->json();
            }




            /** Tasks */
            $badges = collect(
                $this->getApi()
                    ->get('https://wonton.food/api/v1/badge/list')
                    ->json('badges')
            )->values();

            $unclaimedBadges = $badges->filter(
                fn($item) => intval($item['progress']) >= intval($item['target'])
            )->values();

            /** Claim Random Badge */
            if ($unclaimedBadges->isNotEmpty()) {
                $this->getApi()
                    ->post('https://wonton.food/api/v1/badge/claim', [
                        'type' => $unclaimedBadges->random()['type']
                    ])->json();
            }


            $user = $this->getApi()->get('https://wonton.food/api/v1/user')->json();
            $tickets = $user['ticketCount'];

            /** Game */
            if ($tickets > 0) {
                $perItem = collect($selectedSkin['stats'])->map('intval')->max();
                $points = intval(
                    Helpers::extraGamePoints(70) * $perItem
                );

                $bonusRound = $this->getApi()
                    ->post(
                        'https://wonton.food/api/v1/user/start-game'
                    )->json('bonusRound');

                /** Delay */
                Sleep::for(15 + rand(0, 5))->seconds();

                $this->getApi()
                    ->post(
                        'https://wonton.food/api/v1/user/finish-game',
                        [
                            'hasBonus' => $bonusRound,
                            'points' => $points
                        ]
                    )->json();
            }


        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }
}