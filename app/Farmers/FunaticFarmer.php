<?php
namespace App\Farmers;

class FunaticFarmer extends BaseFarmer
{

    protected $key = 'funatic';
    protected $origin = 'https://clicker.funtico.com';
    protected $delay = 1;

    protected function setAuth()
    {
        /** Init Data */
        $initData = $this->farmer->getInitData();

        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->post(
                'https://api2.funtico.com/api/lucky-funatic/login?' . $initData,
            )
            ->json('data.token');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    public function process()
    {
        try {
            /** Daily Bonus */
            $dailyBonus = $this->getApi()->get('https://api2.funtico.com/api/lucky-funatic/daily-bonus/config')->json('data');

            /** Claim Daily-Bonus */
            if ($dailyBonus['cooldown'] === 0) {
                $this->getApi()->withBody('')->post(
                    'https://api2.funtico.com/api/lucky-funatic/daily-bonus/claim'
                );
            }

            /** Get Boosters */
            $boosters = $this->getApi()->get('https://clicker.api.funtico.com/boosters')->json('data');
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
                $availableBoosters->each(function ($booster) {
                    /** Activate Booster */
                    $this->getApi()->post(
                        'https://clicker.api.funtico.com/boosters/activate',
                        [
                            'boosterType' => $booster['type']
                        ]
                    );
                });
            }


            /** Get Game */
            $game = $this->getApi()->get('https://clicker.api.funtico.com/game')->json('data');

            /** Balance */
            $balance = $game['funz']['currentFunzBalance'];

            /** Cards */
            $cards = $this->getApi()->get('https://api2.funtico.com/api/lucky-funatic/cards')->json('data');

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
                $this->getApi()->post(
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
            while ($energy > 0) {
                $taps = min($energy, 8 + rand(0, 2));
                $energy -= $taps;

                /** Tap */
                $this->getApi()
                    ->post(
                        'https://clicker.api.funtico.com/tap',
                        ['taps' => $taps]
                    );
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Refetch Auth or Disconnect Farmer */
            $this->refetchAuthOrDisconnect();
        }
    }

}