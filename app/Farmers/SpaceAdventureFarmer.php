<?php
namespace App\Farmers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\RequestInterface;

class SpaceAdventureFarmer extends BaseFarmer
{

    protected $key = 'space-adventure';
    protected $origin = 'https://space-adventure.online';

    protected function setAuth()
    {
        /** Get API */
        $api = $this->getSpaceAdventureApi();

        /** Get Access Token */
        $accessToken = $api->asForm()
            ->post(
                'https://space-adventure.online/api/auth/telegram',
                $this->farmer->getInitDataParsed()
            )->json('token');

        /** Update Authorization Header */
        return $this->farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }


    public function process()
    {
        try {
            /** Get API */
            $api = $this->getSpaceAdventureApi();


            /** Get User */
            $user = $api->get('https://space-adventure.online/api/user/get')->json('user');


            /** Get Boosts */
            $boosts = $api->get('https://space-adventure.online/api/boost/get/')
                ->collect('list');


            /** Get Status */
            $status = $this->getStatus($user);

            if ($status['canSkipTutorial']) {
                $user = $api->put('https://space-adventure.online/api/user/settings/tutorial/')
                    ->json('user');
                $status = $this->getStatus($user);
            }

            if ($status['canReadNews']) {
                $user = $api->put('https://space-adventure.online/api/user/settings/read-news')
                    ->json('user');
                $status = $this->getStatus($user);
            }

            if ($status['canClaimDailyReward']) {
                $user = $this->makeAdsRequest($api, 'daily_activity')
                    ->post('https://space-adventure.online/api/dayli/claim_activity/')
                    ->json('user');
                $status = $this->getStatus($user);
            }

            if ($status['canClaim']) {
                /** Get Captcha */
                $captcha = $this->makeAdsRequest($api, 'claim_coins')
                    ->get('https://space-adventure.online/api/game/captcha/')
                    ->json();

                $correct = collect($captcha['captchaList'])->first(
                    fn($item) => $item['img'] === $captcha['captchaTrue']
                );


                /** Solve Captcha */
                $api->post('https://space-adventure.online/api/game/captcha/', ['captcha' => $correct['value']])
                    ->json();

                /** Claim */
                $user = $api->post('https://space-adventure.online/api/game/claiming/')->json('user');
                $status = $this->getStatus($user);
            }

            /** Spin */
            if ($status['canSpin']) {
                $user = $this->makeAdsRequest($api, 'spin_roulete')
                    ->post('https://space-adventure.online/api/roulette/buy/', ['method' => 'free'])
                    ->json('user');
                $status = $this->getStatus($user);
            }

            /** Buy Shield */
            if ($status['canBuyShield']) {
                $user = $this->shopFreeItem($api, $boosts, 'shield')->json('user');
                $status = $this->getStatus($user);
            }

            /** Buy Immunity */
            if ($status['canBuyImmunity']) {
                $user = $this->shopFreeItem($api, $boosts, 'immunity')->json('user');
                $status = $this->getStatus($user);
            }

            /** Buy Fuel */
            if ($status['canBuyFuel']) {
                $user = $this->shopFreeItem($api, $boosts, 'fuel')->json('user');
                $status = $this->getStatus($user);
            }

            /** Tasks */
            $tasks = $api->get('https://space-adventure.online/api/tasks/get?category=sponsors')
                ->collect('listActive');

            $videoTasksCount = intval($user['video_tasks']);
            $adsTask = $tasks->first(
                fn($item) =>
                $item['status'] === 'not_completed' &&
                Str::of($item['title'])->contains("Watch 3 ads")
            );

            if ($adsTask) {
                for ($i = $videoTasksCount; $i < 3; $i++) {
                    $this->makeAdsRequest($api, 'tasks_reward')
                        ->put('https://space-adventure.online/api/tasks/reward-video/');
                }
            }

            /** Upgrade Level */
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
                isset($item['next_level']) && (
                    $item['next_level']['price_coin'] <= $balance ||
                    $item['next_level']['price_gems'] <= $gems
                )
            );

            $currentLevel = $user['level_global'];
            $sameLevel = $availableBoosts->every('level_current', $currentLevel);

            $upgradableBoosts = $availableBoosts->filter(
                fn($item) => $sameLevel || $item['level_current'] === $currentLevel
            );

            if ($upgradableBoosts->isNotEmpty()) {
                $random = $upgradableBoosts->random();
                $method = $random['next_level']['price_gems'] <= $gems ? 'gems' : 'coin';

                $api->post(
                    'https://space-adventure.online/api/boost/buy/',
                    [
                        'method' => $method,
                        'id' => $random['id']
                    ]
                );
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }



    /**
     * Shop Free Item
     * @param \Illuminate\Http\Client\PendingRequest $api
     * @param \Illuminate\Support\Collection $boosts
     * @param string $type
     * @return \Illuminate\Http\Client\Response
     */
    protected function shopFreeItem(PendingRequest $api, Collection $boosts, string $type)
    {
        $shopItem = $boosts->first(
            fn($item) => $item['single_type'] === $type
        );

        return $this->makeAdsRequest($api, 'shop_free_' . $type)->post(
            'https://space-adventure.online/api/boost/buy/',
            [
                'method' => 'free',
                'id' => $shopItem['id']
            ]
        );
    }

    /**
     * Make Ads Request
     * @param \Illuminate\Http\Client\PendingRequest $api
     * @param string $type
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function makeAdsRequest(PendingRequest $api, $type)
    {
        /** Get Ads */
        $api->post('https://space-adventure.online/api/user/get_ads/', ['type' => $type]);

        /** Return API */
        return $api;
    }


    /**
     * Get Status
     * @param array $user
     * @return array
     */
    protected function getStatus($user)
    {
        $localeTime = $this->createDate($user["locale_time"]);

        $timePassed = $this->createDate($user["claimed_last"])->diffInSeconds(
            $this->createDate(
                $user["shield_ended_at"]
            )
        );
        $lowFuelInSeconds = 10 * 60; // Ten Minutes
        $remainingFuelInSeconds = $localeTime->diffInSeconds(
            $this->createDate(
                $user["fuel_last_at"] + $user["fuel"] * 1000
            )
        );

        $unclaimed = $user["claim"] * $timePassed;
        $canBuyFuel = $remainingFuelInSeconds <= $lowFuelInSeconds &&
            $localeTime->isAfter(
                $this->createDate($user["fuel_free_at"])
            );

        $canClaim = $unclaimed >= $user["claim_max"];

        $canBuyShield = $user["shield_damage"] > 0 &&
            $localeTime->isAfter(
                $this->createDate($user["shield_free_at"])
            );

        $canBuyImmunity =
            $localeTime->isAfter(
                $this->createDate($user["shield_immunity_at"])
            ) &&
            $localeTime->isAfter(
                $this->createDate($user["shield_free_immunity_at"])
            );

        $canSpin = $localeTime->isAfter(
            $this->createDate($user["spin_after_at"])
        );

        $canSkipTutorial = $user["tutorial"] !== true;

        $canReadNews = $user["new_post"] !== true;

        $canClaimDailyReward = $localeTime->isAfter(
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
        return !isset($date) ? Carbon::now() : Carbon::createFromTimestampMs($date);
    }

    protected function getSpaceAdventureApi()
    {
        /** Cookies */
        $cookies = new CookieJar();


        /** Get API */
        $api = $this->getApi()
            ->withOptions(['cookies' => $cookies])
            ->withRequestMiddleware(function (RequestInterface $request) use ($cookies) {
                $xsrfCookie = $cookies->getCookieByName('XSRF-TOKEN');
                $xsrf = urldecode($xsrfCookie ? $xsrfCookie->getValue() : '');

                $authHeader = $this->farmer->headers['Authorization'] ?? '';
                $accessToken = explode(" ", $authHeader)[1] ?? '';

                $headers = array_merge(
                    $this->getSignatureHeaders(
                        timestamp: strval(time()),
                        authId: strval($this->farmer->user_id),
                        accessToken: $accessToken,
                        xsrf: $xsrf,
                        uuid: Str::uuid(),
                    ),
                    ['x-xsrf-token' => $xsrf]
                );

                return collect($headers)->reduce(
                    fn($newRequest, $v, $k) => $newRequest->withHeader($k, $v),
                    $request
                );
            });

        /** Fetch CSRF Token */
        $api->get('https://space-adventure.online/sanctum/csrf-cookie');

        return $api;
    }

    /**
     * Get Signature Headers
     * @param string $timestamp
     * @param string $authId
     * @param string $accessToken
     * @param string $xsrf
     * @param string $uuid
     * @return array{x-auth-id: string, x-nonce: string, x-signature: string, x-timestamp: string, x-xsrf-sign: string, x-xsrf-token: string}
     */
    protected function getSignatureHeaders(
        $timestamp,
        $authId,
        $accessToken,
        $xsrf,
        $uuid,
    ) {
        $nonce = $uuid . '-' . $timestamp;
        $sign = $this->getXSRFSign($xsrf, $timestamp);

        $data = implode(":", [$timestamp, $timestamp, $accessToken, $nonce, $sign]);
        $signature = hash(
            "sha256",
            $data
        );

        return [
            'x-auth-id' => $authId,
            'x-timestamp' => $timestamp,
            'x-nonce' => $nonce,
            'x-xsrf-sign' => $sign,
            'x-signature' => $signature,
        ];
    }

    /**
     * Get XSRF Sign
     * @param string $xsrf
     * @param string $timestamp
     * @return string
     */
    protected function getXSRFSign($xsrf, $timestamp)
    {
        $half = floor(strlen($xsrf) / 2);
        $first = substr($xsrf, 0, $half);
        $second = substr($xsrf, $half);
        return hash("sha256", $first . $timestamp . $second);
    }
}