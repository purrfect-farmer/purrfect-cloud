<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FarmHrum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:hrum';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Hrum Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::lock($this->signature)->get(function () {
            /** Start Date */
            $startDate = now();

            /** Start Farming */
            Account::farmer('hrum')
                ->connected()
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
                        $this->makeHrumRequest(
                            null,
                            $account,
                            [
                                'platform' => $platform,
                                'initData' => $initData,
                                'startParam' => $initDataUnsafe['start_param'] ?? '',
                                'photoUrl' => $initDataUnsafe['user']['photo_url'] ?? '',
                                'chatId' => $initDataUnsafe['chat']['id'] ?? '',
                                'chatType' => $initDataUnsafe['chat_type'],
                                'chatInstance' => $initDataUnsafe['chat_instance']
                            ],
                            'https://api.hrum.me/telegram/auth'
                        );


                        /** All Data */
                        $allData = $this->makeHrumRequest(
                            $key,
                            $account,
                            [],
                            'https://api.hrum.me/user/data/all',
                            JSON_FORCE_OBJECT
                        );

                        /** After Data */
                        $afterData =
                            $this->makeHrumRequest(
                                $key,
                                $account,
                                [
                                    'lang' => 'en'
                                ],
                                'https://api.hrum.me/user/data/after'
                            );





                        /** Daily Check-In */
                        $dailyRewards = $this->makeHrumRequest(
                            $key,
                            $account,
                            [],
                            'https://api.hrum.me/quests/daily',
                            JSON_FORCE_OBJECT
                        );

                        $day = collect($dailyRewards)->flip()->get('canTake');

                        if ($day) {
                            /** Get Result */
                            $result = $this->makeHrumRequest(
                                $key,
                                $account,
                                $day,
                                'https://api.hrum.me/quests/daily/claim'
                            );


                            /** Update Data */
                            $dailyRewards = $result['dailyRewards'];
                            $allData['hero'] = $result['hero'];
                        }

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


                        /** Claim Riddle */
                        if ($canClaimRiddle) {
                            /** Check */
                            $check = $this->makeHrumRequest(
                                $key,
                                $account,
                                [
                                    $riddle['key'],
                                    $riddle['checkData']
                                ],
                                'https://api.hrum.me/quests/check'
                            );


                            /** Get Result */
                            $result =  $this->makeHrumRequest(
                                $key,
                                $account,
                                [
                                    $riddle['key'],
                                    $riddle['checkData']
                                ],
                                'https://api.hrum.me/quests/claim'
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
                            $result =  $this->makeHrumRequest(
                                $key,
                                $account,
                                [
                                    $task['key'],
                                    null
                                ],
                                'https://api.hrum.me/quests/claim'
                            );

                            /** Update Data */
                            $allData['hero'] = $result['hero'];
                            $afterData['quests'] = $result['quests'];
                        }


                        /** Open Cookie */
                        $cookies = intval($allData['hero']['cookies']);
                        if ($cookies > 0) {
                            $this->makeHrumRequest(
                                $key,
                                $account,
                                [],
                                'https://api.hrum.me/user/cookie/open',
                                JSON_FORCE_OBJECT
                            );
                        }
                    } catch (\Throwable $e) {
                        if (app()->isProduction()) {
                            $account->disconnect();
                        }

                        /** Log Error */
                        Log::error('Hrum Error', [
                            'message' => $e->getMessage(),
                            'line' => $e->getLine()
                        ]);
                    }
                });

            /** End Date */
            $endDate = now();

            /** Get Links */
            $links = Helpers::getCloudAccountLinks(
                Account::farmer('hrum')->get()
            );

            /** Send Message */
            Helpers::sendCloudFarmerMessage('hrum.completed', [
                "<b>🥠 Hrum Farmer</b>",
                "<i>✅ Status: Completed</i>",
                $links,
                "<b>🗓️ Start Date</b>: $startDate",
                "<b>🗓️ End Date</b>: $endDate"
            ]);
        });
    }

    protected function makeHrumRequest(
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
            $this->getHrumHeaders(
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
        return Http::timeout(10)
            ->withHeaders($account->headers)
            ->withHeaders([
                'Origin' => 'https://game.hrum.me',
                'Referer' => 'https://game.hrum.me/',
                'X-Requested-With' => 'org.telegram.messenger'
            ])
            ->withUserAgent(
                $account->headers['User-Agent'] ??
                    Helpers::getUserAgent($account->user_id)
            );
    }

    protected function getHrumHeaders($data, $key)
    {
        $apiTime = time();
        $apiHash = md5(
            rawurlencode($apiTime . '_' . $data ?? '')
        );

        return [
            'Api-Key' => $key ?? 'empty',
            'Api-Time' => $apiTime,
            'Api-Hash' => $apiHash,
            'Is-Beta-Server' => null,
        ];
    }
}
