<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Helpers;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

class FarmWonton extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:wonton';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Wonton Automatically';


    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://www.wonton.restaurant';


    /**
     * The delay in seconds for all requests.
     *
     * @var int
     */
    protected $delay = 3;

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
     * @return void
     */
    protected function setAuth(Farmer $farmer)
    {
        /** Get Access Token */
        $accessToken = $this->getBaseApi($farmer)
            ->post('https://wonton.food/api/v1/user/auth', [
                'initData' => $farmer->getInitData(),
                'inviteCode' => 'K45JQRG7',
                'newUserPromoteCode' => ''
            ])
            ->json('tokens.accessToken');

        /** Set Headers */
        $farmer->setAuthorizationHeader('bearer ' . $accessToken);
    }

    protected function farmFarmers($farmers)
    {
        $games = $this->runConcurrently(
            $farmers->mapForConcurrency(function ($item) {
                try {
                    $farmer = $item['farmer'];
                    $selectedSkin = $item['selectedSkin'];
                    $perItem = collect($selectedSkin['stats'])->map('intval')->max();
                    $points = intval(
                        Helpers::extraGamePoints(70) * $perItem
                    );

                    $bonusRound = $this->getApi($farmer)
                        ->post(
                            'https://wonton.food/api/v1/user/start-game'
                        )->json('bonusRound');

                    return compact(
                        'farmer',
                        'points',
                        'bonusRound'
                    );
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $item['farmer']);
                }
            })
        )->filter();

        /** Delay */
        Sleep::for(15 + rand(0, 5))->seconds();

        /** Claim Points */
        $this->runConcurrently(
            $games->mapForConcurrency(function ($item) {
                try {

                    $farmer = $item['farmer'];
                    $points = $item['points'];
                    $bonusRound = $item['bonusRound'];

                    $this->getApi($farmer)
                        ->post(
                            'https://wonton.food/api/v1/user/finish-game',
                            [
                                'hasBonus' => $bonusRound,
                                'points' => $points
                            ]
                        )->json();
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $item['farmer']);
                }
            })
        );
    }

    protected function retrieveFarmers()
    {
        return $this->runConcurrently(
            $this->getFarmers()->mapForConcurrency(function (Farmer $farmer) {
                try {
                    /** Daily Check-In */
                    $this->getApi($farmer)->get('https://wonton.food/api/v1/checkin')->json();

                    /** Farming Status */
                    $farming = $this->getApi($farmer)->get('https://wonton.food/api/v1/user/farming-status')->json();

                    /** Should Start Farming? */
                    $shoudStartFarming = !isset($farming['finishAt']) || $farming['claimed'];

                    if ($shoudStartFarming) {
                        /** Start Farming */
                        $this->getApi($farmer)->post('https://wonton.food/api/v1/user/start-farming')->json();
                    }
                    /** Can Claim */ else if (now()->isAfter($farming['finishAt'])) {
                        /** Claim Previous Farming */
                        $this->getApi($farmer)->post('https://wonton.food/api/v1/user/farming-claim')->json();

                        /** Start Farming */
                        $this->getApi($farmer)->post('https://wonton.food/api/v1/user/start-farming')->json();
                    }

                    /** Use Top Shop-Items */
                    $shopItems = collect(
                        $this->getApi($farmer)
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
                        $this->getApi($farmer)
                            ->post('https://wonton.food/api/v1/shop/use-item', [
                                'itemId' => $topSkin['id']
                            ])->json();

                        $selectedSkin = $topSkin;
                    }

                    /** Use Top Bowl */
                    if ($topBowl && $topBowl['bowlDisplay'] === false) {
                        $this->getApi($farmer)
                            ->post('https://wonton.food/api/v1/shop/use-item', [
                                'itemId' => $topBowl['id']
                            ])->json();

                        $selectedBowl = $topBowl;
                    }


                    /** Tasks */
                    $tasksData = $this->getApi($farmer)
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
                        $this->getApi($farmer)
                            ->post('https://wonton.food/api/v1/task/verify', [
                                'taskId' => $pendingTasks->random()['id']
                            ])->json();
                    }

                    /** Claim a random Task */
                    if ($unclaimedTasks->isNotEmpty()) {
                        $this->getApi($farmer)
                            ->post('https://wonton.food/api/v1/task/claim', [
                                'taskId' => $unclaimedTasks->random()['id']
                            ])->json();
                    }

                    /** Claim Task Progress */
                    if ($taskProgress >= 3) {
                        $this->getApi($farmer)
                            ->get('https://wonton.food/api/v1/task/claim-progress')
                            ->json();
                    }




                    /** Tasks */
                    $badges = collect(
                        $this->getApi($farmer)
                            ->get('https://wonton.food/api/v1/badge/list')
                            ->json('badges')
                    )->values();

                    $unclaimedBadges = $badges->filter(
                        fn($item) => intval($item['progress']) >= intval($item['target'])
                    )->values();

                    /** Claim Random Badge */
                    if ($unclaimedBadges->isNotEmpty()) {
                        $this->getApi($farmer)
                            ->post('https://wonton.food/api/v1/badge/claim', [
                                'type' => $unclaimedBadges->random()['type']
                            ])->json();
                    }


                    $user = $this->getApi($farmer)->get('https://wonton.food/api/v1/user')->json();
                    $tickets = $user['ticketCount'];

                    /** Return Tickets and Farmer */
                    if ($tickets > 0) {
                        return compact(
                            'farmer',
                            'tickets',
                            'selectedSkin',
                            'selectedBowl',
                        );
                    }
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $farmer);

                    /** Refetch Auth or Disconnect Farmer */
                    $this->refetchAuthOrDisconnect($farmer);
                }
            })
        )->filter();
    }
}
