<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
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
            /** Send Message */
            Helpers::sendCloudFarmerMessage('zoo.started', [
                "<b>🐍 Zoo Farmer</b>",
                "<i>🔁 Status: Started</i>",
            ]);

            /** Start Farming */
            Account::where('farmer', 'zoo')
                ->get()
                ->each(function (Account $account) {
                    try {

                        /** Platform */
                        $platform = 'android';

                        /** Init Data */
                        $initData = $account->telegram_web_app['initData'];

                        /** Init Data Unsafe */
                        $initDataUnsafe = $account->telegram_web_app['initDataUnsafe'];

                        /** Api-Key */
                        $key = $initDataUnsafe['hash'];

                        /** Auth */
                        $this->makeZooRequest(
                            null,
                            $account,
                            [
                                'platform' => $platform,
                                'initData' => $initData,
                                'startParam' => $initDataUnsafe['start_param'],
                                'photoUrl' => $initDataUnsafe['user']['photo_url'] ?? '',
                                'chatId' => $initDataUnsafe['chat']['id'] ?? '',
                                'chatType' => $initDataUnsafe['chat_type'],
                                'chatInstance' => $initDataUnsafe['chat_instance']
                            ],
                            'https://api.zoo.team/telegram/auth'
                        );


                        /** All Data */
                        $allData = $this->makeZooRequest(
                            $key,
                            $account,
                            [],
                            'https://api.zoo.team/user/data/all',
                            JSON_FORCE_OBJECT
                        );

                        /** After Data */
                        $afterData =
                            $this->makeZooRequest(
                                $key,
                                $account,
                                [
                                    'lang' => 'en'
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
                                $account,
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
                            Str::startsWith($quest['key'], 'riddle_')
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
                            Str::startsWith($quest['key'], 'rebus_')
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
                                $account,
                                [
                                    $riddle['key'],
                                    $riddle['checkData']
                                ],
                                'https://api.zoo.team/quests/check'
                            );


                            /** Get Result */
                            $result =  $this->makeZooRequest(
                                $key,
                                $account,
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
                                $account,
                                [
                                    $rebus['key'],
                                    $rebus['checkData']
                                ],
                                'https://api.zoo.team/quests/check'
                            );


                            /** Get Result */
                            $result =  $this->makeZooRequest(
                                $key,
                                $account,
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
                                $account,
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
                                $account,
                                'instant',
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
                                $account,
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

                        /** Log Error */
                        Log::error('Zoo Error', [
                            'message' => $e->getMessage()
                        ]);
                    }
                });

            /** Get Links */
            $links = Helpers::getCloudAccountLinks(
                Account::where('farmer', 'zoo')->get()
            );

            /** Send Message */
            Helpers::sendCloudFarmerMessage('zoo.completed', [
                "<b>🐍 Zoo Farmer</b>",
                "<i>✅ Status: Completed</i>",
                $links
            ]);
        });
    }



    protected function shouldBuyFeed($allData)
    {
        $hero = $allData['hero'];
        $balance = $hero['coins'];
        $tph = $hero['tph'];

        $instantItem = collect($allData['dbData']['dbAutoFeed'])
            ->first(
                fn($item) => $item['key'] === 'instant'
            );
        $instantItemPriceInTph = $instantItem['priceInTph'];

        $feedPrice = ceil($tph * $instantItemPriceInTph);

        $feed = $allData['feed'];
        $isNeedFeed = $feed['isNeedFeed'];
        $nextFeedTime = $feed['nextFeedTime'];

        $hasExpired = $nextFeedTime && now()->isAfter($nextFeedTime . 'Z');

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
        $hasExpired = $boostExpiredDate && now()->isAfter($boostExpiredDate . 'Z');


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
        string $key = null,
        Account $account,
        mixed $data,
        string $url,
        int $flags = 0
    ) {
        /** Request Body */
        $requestBody = json_encode(['data' => $data], $flags);

        /** Get Result */
        return $this->getApi($account)->withHeaders(
            $this->getZooHeaders(
                $requestBody,
                $key
            )
        )
            ->withBody($requestBody)
            ->post($url)
            ->json('data');
    }


    protected function getApi(Account $account)
    {
        return Http::withHeaders($account->headers)
            ->withUserAgent(
                $account->headers['User-Agent'] ?:
                    Helpers::getUserAgent($account->user_id)
            );
    }


    protected function getZooHeaders($data, $key)
    {
        $apiTime = time();
        $apiHash = md5(
            urlencode($apiTime . '_' . $data ?: '')
        );

        return [
            'Api-Key' => $key ?: 'empty',
            'Api-Time' => $apiTime,
            'Api-Hash' => $apiHash,
            'Is-Beta-Server' => null,
        ];
    }
}
