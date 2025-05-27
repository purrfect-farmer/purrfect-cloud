<?php
namespace App\Farmers;

use App\Helpers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

class MatchQuestFarmer extends BaseFarmer
{

    protected $key = 'matchquest';
    protected $origin = 'https://tgapp.matchain.io';

    protected function setAuth()
    {
        /** Get Data */
        $data = [
            'initData' => $this->farmer->getInitData(),
            'initDataUnsafe' => $this->farmer->getInitDataUnsafe(),
        ];

        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->post('https://tgapp-api.matchain.io/api/tgapp/v1/user/login', [
                'tg_login_params' => $data['initData'],
                'uid' => $data['initDataUnsafe']['user']['id'] ?? '',
                'first_name' => $data['initDataUnsafe']['user']['first_name'] ?? '',
                'last_name' => $data['initDataUnsafe']['user']['last_name'] ?? '',
                'user_name' => $data['initDataUnsafe']['user']['username'] ?? '',
            ])
            ->json('data.token');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader($accessToken);
    }

    public function process()
    {
        try {
            /** UID */
            $uid = $this->farmer->getInitDataUnsafe()['user']['id'];

            /** Rewards */
            $rewards = $this->getApi()->post(
                'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward',
                ['uid' => $uid]
            )->json('data');


            /** Start Farming */
            if ($rewards['reward'] === 0) {
                /** Start Farming */
                $this->getApi()->post(
                    'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward/farming',
                    ['uid' => $uid]
                )->json('data');
            }

            /** Can Claim */ else if (
                now()->isAfter(Carbon::createFromTimestampMs($rewards["next_claim_timestamp"]))
            ) {
                /** Claim Previous Farming */
                $this->getApi()->post(
                    'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward/claim',
                    ['uid' => $uid]
                )->json('data');

                /** Start Farming */
                $this->getApi()->post(
                    'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward/farming',
                    ['uid' => $uid]
                )->json('data');
            }


            /** User */
            $user = $this->getApi()->post(
                'https://tgapp-api.matchain.io/api/tgapp/v1/user/profile',
                ['uid' => $uid]
            )->json('data');

            /** Daily Tasks */
            $dailyTasks = $this->getApi()->get(
                'https://tgapp-api.matchain.io/api/tgapp/v1/daily/task/status'
            )->json('data');


            $initialBalance = $user['Balance'] / 1000;
            $balance = $initialBalance;
            $hasPurchasedDailyBoost = false;

            foreach ($dailyTasks as $task) {
                /** Prevent Purchase */
                if ($task["type"] === "daily" && $hasPurchasedDailyBoost) {
                    continue;
                }

                for ($i = $task["current_count"]; $i < $task["task_count"]; $i++) {
                    if ($balance >= $task["point"]) {
                        try {
                            /** Purchase */
                            $isSuccess = $this->getApi()->post(
                                'https://tgapp-api.matchain.io/api/tgapp/v1/daily/task/purchase',
                                [
                                    'uid' => $uid,
                                    'type' => $task["type"]
                                ]
                            )->json('data');

                            if (!$isSuccess)
                                break;

                            /** Update Balance */
                            $balance -= $task["point"];
                        } catch (\Throwable $e) {
                            $this->logError($e);
                        }
                    }
                }

                /** Prevent Purchasing Again */
                if ($task["type"] === "daily") {
                    $hasPurchasedDailyBoost = true;
                }
            }

            /** Game Rule */
            $gameRule = $this->getApi()->get(
                'https://tgapp-api.matchain.io/api/tgapp/v1/game/rule'
            )->json('data');

            $tickets = $gameRule['game_count'];

            /** Return Tickets and Farmer */
            if ($tickets > 0) {

                $points = intval(
                    Helpers::extraGamePoints(90)
                );

                $game = $this->getApi()
                    ->get(
                        'https://tgapp-api.matchain.io/api/tgapp/v1/game/play'
                    )->json('data');


                /** Delay */
                Sleep::for(30)->seconds();

                $this->getApi()
                    ->post(
                        'https://tgapp-api.matchain.io/api/tgapp/v1/game/claim',
                        [
                            'game_id' => $game['game_id'],
                            'points' => $points
                        ]
                    )->json('data');
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Refetch Auth or Disconnect Farmer */
            $this->refetchAuthOrDisconnect();
        }
    }
}