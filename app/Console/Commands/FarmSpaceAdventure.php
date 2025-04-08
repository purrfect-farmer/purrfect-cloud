<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Helpers;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
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
            $this->getFarmers(true)->mapConcurrently(function (Farmer $farmer) {
                try {
                    $user = $this->getApi($farmer)
                        ->get('https://space-adventure.online/api/user/get')
                        ->json('user');

                    $boosts = collect(
                        $this->getApi($farmer)
                            ->get('https://space-adventure.online/api/boost/get/')
                            ->json('list')
                    );

                    $status = $this->getStatus($user);

                    if ($status['canSkipTutorial']) {
                        $user = $this->getApi($farmer)
                            ->put('https://space-adventure.online/api/user/settings/tutorial/')
                            ->json('user');
                    }

                    if ($status['canReadNews']) {
                        $user = $this->getApi($farmer)
                            ->put('https://space-adventure.online/api/user/settings/read-news')
                            ->json('user');
                    }

                    if ($status['canClaimDailyReward']) {
                        $user = $this->getAdsApi($farmer, 'daily_activity')
                            ->post('https://space-adventure.online/api/dayli/claim_activity/')
                            ->json('user');
                    }

                    if ($status['canClaim']) {
                        $user = $this->getApi($farmer)
                            ->post('https://space-adventure.online/api/game/claiming/')
                            ->json('user');
                    }

                    if ($status['canSpin']) {
                        $user = $this->getAdsApi($farmer, 'spin_roulete')
                            ->post('https://space-adventure.online/api/roulette/buy/', ['method' => 'free'])
                            ->json('user');
                    }

                    if ($status['canBuyFuel']) {
                        $user = $this->shopFreeItem($farmer, $boosts, 'fuel')->json('user');
                    }

                    if ($status['canBuyShield']) {
                        $user = $this->shopFreeItem($farmer, $boosts, 'shield')->json('user');
                    }

                    if ($status['canBuyImmunity']) {
                        $user = $this->shopFreeItem($farmer, $boosts, 'immunity')->json('user');
                    }

                    $tasks = collect(
                        $this->getApi($farmer)
                            ->get('https://space-adventure.online/api/tasks/get?category=sponsors')
                            ->json('listActive')
                    );

                    $videoTasksCount = intval($user['video_tasks']);
                    $adsTask = $tasks->first(
                        fn($item) =>
                        $item['status'] === 'not_completed' &&
                        Str::of($item['title'])->contains("Watch 3 ads")
                    );

                    if ($adsTask) {
                        for ($i = $videoTasksCount; $i < 3; $i++) {
                            $this->getAdsApi($farmer, 'tasks_reward')
                                ->put('https://space-adventure.online/api/tasks/reward-video/');
                        }
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
     * @param \App\Models\Farmer $farmer
     * @param \Illuminate\Support\Collection $boosts
     * @param string $type
     * @return \Illuminate\Http\Client\Response
     */
    protected function shopFreeItem(Farmer $farmer, Collection $boosts, string $type)
    {
        $shopItem = $boosts->first(
            fn($item) => $item['single_type'] === $type
        );

        return $this->getAdsApi(
            $farmer,
            'shop_free_' . $type
        )->post(
                'https://space-adventure.online/api/boost/buy/',
                [
                    'method' => 'free',
                    'id' => $shopItem['id']
                ]
            );
    }

    /**
     * Get Ads API
     * @param \App\Models\Farmer $farmer
     * @param string $type
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getAdsApi(Farmer $farmer, $type)
    {
        $this->getApi($farmer)->post(
            'https://space-adventure.online/api/user/get_ads/',
            ['type' => $type]
        );

        return $this->getApi($farmer);
    }


    /**
     * Get Status
     * @param array $user
     * @return array
     */
    protected function getStatus($user)
    {
        $timePassed = $this->createDate($user["claimed_last"])->diffInSeconds();
        $remainingFuel = now()->diffInSeconds(
            $this->createDate(
                $user["fuel_last_at"] + $user["fuel"] * 1000
            )
        );

        $unclaimed = $user["claim"] * $timePassed;
        $canBuyFuel = $remainingFuel <= $user["fuel"] / 2 &&
            now()->isAfter(
                $this->createDate($user["fuel_free_at"])
            );

        $canClaim = $unclaimed >= $user["claim_max"];

        $canBuyShield = $user["shield_damage"] === 1 &&
            now()->isAfter(
                $this->createDate($user["shield_free_at"])
            );

        $canBuyImmunity = now()->isAfter(
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
     * @param int|string $date
     * @return Carbon
     */
    protected function createDate($date)
    {
        return Carbon::createFromTimestampMs($date);
    }

    /**
     *  Set Authorization
     * @param \App\Models\Farmer $farmer
     * @return Farmer
     */
    protected function setAuth(Farmer $farmer)
    {
        /** Get Access Token */
        $accessToken = $this->getBaseApi($farmer)
            ->asForm()
            ->post(
                'https://space-adventure.online/api/auth/telegram',
                $farmer->getInitDataParsed()
            )
            ->json('token');

        /** Set Headers */
        return $farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }
}