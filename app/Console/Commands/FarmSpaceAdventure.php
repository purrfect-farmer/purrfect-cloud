<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\SpaceAdventureFarmer;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FarmSpaceAdventure extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:space-adventure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Space Adventure Automatically';


    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://space-adventure.online';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            $this->getFarmers()->mapConcurrently(function (Farmer $farmer) {
                try {
                    $helper = new SpaceAdventureFarmer(
                        $farmer,
                        fn() => $this->getBaseApi($farmer)
                    );

                    $helper
                        ->withoutCookies()
                        ->withoutSignature()
                        ->makeRequest(
                            fn(PendingRequest $api) => $api->get('https://space-adventure.online/sanctum/csrf-cookie')
                        );


                    /** Get Access Token */
                    $accessToken = $helper
                        ->withoutSignature()
                        ->makeRequest(
                            fn(PendingRequest $api) =>
                            $api->asForm()
                                ->post(
                                    'https://space-adventure.online/api/auth/telegram',
                                    $farmer->getInitDataParsed()
                                )
                        )->json('token');

                    /** Update Authorization Header */
                    $farmer->setAuthorizationHeader('Bearer ' . $accessToken)->save();


                    $user = $helper
                        ->makeAuthRequest(
                            fn(PendingRequest $api) => $api->get('https://space-adventure.online/api/user/get')
                        )
                        ->json('user');

                    $boosts = $helper
                        ->makeAuthRequest(
                            fn(PendingRequest $api) => $api->get('https://space-adventure.online/api/boost/get/')
                        )
                        ->collect('list');


                    $status = $this->getStatus($user);

                    if ($status['canSkipTutorial']) {
                        $user = $helper
                            ->makeAuthRequest(
                                fn(PendingRequest $api) => $api->put('https://space-adventure.online/api/user/settings/tutorial/')
                            )
                            ->json('user');
                        $status = $this->getStatus($user);
                    }

                    if ($status['canReadNews']) {
                        $user = $helper
                            ->makeAuthRequest(
                                fn(PendingRequest $api) => $api->put('https://space-adventure.online/api/user/settings/read-news')
                            )
                            ->json('user');
                        $status = $this->getStatus($user);
                    }

                    if ($status['canClaimDailyReward']) {
                        $user = $this->makeAdsRequest(
                            $helper,
                            'daily_activity',
                            fn(PendingRequest $api) => $api->post(
                                'https://space-adventure.online/api/dayli/claim_activity/'
                            )
                        )->json('user');
                        $status = $this->getStatus($user);
                    }

                    if ($status['canClaim']) {
                        $user = $helper
                            ->makeAuthRequest(
                                fn(PendingRequest $api) => $api->post(
                                    'https://space-adventure.online/api/game/claiming/'
                                )
                            )->json('user');
                        $status = $this->getStatus($user);
                    }

                    if ($status['canSpin']) {
                        $user = $this->makeAdsRequest(
                            $helper,
                            'spin_roulete',
                            fn(PendingRequest $api) => $api->post(
                                'https://space-adventure.online/api/roulette/buy/',
                                ['method' => 'free']
                            )
                        )->json('user');
                        $status = $this->getStatus($user);
                    }

                    if ($status['canBuyShield']) {
                        $user = $this->shopFreeItem($helper, $boosts, 'shield')->json('user');
                        $status = $this->getStatus($user);
                    }

                    if ($status['canBuyImmunity']) {
                        $user = $this->shopFreeItem($helper, $boosts, 'immunity')->json('user');
                        $status = $this->getStatus($user);
                    }

                    if ($status['canBuyFuel']) {
                        $user = $this->shopFreeItem($helper, $boosts, 'fuel')->json('user');
                        $status = $this->getStatus($user);
                    }

                    $tasks = $helper
                        ->makeAuthRequest(
                            fn(PendingRequest $api) => $api->get('https://space-adventure.online/api/tasks/get?category=sponsors')
                        )
                        ->collect('listActive');

                    $videoTasksCount = intval($user['video_tasks']);
                    $adsTask = $tasks->first(
                        fn($item) =>
                        $item['status'] === 'not_completed' &&
                        Str::of($item['title'])->contains("Watch 3 ads")
                    );

                    if ($adsTask) {
                        for ($i = $videoTasksCount; $i < 3; $i++) {
                            $this->makeAdsRequest(
                                $helper,
                                'tasks_reward',
                                fn(PendingRequest $api) => $api->put(
                                    'https://space-adventure.online/api/tasks/reward-video/'
                                )
                            );
                        }
                    }

                    $balance = intval($user['balance']);
                    $gems = intval($user['gems']);
                    $levelBoosts = $boosts
                        ->filter(
                            fn($item) => $item['type'] === 'level_boost'
                        )->map(
                            fn($item) => [
                                ...$item,
                                'next_level' => $item['level_list'][$item['level_current'] + 1] ?? null
                            ]
                        );

                    $availableBoosts = $levelBoosts->filter(
                        fn($item) =>
                        $item['next_level'] !== null &&
                        $item['next_level']['price_coin'] <= $balance ||
                        $item['next_level']['price_gems'] <= $gems
                    );

                    $maxLevel = $availableBoosts->max('level_current');
                    $sameLevel = $availableBoosts->every('level_current', $maxLevel);

                    $upgradableBoosts = $availableBoosts->filter(
                        fn($item) => $sameLevel || $item['level_current'] < $maxLevel
                    );

                    if ($upgradableBoosts->isNotEmpty()) {
                        $random = $upgradableBoosts->random();
                        $method = $random['next_level']['price_gems'] <= $gems ? 'gems' : 'coin';

                        $helper
                            ->makeAuthRequest(
                                fn(PendingRequest $api) => $api->post(
                                    'https://space-adventure.online/api/boost/buy/',
                                    [
                                        'method' => $method,
                                        'id' => $random['id']
                                    ]
                                )
                            );
                    }


                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $farmer);

                    /** Refetch Auth or Disconnect Farmer */
                    $this->refetchAuthOrDisconnect($farmer);
                }
            });
        });
    }

    /**
     * Shop Free Item
     * @param \App\Farmers\SpaceAdventureFarmer $helper
     * @param \Illuminate\Support\Collection $boosts
     * @param string $type
     * @return \Illuminate\Http\Client\Response
     */
    protected function shopFreeItem(
        SpaceAdventureFarmer $helper,
        Collection $boosts,
        string $type
    ) {
        $shopItem = $boosts->first(
            fn($item) => $item['single_type'] === $type
        );

        return $this->makeAdsRequest(
            $helper,
            'shop_free_' . $type,
            fn(PendingRequest $api) => $api->post(
                'https://space-adventure.online/api/boost/buy/',
                [
                    'method' => 'free',
                    'id' => $shopItem['id']
                ]
            )
        );
    }

    /**
     * Make Ads Request
     * @param \App\Farmers\SpaceAdventureFarmer $helper
     * @param string $type
     * @param Closure $callback
     * @return \Illuminate\Http\Client\Response
     */
    protected function makeAdsRequest(
        SpaceAdventureFarmer $helper,
        $type,
        $callback
    ) {
        $helper->makeAuthRequest(
            fn(PendingRequest $api) => $api->post(
                'https://space-adventure.online/api/user/get_ads/',
                ['type' => $type]
            )
        );

        return $helper->makeAuthRequest($callback);
    }


    /**
     * Get Status
     * @param array $user
     * @return array
     */
    protected function getStatus($user)
    {
        $timePassed = $this->createDate($user["claimed_last"])->diffInSeconds();
        $lowFuelInSeconds = 10 * 60; // Ten Minutes
        $remainingFuelInSeconds = now()->diffInSeconds(
            $this->createDate(
                $user["fuel_last_at"] + $user["fuel"] * 1000
            )
        );

        $unclaimed = $user["claim"] * $timePassed;
        $canBuyFuel = $remainingFuelInSeconds <= $lowFuelInSeconds &&
            now()->isAfter(
                $this->createDate($user["fuel_free_at"])
            );

        $canClaim = $unclaimed >= $user["claim_max"];

        $canBuyShield = $user["shield_damage"] === 1 &&
            now()->isAfter(
                $this->createDate($user["shield_free_at"])
            );

        $canBuyImmunity =
            now()->isAfter(
                $this->createDate($user["shield_immunity_at"])
            ) &&
            now()->isAfter(
                $this->createDate($user["shield_free_immunity_at"])
            );

        $canSpin = now()->isAfter(
            $this->createDate($user["spin_after_at"])
        );

        $canSkipTutorial = $user["tutorial"] !== true;

        $canReadNews = $user["new_post"] !== true;

        $canClaimDailyReward = now()->isAfter(
            $this->createDate($user["daily_next_at"])
        );

        return compact(
            'canBuyFuel',
            'canClaim',
            'canBuyShield',
            'canBuyImmunity',
            'canSpin',
            'canSkipTutorial',
            'canReadNews',
            'canClaimDailyReward'
        );
    }

    /**
     * Create Date
     * @param int|string|null $date
     * @return Carbon
     */
    protected function createDate($date)
    {
        return $date === null ? Carbon::now() : Carbon::createFromTimestampMs($date);
    }
}
