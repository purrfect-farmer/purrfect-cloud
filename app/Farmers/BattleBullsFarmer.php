<?php
namespace App\Farmers;

use App\Helpers;
use Illuminate\Support\Carbon;

class BattleBullsFarmer extends BaseFarmer
{
    protected $key = 'battle-bulls';
    protected $origin = 'https://tg.battle-games.com';

    protected function setAuth()
    {
        /** Init Data */
        $initData = $this->farmer->getInitData();

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader($initData);
    }

    public function process()
    {
        try {
            try {
                $this->getApi()
                    ->post('https://api.battle-games.com:8443/api/api/v1/user?inviteCode=' . $this->farmer->getInitDataUnsafe()["start_param"])
                    ->json('data');
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e);
            }


            /** Get User */
            $user = $this->getApi()
                ->post('https://api.battle-games.com:8443/api/api/v1/user/sync')
                ->json('data');

            /** Get Tasks */
            $tasks = $this->getApi()
                ->get('https://api.battle-games.com:8443/api/api/v1/tasks')
                ->collect('data');


            /** Claim Daily Reward */
            $dailyTasks = $tasks->first(
                fn($item) => $item['id'] === "streak_days"
            );
            $dailyTaskCompletedAt = $dailyTasks['completedAt'] ?? null;

            if (
                !isset($dailyTaskCompletedAt) ||
                !Carbon::createFromTimestampMs($dailyTaskCompletedAt)->isToday()
            ) {
                $this->getApi()
                    ->post('https://api.battle-games.com:8443/api/api/v1/tasks/streak_days/complete')
                    ->json('data');
            }


            /** Set BlockChain */
            if (!isset($user['blockchainId'])) {
                $this->getApi()->post('https://api.battle-games.com:8443/api/api/v1/user/blockchain', [
                    'blockchainId' => 'bitcoin'
                ]);
            }

            /** Complete Task */
            $availableTasks = $tasks->filter(
                fn($item) =>
                "streak_days" !== $item['id'] &&
                $this->validateTelegramTask($item['link'] ?? null) &&
                $this->validateFriends($item) &&
                $this->validateBlockchain($item, $user)
            );



            /** Uncompleted Tasks */
            $uncompletedTasks = $availableTasks->filter(fn($item) => !$item["completedAt"]);

            if ($uncompletedTasks->isNotEmpty()) {
                $task = $uncompletedTasks->random();

                $this->tryToJoinTelegramLink($task['link'] ?? null);

                $this->getApi()->post(
                    'https://api.battle-games.com:8443/api/api/v1/tasks/' . $task['id'] . '/complete'
                );
            }


            /** All Cards */
            $cards = $this->getApi()
                ->get('https://api.battle-games.com:8443/api/api/v1/cards')
                ->collect('data');


            /** Available Cards */
            $availableCards =
                $cards->filter(
                    fn($card) =>
                    $card['available'] && isset($card['nextLevel']) &&
                    $card['nextLevel']['cost'] <= $user['balance']
                );


            /** Upgradable Cards */
            $upgradableCards = $availableCards->filter(
                fn($item) =>
                $this->validateCardCondition($item) && $this->validateCardAvailability($item)
            );


            /** Level Zero Cards */
            $levelZeroCards = $upgradableCards->filter(
                fn($card) => $card['level'] === 0
            );

            /** Collection */
            $collection = $levelZeroCards->isNotEmpty()
                ? $levelZeroCards
                : $upgradableCards;

            /** Random Card */
            $card = $collection->isNotEmpty() ? $collection->random() : null;

            if ($card) {
                $this->getApi()->post('https://api.battle-games.com:8443/api/api/v1/cards/buy', [
                    'cardId' => $card['id'],
                    'requestedAt' => now()->getTimestampMs()
                ]);
            }

            /** Tap */
            if ($user['availableEnergy'] > 0) {
                $this->getApi()->post('https://api.battle-games.com:8443/api/api/v1/taps', [
                    'taps' => $user['availableEnergy'],
                    'availableEnergy' => 0,
                    'requestedAt' => now()->getTimestampMs()
                ]);
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }

    protected function validateBlockchain($item, $user)
    {
        return $item['id'] !== "select_blockchain" || isset($user['blockchainId']);
    }

    protected function validateFriends($item)
    {
        return !isset($item['friendsMinimalCount']) || $item['friendsCount'] >= $item['friendsMinimalCount'];
    }

    protected function validateCardCondition($card)
    {
        return !isset($card['condition']) || $card['condition']['passed'];
    }

    protected function validateCardAvailability($card)
    {
        return !isset($card['boughtAt']) ||
            $card['rechargingDuration'] === 0 ||
            now()->isAfter(
                Carbon::createFromTimestampMs($card['boughtAt'] + $card['rechargingDuration'])
            );
    }
}