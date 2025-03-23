<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Models\Farmer;
use Illuminate\Console\Command;

class FarmFunatic extends Command
{
    use FarmerTrait;

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
     * The delay in seconds for all requests.
     *
     * @var int
     */
    protected $delay = 1;

    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://clicker.funtico.com';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Retrieve Farmers */
            $farmers = $this->retrieveFarmers();

            /** Tap */
            while ($farmers->isNotEmpty()) {
                $farmers = $this->farmFarmers($farmers);
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
                'https://api2.funtico.com/api/lucky-funatic/login?' . $initData,
            )
            ->json('data.token');

        /** Set Headers */
        $farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    protected function farmFarmers($farmers)
    {
        return $farmers->map(function ($item) {
            try {
                $farmer = $item['account'];
                $energy = $item['energy'];

                $taps = min($energy, 8 + rand(0, 2));
                $energy -= $taps;

                /** Tap */
                $this->getApi($farmer)
                    ->post(
                        'https://clicker.api.funtico.com/tap',
                        ['taps' => $taps]
                    );

                /** Return Energy and Farmer */
                if ($energy > 0) {
                    return compact(
                        'account',
                        'energy'
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
        return Farmer::farmer('funatic')
            ->connected()
            ->get()->map(function (Farmer $farmer) {
                try {
                    /** Daily Bonus */
                    $dailyBonus = $this->getApi($farmer)->get('https://api2.funtico.com/api/lucky-funatic/daily-bonus/config')->json('data');

                    /** Claim Daily-Bonus */
                    if ($dailyBonus['cooldown'] === 0) {
                        $this->getApi($farmer)->withBody('')->post(
                            'https://api2.funtico.com/api/lucky-funatic/daily-bonus/claim'
                        );
                    }

                    /** Get Boosters */
                    $boosters = $this->getApi($farmer)->get('https://clicker.api.funtico.com/boosters')->json('data');
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
                        $availableBoosters->each(function ($booster) use ($farmer) {
                            /** Activate Booster */
                            $this->getApi($farmer)->post(
                                'https://clicker.api.funtico.com/boosters/activate',
                                [
                                    'boosterType' => $booster['type']
                                ]
                            );
                        });
                    }


                    /** Get Game */
                    $game = $this->getApi($farmer)->get('https://clicker.api.funtico.com/game')->json('data');

                    /** Balance */
                    $balance = $game['funz']['currentFunzBalance'];

                    /** Cards */
                    $cards = $this->getApi($farmer)->get('https://api2.funtico.com/api/lucky-funatic/cards')->json('data');

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
                        $this->getApi($farmer)->post(
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

                    /** Return Energy and Farmer */
                    if ($energy > 0) {
                        return compact(
                            'account',
                            'energy'
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
