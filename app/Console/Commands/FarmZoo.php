<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FarmZoo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:zoo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Zoo Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::lock($this->signature)->get(function () {
            // Start Farming
            Account::where('farmer', 'zoo')
                ->get()
                ->each(function (Account $account) {
                    try {
                        /** Api-Key */
                        $key = $account->telegram_web_app['initDataUnsafe']['hash'];

                        /** API */
                        $api = Http::withHeaders($account->headers)
                            ->withUserAgent(
                                $account->headers['User-Agent'] ?:
                                    Helpers::getUserAgent($account->user_id)
                            );


                        /** All Data */
                        $allData = $this->makeZooRequest(
                            $key,
                            $api,
                            [],
                            'https://api.zoo.team/user/data/all',
                            JSON_FORCE_OBJECT
                        );

                        /** After Data */
                        $afterData =
                            $this->makeZooRequest(
                                $key,
                                $api,
                                [
                                    'lang' => "en"
                                ],
                                'https://api.zoo.team/user/data/after'
                            );


                        /** Daily Check-In */
                        $dailyRewards = $afterData['dailyRewards'];
                        $day = collect($dailyRewards)->flip()->get('canTake');


                        if ($day) {
                            /** Get Result */
                            $result = $this->makeZooRequest(
                                $key,
                                $api,
                                $day,
                                'https://api.zoo.team/quests/daily/claim'
                            );


                            /** Update Data */
                            $allData['hero'] = $result['hero'];
                            $afterData['dailyRewards'] = $result['dailyRewards'];
                        }



                        /** Riddle and Rebus */
                        /** Riddle */
                        $riddle = collect($allData['dbData']['dbQuests'])->first(
                            fn($quest) =>
                            Str::startsWith($quest['key'], "riddle_")
                        );

                        /** Riddle Completion */
                        $riddleCompletion = collect($afterData['quests'])
                            ->first(
                                fn($quest) => $quest['key'] === $riddle['key']
                            );

                        /** Can Claim Riddle */
                        $canClaimRiddle = $riddle && !$riddleCompletion;

                        /** Rebus */
                        $rebus = collect($allData['dbData']['dbQuests'])->first(
                            fn($quest) =>
                            Str::startsWith($quest['key'], "rebus_")
                        );

                        /** Rebus Completion */
                        $rebusCompletion = collect($afterData['quests'])
                            ->first(
                                fn($quest) => $quest['key'] === $rebus['key']
                            );

                        /** Can Claim Rebus */
                        $canClaimRebus = $rebus && !$rebusCompletion;


                        /** Claim Riddle */
                        if ($canClaimRiddle) {
                            /** Check */
                            $this->makeZooRequest(
                                $key,
                                $api,
                                [
                                    $riddle['key'],
                                    $riddle['checkData']
                                ],
                                'https://api.zoo.team/quests/check'
                            );


                            /** Get Result */
                            $result =  $this->makeZooRequest(
                                $key,
                                $api,
                                [
                                    $riddle['key'],
                                    $riddle['checkData']
                                ],
                                'https://api.zoo.team/quests/claim'
                            );

                            /** Update Data */
                            $allData['hero'] = $result['hero'];
                            $afterData['quests'] = $result['quests'];
                        }


                        /** Claim Rebus */
                        if ($canClaimRebus) {
                            /** Check */
                            $this->makeZooRequest(
                                $key,
                                $api,
                                [
                                    $rebus['key'],
                                    $rebus['checkData']
                                ],
                                'https://api.zoo.team/quests/check'
                            );


                            /** Get Result */
                            $result =  $this->makeZooRequest(
                                $key,
                                $api,
                                [
                                    $rebus['key'],
                                    $rebus['checkData']
                                ],
                                'https://api.zoo.team/quests/claim'
                            );

                            /** Update Data */
                            $allData['hero'] = $result['hero'];
                            $afterData['quests'] = $result['quests'];
                        }

                        /** Fake Check Tasks */
                        $completedQuests = collect($afterData['quests']);
                        $tasks = collect(
                            $allData['dbData']['dbQuests']
                        )->filter(
                            fn($item) => $item['checkType'] === 'fakeCheck'

                        )->filter(
                            fn($item) => $completedQuests->first(
                                fn($quest) => $quest['key'] === $item['key']
                            ) === null
                        );


                        /** Claim Tasks */
                        foreach ($tasks as $task) {
                            /** Get Result */
                            $result =  $this->makeZooRequest(
                                $key,
                                $api,
                                [
                                    $task['key'],
                                    null
                                ],
                                'https://api.zoo.team/quests/claim'
                            );

                            /** Update Data */
                            $allData['hero'] = $result['hero'];
                            $afterData['quests'] = $result['quests'];
                        }


                        /** Buy Feed */
                        $shouldBuyFeed = $this->shouldBuyFeed($allData);

                        if ($shouldBuyFeed) {
                            $result =  $this->makeZooRequest(
                                $key,
                                $api,
                                "instant",
                                'https://api.zoo.team/autofeed/buy'
                            );

                            /** Update Data */
                            $allData = [
                                ...$allData,
                                ...$result
                            ];
                        }


                        /** Boost */
                        $boost = $this->getZooBoost($allData);

                        if ($boost) {
                            $result =  $this->makeZooRequest(
                                $key,
                                $api,
                                $boost['key'],
                                'https://api.zoo.team/boost/buy'
                            );

                            /** Update Data */
                            $allData = [
                                ...$allData,
                                ...$result
                            ];
                        }
                    } catch (\Throwable $e) {
                        $account->delete();
                    }
                });

            // Log Farming Completion
            Log::info('Completed Zoo Farming - ' . now());
        });
    }



    protected function shouldBuyFeed($allData)
    {
        $hero = $allData['hero'];
        $balance = $hero['coins'];
        $tph = $hero['tph'];

        $instantItem = collect($allData['dbData']['dbAutoFeed'])
            ->first(
                fn($item) => $item['key'] === "instant"
            );
        $instantItemPriceInTph = $instantItem['priceInTph'];

        $feedPrice = ceil($tph * $instantItemPriceInTph);

        $feed = $allData['feed'];
        $isNeedFeed = $feed['isNeedFeed'];
        $nextFeedTime = $feed['nextFeedTime'];

        $hasExpired = $nextFeedTime && now()->isAfter($nextFeedTime . "Z");

        $shouldPurchaseFeed = $isNeedFeed || $hasExpired;
        $canPurchaseFeed = $balance >= $feedPrice;

        return $shouldPurchaseFeed && $canPurchaseFeed;
    }


    public function getZooBoost($allData)
    {
        $hero = $allData['hero'];
        $balance = $hero['coins'];

        $currentBoostPercent = $hero['boostPercent'];
        $boostExpiredDate = $hero['boostExpiredDate'];
        $hasExpired = $boostExpiredDate && now()->isAfter($boostExpiredDate . "Z");


        $availableBoosts = collect($allData['dbData']['dbBoost'])
            ->filter(
                fn($item) => $item['price'] <= $balance
            );
        $boost = $availableBoosts->first(
            fn($item) => $item['price'] === 1000
        );

        $shouldPurchase = $boost &&
            ($boost['boost'] > $currentBoostPercent || $hasExpired);


        return $shouldPurchase ? $boost : null;
    }


    protected function makeZooRequest(
        string $key,
        PendingRequest $api,
        mixed $data,
        string $url,
        int $flags = 0
    ) {
        /** Request Body */
        $requestBody = json_encode(['data' => $data], $flags);

        /** Get Result */
        return $api->withHeaders(
            $this->getZooHeaders(
                $requestBody,
                $key
            )
        )
            ->withBody($requestBody)
            ->post($url)
            ->json('data');
    }


    protected function getZooHeaders($data, $key)
    {
        $apiTime = time();
        $apiHash = md5(
            urlencode($apiTime . '_' . $data ?: "")
        );

        return [
            "Api-Key" => $key ?: "empty",
            "Api-Time" => $apiTime,
            "Api-Hash" => $apiHash,
            "Is-Beta-Server" => null,
        ];
    }
}