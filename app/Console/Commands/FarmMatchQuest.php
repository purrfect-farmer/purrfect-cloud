<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Helpers;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

class FarmMatchQuest extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:match-quest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm MatchQuest Automatically';


    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://tgapp.matchain.io';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Retrieve Farmers */
            $farmers = $this->retrieveFarmers();

            /** Game */
            if ($farmers->isNotEmpty()) {
                $this->farmFarmers($farmers);
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
        /** Get Access Token */
        $accessToken = $this->getBaseApi($farmer)
            ->post('https://tgapp-api.matchain.io/api/tgapp/v1/user/login', [
                'tg_login_params' => $data['initData'],
                'uid' => $data['initDataUnsafe']['user']['id'] ?? '',
                'first_name' => $data['initDataUnsafe']['user']['first_name'] ?? '',
                'last_name' => $data['initDataUnsafe']['user']['last_name'] ?? '',
                'user_name' => $data['initDataUnsafe']['user']['username'] ?? '',
            ])
            ->json('data.token');

        /** Set Headers */
        $farmer->setAuthorizationHeader($accessToken);
    }

    protected function farmFarmers($farmers)
    {
        $games = $farmers->map(function ($item) {
            try {
                $farmer = $item['farmer'];
                $points = intval(
                    Helpers::extraGamePoints(90)
                );

                $game = $this->getApi($farmer)
                    ->post(
                        'https://tgapp-api.matchain.io/api/tgapp/v1/game/play'
                    )->json('data');

                return compact(
                    'farmer',
                    'points',
                    'game'
                );
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $item['farmer']);
            }
        })->filter();


        if ($games->isNotEmpty()) {
            /** Delay */
            Sleep::for(30)->seconds();

            /** Claim Points */
            $games->each(function ($item) {
                $farmer = $item['farmer'];
                $points = $item['points'];
                $game = $item['game'];

                $this->getApi($farmer)
                    ->post(
                        'https://tgapp-api.matchain.io/api/tgapp/v1/game/claim',
                        [
                            'game_id' => $game['game_id'],
                            'points' => $points
                        ]
                    )->json('data');
            });
        }
    }

    protected function retrieveFarmers()
    {
        return Farmer::farmer('match-quest')
            ->connected()
            ->get()->map(function (Farmer $farmer) {
                try {
                    /** UID */
                    $uid = $farmer->telegram_web_app['initDataUnsafe']['user']['id'];

                    /** Rewards */
                    $rewards = $this->getApi($farmer)->post(
                        'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward',
                        ['uid' => $uid]
                    )->json('data');


                    /** Start Farming */
                    if ($rewards['reward'] === 0) {
                        /** Start Farming */
                        $this->getApi($farmer)->post(
                            'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward/farming',
                            ['uid' => $uid]
                        )->json('data');
                    }

                    /** Can Claim */
                    else if (
                        now()->isAfter(Carbon::createFromTimestampMs($rewards["next_claim_timestamp"]))
                    ) {
                        /** Claim Previous Farming */
                        $this->getApi($farmer)->post(
                            'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward/claim',
                            ['uid' => $uid]
                        )->json('data');

                        /** Start Farming */
                        $this->getApi($farmer)->post(
                            'https://tgapp-api.matchain.io/api/tgapp/v1/point/reward/farming',
                            ['uid' => $uid]
                        )->json('data');
                    }


                    /** User */
                    $user = $this->getApi($farmer)->post(
                        'https://tgapp-api.matchain.io/api/tgapp/v1/user/profile',
                        ['uid' => $uid]
                    )->json('data');

                    /** Daily Tasks */
                    $dailyTasks = $this->getApi($farmer)->get(
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
                                    $isSuccess = $this->getApi($farmer)->post(
                                        'https://tgapp-api.matchain.io/api/tgapp/v1/daily/task/purchase',
                                        [
                                            'uid' => $uid,
                                            'type' => $task["type"]
                                        ]
                                    )->json('data');

                                    if (!$isSuccess) break;

                                    /** Update Balance */
                                    $balance -= $task["point"];
                                } catch (\Throwable $e) {
                                    $this->logError($e, $farmer);
                                }
                            }
                        }

                        /** Prevent Purchasing Again */
                        if ($task["type"] === "daily") {
                            $hasPurchasedDailyBoost = true;
                        }
                    }

                    /** Game Rule */
                    $gameRule = $this->getApi($farmer)->get(
                        'https://tgapp-api.matchain.io/api/tgapp/v1/game/rule'
                    )->json('data');

                    $tickets = $gameRule['game_count'];

                    /** Return Tickets and Farmer */
                    if ($tickets > 0) {
                        return compact(
                            'farmer',
                            'tickets',
                            'uid',
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
