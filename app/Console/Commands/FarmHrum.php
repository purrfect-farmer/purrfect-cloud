<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FarmHrum extends Command
{
    use FarmerTrait;

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
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://game.hrum.me';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Start Farming */
            $this->getFarmers()
                ->each(function (Farmer $farmer) {
                    try {

                        /** Platform */
                        $platform = 'android';

                        /** Init Data */
                        $initData = $farmer->telegram_web_app['initData'];

                        /** Init Data Unsafe */
                        $initDataUnsafe = $farmer->telegram_web_app['initDataUnsafe'];

                        /** Api-Key */
                        $key = $initDataUnsafe['hash'];

                        /** Auth */
                        $this->makeHrumRequest(
                            null,
                            $farmer,
                            [
                                'platform' => $platform,
                                'initData' => $initData,
                                'startParam' => $initDataUnsafe['start_param'] ?? '',
                                'photoUrl' => $initDataUnsafe['user']['photo_url'] ?? '',
                                'chatId' => $initDataUnsafe['chat']['id'] ?? '',
                                'chatType' => $initDataUnsafe['chat_type'] ?? '',
                                'chatInstance' => $initDataUnsafe['chat_instance'] ?? ''
                            ],
                            'https://api.hrum.me/telegram/auth'
                        );


                        /** All Data */
                        $allData = $this->makeHrumRequest(
                            $key,
                            $farmer,
                            [],
                            'https://api.hrum.me/user/data/all',
                            JSON_FORCE_OBJECT
                        );

                        /** After Data */
                        $afterData =
                            $this->makeHrumRequest(
                                $key,
                                $farmer,
                                [
                                    'lang' => 'en'
                                ],
                                'https://api.hrum.me/user/data/after'
                            );





                        /** Daily Check-In */
                        $dailyRewards = $this->makeHrumRequest(
                            $key,
                            $farmer,
                            [],
                            'https://api.hrum.me/quests/daily',
                            JSON_FORCE_OBJECT
                        );

                        $day = collect($dailyRewards)->flip()->get('canTake');

                        if ($day) {
                            /** Get Result */
                            $result = $this->makeHrumRequest(
                                $key,
                                $farmer,
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
                                $farmer,
                                [
                                    $riddle['key'],
                                    $riddle['checkData']
                                ],
                                'https://api.hrum.me/quests/check'
                            );


                            /** Get Result */
                            $result =  $this->makeHrumRequest(
                                $key,
                                $farmer,
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
                                $farmer,
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
                                $farmer,
                                [],
                                'https://api.hrum.me/user/cookie/open',
                                JSON_FORCE_OBJECT
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

    protected function makeHrumRequest(
        ?string $key = null,
        Farmer $farmer,
        mixed $data,
        string $url,
        int $flags = 0
    ) {
        /** Request Body */
        $requestBody = json_encode(['data' => $data], $flags);

        /** Get Result */
        return $this->getApi($farmer)->withHeaders(
            $this->getHrumHeaders(
                $requestBody,
                $key
            )
        )
            ->withBody($requestBody)
            ->post($url)
            ->json('data');
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
