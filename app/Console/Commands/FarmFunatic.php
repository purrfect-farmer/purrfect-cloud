<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class FarmFunatic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:funatic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Funatic Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        Cache::lock($this->signature)->get(function () {
            /** Start Date */
            $startDate = now();

            /** Retrieve Accounts */
            $accounts = $this->retrieveAccounts();

            /** Tap */
            while ($accounts->isNotEmpty()) {
                $accounts = $this->farmAccounts($accounts);
            }

            /** End Date */
            $endDate = now();

            /** Send Message */
            Helpers::sendFarmingCompletedMessage('funatic', $startDate, $endDate);
        });
    }


    protected function getBaseHeaders()
    {
        return [
            'Origin' => 'https://clicker.funtico.com',
            'Referer' => 'https://clicker.funtico.com/',
            'X-Requested-With' => 'org.telegram.messenger'
        ];
    }

    protected function getApi(Account $account)
    {
        return Http::withHeaders($account->headers)
            ->withHeaders(
                $this->getBaseHeaders()
            )
            ->withUserAgent(
                $account->getUserAgent()
            );
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
        $accessToken = Http::withHeaders(
            $this->getBaseHeaders()
        )
            ->withUserAgent(
                $account->getUserAgent()
            )
            ->post(
                'https://api2.funtico.com/api/lucky-funatic/login?' . $initData,
            )
            ->json('data.token');

        /** Set Headers */
        $account->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    protected function farmAccounts($accounts)
    {
        return $accounts->map(function ($item) {
            try {
                $account = $item['account'];
                $energy = $item['energy'];

                $taps = min($energy, 8 + rand(0, 2));
                $energy -= $taps;

                /** Tap */
                $this->getApi($account)
                    ->post(
                        'https://clicker.api.funtico.com/tap',
                        ['taps' => $taps]
                    );

                /** Return Energy and Account */
                if ($energy > 0) {
                    return compact(
                        'account',
                        'energy'
                    );
                }
            } catch (\Throwable $e) {
                /** Log Error */
                Log::error('Funatic Error', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
            }
        })->filter();
    }

    protected function retrieveAccounts()
    {
        return Account::farmer('funatic')
            ->connected()
            ->get()->map(function (Account $account) {
                try {
                    /** Set Auth */
                    $this->setAuth($account);


                    /** Daily Bonus */
                    $dailyBonus = $this->getApi($account)->get('https://api2.funtico.com/api/lucky-funatic/daily-bonus/config')->json('data');

                    /** Claim Daily-Bonus */
                    if ($dailyBonus['cooldown'] === 0) {
                        $this->getApi($account)->withBody('')->post(
                            'https://api2.funtico.com/api/lucky-funatic/daily-bonus/claim'
                        );
                    }

                    /** Get Boosters */
                    $boosters = $this->getApi($account)->get('https://clicker.api.funtico.com/boosters')->json('data');
                    $availableBoosters = collect($boosters)->filter(
                        fn($item) => (
                            $item['price'] === 0 &&
                            $item['isActive'] === false &&
                            $item['cooldownLeft'] === 0 &&
                            $item['usagesLeft'] !== 0
                        )
                    );

                    /** Purchase Booster */
                    if ($availableBoosters->isNotEmpty()) {
                        $availableBoosters->each(function ($booster) use ($account) {
                            /** Activate Booster */
                            $this->getApi($account)->post(
                                'https://clicker.api.funtico.com/boosters/activate',
                                [
                                    'boosterType' => $booster['type']
                                ]
                            );
                        });
                    }


                    /** Get Game */
                    $game = $this->getApi($account)->get('https://clicker.api.funtico.com/game')->json('data');

                    /** Balance */
                    $balance = $game['funz']['currentFunzBalance'];

                    /** Cards */
                    $cards = $this->getApi($account)->get('https://api2.funtico.com/api/lucky-funatic/cards')->json('data');

                    /** Upgradeable Cards */
                    $upgradableCards = collect($cards)->filter(
                        fn($item) => (
                            $item['buyOrUpgradeCost'] <= $balance &&
                            $item['isMaxLevelReached'] === false &&
                            $item['isComingSoon'] === false &&
                            collect(
                                $item['buyOrUpgradeRequirements']
                            )
                            ->every(
                                fn($dep) => $dep['isMissing'] === false
                            )
                        )
                    );

                    /** Level Zero Cards */
                    $levelZeroCards = $upgradableCards->filter(
                        fn($card) => $card['level'] === null
                    );

                    /** Collection */
                    $collection = $levelZeroCards->isNotEmpty()
                        ? $levelZeroCards
                        : $upgradableCards;

                    /** Random Card */
                    $card = $collection->isNotEmpty() ? $collection->random() : null;

                    if ($card) {
                        $isUpgrade = $card['level'] !== null;

                        /** Buy or Upgrade Card */
                        $this->getApi($account)->post(
                            $isUpgrade ?
                                'https://api2.funtico.com/api/lucky-funatic/upgrade-card' :
                                'https://api2.funtico.com/api/lucky-funatic/buy-card',
                            [
                                'cardId' => $card['id']
                            ]
                        );
                    }

                    /** Energy */
                    $energy = $game['energy']['currentEnergyBalance'];

                    /** Return Energy and Account */
                    if ($energy > 0) {
                        return compact(
                            'account',
                            'energy'
                        );
                    }
                } catch (\Throwable $e) {
                    /** Disconnect Account */
                    $account->disconnect();

                    /** Log Error */
                    Log::error('Funatic Error', [
                        'message' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                }
            })->filter();
    }
}
