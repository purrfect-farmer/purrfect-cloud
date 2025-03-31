<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FarmTsubasa extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:tsubasa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Tsubasa Automatically';

    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://web.app.ton.tsubasa-rivals.com';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Start Farming */
            $this->runConcurrently(
                $this->getFarmers()
                    ->mapForConcurrency(function (Farmer $farmer) {
                        try {
                            /** Platform */
                            $platform = 'android';

                            /** Init Data */
                            $initData = $farmer->getInitData();

                            /** Init Data Unsafe */
                            $initDataUnsafe = $farmer->getInitDataUnsafe();

                            /** Auth */
                            $auth = $this->getTsubasaApi($farmer)->post(
                                'https://api.app.ton.tsubasa-rivals.com/api/start',
                                [
                                    'initData' => $initData,
                                    'lang_code' => $initDataUnsafe['user']['language_code']
                                ]
                            )->json();

                            /** MasterHash */
                            $masterHash = $auth['master_hash'];


                            $user = $auth['game_data']['user'];
                            $balance = $user['total_coins'];
                            $friendCount = $auth['friend_count'];

                            /** Claim Daily Reward */
                            $lastUpdate = $auth['user_daily_reward']['last_update'];
                            $hasClaimedDailyReward = Carbon::createFromTimestamp($lastUpdate)->isToday();

                            if ($hasClaimedDailyReward === false) {
                                $data = $this->getBaseApi($farmer)->post(
                                    'https://api.app.ton.tsubasa-rivals.com/api/daily_reward/claim',
                                    [
                                        'initData' => $initData,
                                    ]
                                )->json();

                                if (isset($data['master_hash'])) {
                                    $masterHash = $data['master_hash'];
                                }
                            }

                            /**
                             * @var \Illuminate\Support\Collection
                             */
                            $allCards = collect($auth['card_info'])->reduce(
                                fn($result, $category) =>
                                $result->concat(
                                    $category['card_list']
                                ),
                                collect([])
                            );

                            /** Available Cards */
                            $availableCards = $allCards->filter(
                                fn($card) => $card['cost'] <= $balance && $this->validateCardEndTime($card)
                            );

                            /** Unlocked Cards */
                            $unlockedCards = $availableCards->filter(
                                fn($card) => $card['unlocked'] || $this->validateCardUnlock(
                                    $availableCards,
                                    $friendCount,
                                    $card
                                )
                            );

                            /** Upgradable Cards */
                            $upgradableCards = $unlockedCards->filter(
                                fn($card) => $this->validateCardAvailability($card)
                            );

                            /** Level Zero Cards */
                            $levelZeroCards = $upgradableCards->filter(fn($item) => $item["level"] === 0);

                            /** Required Cards */
                            $requiredCards = $upgradableCards->filter(
                                fn($item) =>
                                $availableCards->some(
                                    fn($card) =>
                                    $item["id"] === $card["unlock_card_id"] &&
                                        $item["level"] < $card["unlock_card_level"]
                                )
                            );

                            /** Collection */
                            $collection = $levelZeroCards->isNotEmpty() ? $levelZeroCards : (
                                $requiredCards->isNotEmpty() ? $requiredCards : $upgradableCards
                            );

                            /** Upgrade a random card */
                            if ($collection->isNotEmpty()) {
                                $card = $collection->random();

                                /** Level Up */
                                $this->getTsubasaApi(
                                    $farmer,
                                    $masterHash
                                )->post(
                                    'https://api.app.ton.tsubasa-rivals.com/api/card/levelup',
                                    [
                                        'card_id' => $card['id'],
                                        'category_id' => $card['category'],
                                        'initData' => $initData
                                    ]
                                )->json();
                            }
                        } catch (\Throwable $e) {
                            /** Log Error */
                            $this->logError($e, $farmer);

                            /** Refetch Auth or Disconnect Farmer */
                            $this->refetchAuthOrDisconnect($farmer);
                        }
                    })
            );
        });
    }


    /**
     * Validate Card Availability
     * @param mixed $card
     * @return bool
     */
    protected function validateCardAvailability($card)
    {
        return $card["level_up_available_date"] === null || now()->isAfter(
            Carbon::createFromTimestamp($card["level_up_available_date"])
        );
    }

    /**
     * Validate Card Unlock
     * @param mixed $availableCards
     * @param int $friendCount
     * @param array $card
     * @return bool
     */
    protected function validateCardUnlock(
        $availableCards,
        $friendCount,
        $card
    ) {
        return $card['unlock_card_id'] === null ||
            $card['unlock_card_level'] <=
            (
                $card['unlock_card_id'] === 'Friend'
                ? $friendCount
                : $availableCards->first(
                    fn($item) => $item['id'] === $card['unlock_card_id']
                )['level'] ?? null
            );
    }

    /**
     * Validate Card End Time
     * @param array $card
     * @return bool
     */
    protected function validateCardEndTime($card)
    {
        return $card['end_datetime'] === null || now()->isBefore(
            Carbon::createFromTimestamp($card['end_datetime'])
        );
    }

    /**
     * Get Tsubasa API
     * @param \App\Models\Farmer $farmer
     * @param string $hash
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getTsubasaApi(Farmer $farmer, string $hash = '')
    {
        return $this->getBaseApi($farmer)->withHeaders([
            'X-Masterhash' => $hash,
            'X-Player-Id' => $farmer->user_id,
        ]);
    }
}
