<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\Farmer;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

class FarmDigger extends Command
{
    use Farmer;

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
            /** Retrieve Accounts */
            $accounts = $this->retrieveAccounts();

            /** Rewards */
            $rewards = $accounts->filter(fn($item) => isset($item['reward']));

            /** Claim Rewards */
            if ($rewards->isNotEmpty()) {
                $this->claimRewards($rewards);
            }

            /** Taps */
            $taps = $accounts->filter(fn($item) => $item['energy'] > 0);

            /** Claim Taps */
            while ($taps->isNotEmpty()) {
                $taps = $this->farmAccounts($taps);
            }
        });
    }

    /**
     *  Set Authorization
     * @param \App\Models\Account $account
     * @return void
     */
    protected function setAuth(Account $account)
    {
        /** Init Data */
        $initData = $account->telegram_web_app['initData'];

        /** Get Access Token */
        $accessToken = $this->getBaseApi($account)
            ->post(
                'https://api.diggergame.app/api/auth',
                [
                    'init_data' => $initData,
                    'platform' => 'android',
                ]
            )
            ->json('result.auth.token');

        /** Set Headers */
        $account->setAuthorizationHeader('Bearer ' . $accessToken);
    }


    protected function claimRewards($accounts)
    {
        /** Sleep */
        Sleep::for(10)->seconds();

        /** Claim Reward */
        $accounts->each(function ($item) {
            try {
                $account = $item['account'];
                $reward = $item['reward'];

                /** Claim Reward */
                $this->getApi($account)
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

    /** Farm Accounts */
    protected function farmAccounts($accounts)
    {
        return $accounts->map(function ($item) {
            try {
                $account = $item['account'];
                $energy = $item['energy'];
                $uid = $item['uid'];

                $taps = min($energy, 10);
                $energy -= $taps;

                /** Tap */
                $this->getApi($account)
                    ->post(
                        'https://api.diggergame.app/api/play/tap',
                        [
                            'uid' => $uid,
                            'cnt' => $taps
                        ]
                    );

                /** Return Energy and Account */
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

    protected function retrieveAccounts()
    {
        return Account::farmer('digger')
            ->connected()
            ->get()->map(function (Account $account) {
                try {
                    /** Dig */
                    try {
                        $this->getApi($account)
                            ->post('https://api.diggergame.app/api/play/dig', [
                                'init_data' => $account->telegram_web_app['initData'],
                                'platform' => 'android'
                            ]);
                    } catch (\Throwable $e) {
                        $this->logError($e, $account);
                    }

                    /** Get Tasks */
                    $tasks = collect(
                        $this->getApi($account)
                            ->get('https://api.diggergame.app/api/user-task/list')
                            ->json('result')
                    );

                    $pendingTasks = $tasks->filter(fn($item) => $item['status'] === 'progress');
                    $unclaimedTasks = $tasks->filter(fn($item) => $item['status'] === 'waiting_reward');

                    /** Start a random Task */
                    if ($pendingTasks->isNotEmpty()) {
                        $this->getApi($account)
                            ->post('https://api.diggergame.app/api/user-task/update', [
                                'type' => $pendingTasks->random()['type']
                            ])
                            ->json('result');
                    }

                    /** Claim a random Task */
                    if ($unclaimedTasks->isNotEmpty()) {
                        $this->getApi($account)
                            ->post('https://api.diggergame.app/api/user-task/check', [
                                'type' => $unclaimedTasks->random()['type']
                            ])
                            ->json('result');
                    }


                    /** Get User */
                    $user = $this->getApi($account)
                        ->get('https://api.diggergame.app/api/me')
                        ->json('result');

                    /** Balance */
                    $balance = $user['coin_cnt'];

                    /** Cards */
                    $cards = $this->getApi($account)
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
                        $this->getApi($account)->post(
                            'https://api.diggergame.app/api/user/card/buy',
                            ['card_id' => $selectedCard['card']['id']]
                        );
                    }


                    /** Get Chest Status */
                    $chestStatus = collect(
                        $this->getApi($account)
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
                            $this->getApi($account)
                            ->post('https://api.diggergame.app/api/content/intent', [
                                'platform' => '2',
                                'type' => static::CHEST_TYPES[$viewableChests->random()['chest_id']],
                            ])
                            ->json('result.uid')
                        ) : null;


                    /** Get Chests */
                    $chests = collect(
                        $this->getApi($account)
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

                    /** Return Energy and Account */
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
                    $this->logError($e, $account);

                    /** Refetch Auth or Disconnect Account */
                    $this->refetchAuthOrDisconnect($account);
                }
            })->filter();
    }
}
