<?php
namespace App\Farmers;

use Illuminate\Support\Carbon;


class FrogsterFarmer extends BaseFarmer
{

    protected $key = 'frogster';
    protected $origin = 'https://frogster.app';
    protected $shouldSetAuth = true;

    protected function setAuth()
    {
        /** Init Data */
        $initData = $this->farmer->getInitData();

        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->post(
                'https://frogster.app/api/auth',
                [
                    'init_data' => $initData,
                    'ref_code' => '',
                ]
            )
            ->json('token');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    public function process()
    {
        try {
            /** Get User */
            $user = $this->getApi()->get('https://frogster.app/api/me')->json();


            /** Try to join */
            if (!$user['in_community']) {
                $this->tryToJoinTelegramLink('https://t.me/FrogsterChat');
            }


            /** Complete one task */
            $tasks = $this->getApi()->get('https://frogster.app/api/tasks')->collect();
            $ownTasks = $this->getApi()->get('https://frogster.app/api/tasks/own')->collect();

            $completedTasks = $ownTasks->pluck('id');
            $availableTasks = $tasks->filter(
                fn($item) => !$completedTasks->contains($item['id']) && !isset($item['tag'])
            );

            $uncompletedTasks = $availableTasks->filter(
                fn($item) => $this->validateTelegramTask($item['url'] ?? null)
            );

            if ($uncompletedTasks->isNotEmpty()) {
                $task = $uncompletedTasks->random();
                $this->tryToJoinTelegramLink($task['url'] ?? null);
                $this->getApi()->get('https://frogster.app/api/tasks/assign/' . $task['id']);
            }


            /** Claim */
            $balance = $this->getApi()->get('https://frogster.app/api/wallets/balance');
            $anHourAgo = now()->subHour();
            $lastClaimDate = new Carbon($balance["last_claimed_at"] . "Z");

            if ($lastClaimDate->lessThan($anHourAgo)) {
                $this->getApi()->post(
                    'https://frogster.app/api/wallets/claim?claim_plan_type=1&currency=TON'
                );
            }


        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }
}