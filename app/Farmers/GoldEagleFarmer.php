<?php
namespace App\Farmers;

use App\Helpers;

class GoldEagleFarmer extends BaseFarmer
{

    protected $key = 'gold-eagle';
    protected $origin = 'https://telegram.geagle.online';

    protected function setAuth()
    {
        $query = http_build_query(
            data: [
                'tgWebAppData' => $this->farmer->getInitData(),
                'tgWebAppPlatform' => 'android',
                'tgWebAppVersion' => '8.4',
            ],
        );

        $url = 'https://telegram.geagle.online/?tgWebAppStartParam=r_ubdOBYN6KX#' . $query;

        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->post('https://gold-eagle-api.fly.dev/login/telegram', [
                'init_data_raw' => $url
            ])
            ->json('access_token');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    public function process()
    {
        try {
            /** Get Progress */
            $progress = $this->getApi()->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

            /** Refill */
            if ($progress['energy'] < ($progress['max_energy'] * 0.2)) {
                $this->getApi()
                    ->post('https://gold-eagle-api.fly.dev/user/me/refill');
            }

            /** Claim to Wallet */
            if (
                $progress['coins_amount'] >= $progress['max_coins_amount']
            ) {
                $tasks = $this->getApi()
                    ->get('https://gold-eagle-api.fly.dev/task/my/available')
                    ->collect();

                $hasCompletedTasks = $tasks->every(
                    fn($task) => $task['task_type'] !== 'Sl8' || $task['status'] === 'Completed'
                );

                if ($hasCompletedTasks === false) {
                    return;
                }

                $boosters = $this->getApi()
                    ->get('https://gold-eagle-api.fly.dev/boosters')
                    ->collect();
                $claimBooster = $boosters->first(
                    fn($item) => $item['booster_type'] === 'Claim' && $item['level'] > 0
                );

                /** Check Booster */
                if (!isset($claimBooster)) {
                    return;
                }

                /** Claim To Sl8 */
                try {
                    $this->claimToSl8();
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e);
                }
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }


    /**
     * Claim to Sl8
     * @return void
     */
    protected function claimToSl8()
    {
        /** Retrieve User */
        $user = $this->getApi()->get('https://gold-eagle-api.fly.dev/user/me')->json();

        /** Ensure User is Registered */
        if ($user['is_sl8_user']) {
            /** Get SL8 Info */
            $sl8 = $this->getApi()->get('https://gold-eagle-api.fly.dev/me/sl8')->json();

            /** Wallet is Active */
            if ($sl8['wallet_status'] === 'Active') {
                /** Get Progress */
                $progress = $this->getApi()->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                /** Claim */
                $result = $this->getApi()->timeout(60)->post('https://gold-eagle-api.fly.dev/wallet/claim')->json();

                /** Send Claim Notification */
                $this->sendClaimNotification(
                    $progress['coins_amount'],
                    $sl8['wallet_address'],
                    $result['hash']
                );
            } else if ($sl8['wallet_status'] === 'Inactive') {
                $this->getApi()->post('https://gold-eagle-api.fly.dev/slate/wallet/activate')->json();
            }
        }
    }


    /**
     * Send Claim Notification
     * @param int $amount
     * @param string $address
     * @param string $hash
     * @return void
     */
    protected function sendClaimNotification(
        $amount,
        $address,
        $hash
    ) {

        $formattedAmount = number_format($amount);
        $addressLink = "https://stellar.expert/explorer/public/account/$address";
        $txLink = "https://stellar.expert/explorer/public/tx/$hash";



        /** Send Message */
        Helpers::sendUserMessage(
            'stardust-claim',
            $this->farmer,
            [
                "<b>💰 Amount</b>: <a href=\"$txLink\">$formattedAmount</a>",
                "<b>📘 Address</b>: <a href=\"$addressLink\">$address</a>",
                "<b>🧾 Hash</b>: $hash",
                "<a href=\"$txLink\">View Transaction</a>",
                "<blockquote><i>Successfully claimed <a href=\"$txLink\"><b>$formattedAmount StarDust</b></a> to <a href=\"$addressLink\"><b>$address</b></a></i></blockquote>",
            ],
            false
        );
    }
}