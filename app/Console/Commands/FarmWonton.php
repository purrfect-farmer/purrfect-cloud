<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\Farmer;
use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

class FarmWonton extends Command
{
    use Farmer;

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
            /** Retrieve Accounts */
            $accounts = $this->retrieveAccounts();

            /** Game */
            if ($accounts->isNotEmpty()) {
                $this->farmAccounts($accounts);
            }
        });
    }

    protected function setAuth(Account $account)
    {
        /** Get Access Token */
        $accessToken = $this->getBaseApi($account)
            ->post('https://wonton.food/api/v1/user/auth', [
                'initData' => $account->telegram_web_app['initData'],
                'inviteCode' => '',
                'newUserPromoteCode' => ''
            ])
            ->json('tokens.accessToken');

        /** Set Headers */
        $account->setAuthorizationHeader('bearer ' . $accessToken);
    }

    protected function farmAccounts($accounts)
    {
        $games = $accounts->map(function ($item) {
            try {
                $account = $item['account'];
                $selectedSkin = $item['selectedSkin'];
                $perItem = collect($selectedSkin['stats'])->map('intval')->max();
                $points = intval(
                    Helpers::extraGamePoints(70) * $perItem
                );


                $bonusRound = $this->getApi($account)
                    ->post(
                        'https://wonton.food/api/v1/user/start-game'
                    )->json('bonusRound');

                return compact(
                    'account',
                    'points',
                    'bonusRound'
                );
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e);
            }
        })->filter();

        /** Delay */
        Sleep::for(15 + rand(0, 5))->seconds();

        /** Claim Points */
        $games->each(function ($item) {
            $account = $item['account'];
            $points = $item['points'];
            $bonusRound = $item['bonusRound'];

            $this->getApi($account)
                ->post(
                    'https://wonton.food/api/v1/user/finish-game',
                    [
                        'hasBonus' => $bonusRound,
                        'points' => $points
                    ]
                )->json();
        });
    }

    protected function retrieveAccounts()
    {
        return Account::farmer('wonton')
            ->connected()
            ->get()->map(function (Account $account) {
                try {
                    /** Set Auth */
                    $this->setAuth($account);

                    /** Daily Check-In */
                    $this->getApi($account)->get('https://wonton.food/api/v1/checkin')->json();

                    /** Farming Status */
                    $farming = $this->getApi($account)->get('https://wonton.food/api/v1/user/farming-status')->json();

                    /** Should Start Farming? */
                    $shoudStartFarming = !isset($farming['finishAt']) || $farming['claimed'];

                    if ($shoudStartFarming) {
                        /** Start Farming */
                        $this->getApi($account)->post('https://wonton.food/api/v1/user/start-farming')->json();
                    }
                    /** Can Claim */
                    else if (now()->isAfter($farming['finishAt'])) {
                        /** Claim Previous Farming */
                        $this->getApi($account)->post('https://wonton.food/api/v1/user/farming-claim')->json();

                        /** Start Farming */
                        $this->getApi($account)->post('https://wonton.food/api/v1/user/start-farming')->json();
                    }

                    /** Use Top Shop-Items */
                    $shopItems = collect(
                        $this->getApi($account)
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
                            collect($current['stats'])->map('intval')->max()  >
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
                        $this->getApi($account)
                            ->post('https://wonton.food/api/v1/shop/use-item', [
                                'itemId' => $topSkin['id']
                            ])->json();

                        $selectedSkin = $topSkin;
                    }

                    /** Use Top Bowl */
                    if ($topBowl && $topBowl['bowlDisplay'] === false) {
                        $this->getApi($account)
                            ->post('https://wonton.food/api/v1/shop/use-item', [
                                'itemId' => $topBowl['id']
                            ])->json();

                        $selectedBowl = $topBowl;
                    }


                    /** Tasks */
                    $tasksData = $this->getApi($account)
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
                        $this->getApi($account)
                            ->post('https://wonton.food/api/v1/task/verify', [
                                'taskId' => $pendingTasks->random()['id']
                            ])->json();
                    }

                    /** Claim a random Task */
                    if ($unclaimedTasks->isNotEmpty()) {
                        $this->getApi($account)
                            ->post('https://wonton.food/api/v1/task/claim', [
                                'taskId' => $unclaimedTasks->random()['id']
                            ])->json();
                    }

                    /** Claim Task Progress */
                    if ($taskProgress >= 3) {
                        $this->getApi($account)
                            ->get('https://wonton.food/api/v1/task/claim-progress')
                            ->json();
                    }




                    /** Tasks */
                    $badges = collect(
                        $this->getApi($account)
                            ->get('https://wonton.food/api/v1/badge/list')
                            ->json('badges')
                    )->values();

                    $unclaimedBadges = $badges->filter(
                        fn($item) => intval($item['progress']) >= intval($item['target'])
                    )->values();

                    /** Claim Random Badge */
                    if ($unclaimedBadges->isNotEmpty()) {
                        $this->getApi($account)
                            ->post('https://wonton.food/api/v1/badge/claim', [
                                'type' => $unclaimedBadges->random()['type']
                            ])->json();
                    }


                    $user = $this->getApi($account)->get('https://wonton.food/api/v1/user')->json();
                    $tickets = $user['ticketCount'];

                    /** Return Tickets and Account */
                    if ($tickets > 0) {
                        return compact(
                            'account',
                            'tickets',
                            'selectedSkin',
                            'selectedBowl',
                        );
                    }
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e);

                    /** Disconnect Account */
                    $account->disconnect();
                }
            })->filter();
    }
}
