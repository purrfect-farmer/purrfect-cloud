<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Helpers;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FarmGoldEagle extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:gold-eagle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Gold Eagle Automatically';

    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://telegram.geagle.online';


    /**
     * Set Auth only on error
     * @var boolean
     */
    protected $setAuthOnlyOnError = true;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Check Script */
            if (!$this->checkScript())
                return false;

            /** Start Farming */
            $this->getFarmers()->mapConcurrently(
                function (Farmer $farmer) {
                    try {
                        /** Get Progress */
                        $progress = $this->getApi($farmer)->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                        /** Refill */
                        if ($progress['energy'] < ($progress['max_energy'] * 0.2)) {
                            $this->getApi($farmer)
                                ->post('https://gold-eagle-api.fly.dev/user/me/refill');
                        }

                        /** Claim to Wallet */
                        if (
                            $progress['coins_amount'] >= $progress['max_coins_amount']
                        ) {
                            $tasks = $this->getApi($farmer)
                                ->get('https://gold-eagle-api.fly.dev/task/my/available')
                                ->collect();

                            $hasCompletedTasks = $tasks->every(
                                fn($task) => $task['task_type'] !== 'Sl8' || $task['status'] === 'Completed'
                            );

                            if ($hasCompletedTasks === false) {
                                return;
                            }

                            $boosters = $this->getApi($farmer)
                                ->get('https://gold-eagle-api.fly.dev/boosters')
                                ->collect();
                            $claimBooster = $boosters->first(
                                fn($item) => $item['booster_type'] === 'Claim' && $item['purchased']
                            );

                            /** Check Booster */
                            if ($claimBooster === null) {
                                return;
                            }

                            /** Claim To Sl8 */
                            try {
                                $this->claimToSl8($farmer);
                            } catch (\Throwable $e) {
                                /** Log Error */
                                $this->logError($e, $farmer);
                            }
                        }
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError($e, $farmer);

                        /** Refetch Auth or Disconnect Farmer */
                        $this->refetchAuthOrDisconnect($farmer);
                    }
                }
            );
        });
    }


    /**
     *  Set Authorization
     * @param \App\Models\Farmer $farmer
     * @param array $data
     * @return Farmer
     */
    protected function setAuth(Farmer $farmer)
    {
        $query = http_build_query(
            data: [
                'tgWebAppData' => $farmer->getInitData(),
                'tgWebAppPlatform' => 'android',
                'tgWebAppVersion' => '8.4',
            ],
        );

        $url = 'https://telegram.geagle.online/?tgWebAppStartParam=r_ubdOBYN6KX#' . $query;

        /** Get Access Token */
        $accessToken = $this->getBaseApi($farmer)
            ->post('https://gold-eagle-api.fly.dev/login/telegram', [
                'init_data_raw' => $url
            ])
            ->json('access_token');

        /** Set Headers */
        return $farmer->setAuthorizationHeader('Bearer ' . $accessToken);
    }

    /**
     * Claim to Sl8
     * @param \App\Models\Farmer $farmer
     * @return void
     */
    protected function claimToSl8(Farmer $farmer)
    {
        /** Retrieve User */
        $user = $this->getApi($farmer)->get('https://gold-eagle-api.fly.dev/user/me')->json();

        /** Ensure User is Registered */
        if ($user['is_sl8_user']) {
            /** Get SL8 Info */
            $sl8 = $this->getApi($farmer)->get('https://gold-eagle-api.fly.dev/me/sl8')->json();

            /** Wallet is Active */
            if ($sl8['wallet_status'] === 'Active') {
                /** Get Progress */
                $progress = $this->getApi($farmer)->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                /** Claim */
                $result = $this->getApi($farmer)->timeout(60)->post('https://gold-eagle-api.fly.dev/wallet/claim')->json();

                /** Send Claim Notification */
                $this->sendClaimNotification(
                    $farmer,
                    $progress['coins_amount'],
                    $sl8['wallet_address'],
                    $result['hash']
                );
            } else if ($sl8['wallet_status'] === 'Inactive') {
                $this->getApi($farmer)->post('https://gold-eagle-api.fly.dev/slate/wallet/activate')->json();
            }
        }
    }


    /**
     * Send Claim Notification
     * @param \App\Models\Farmer $farmer
     * @param int $amount
     * @param string $address
     * @param string $hash
     * @return void
     */
    protected function sendClaimNotification(
        Farmer $farmer,
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
            $farmer,
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

    /**
     * Check Script
     * @return boolean|null
     */
    protected function checkScript()
    {
        try {
            $config = Http::throw()->get("https://raw.githubusercontent.com/purrfect-farmer/purrfect-data/main/config.json")->json();
            $index = $config['gold-eagle']['index'];
            $script = Helpers::findDropMainScript('https://telegram.geagle.online', $index);
            $hasNotifiedDev = Cache::has('error-notice:gold-eagle');
            if (!$script) {
                if (!$hasNotifiedDev) {
                    /** Cache */
                    Cache::forever('error-notice:gold-eagle', true);

                    /** Send  */
                    Helpers::sendCloudFarmerMessage(
                        'error-notice:gold-eagle',
                        [
                            "<b>🥇 Gold Eagle Farmer</b>",
                            "<i>❌ Status: Broken</i>",
                            "<b>🗓️ Detected At</b>: " . now(),
                        ],
                        [
                            'message_thread_id' => config('farmer.error_thread_id'),
                            'disable_notification' => false,
                        ]
                    );
                }
                return;
            }

            /** Remove from cache if resolved */
            if ($hasNotifiedDev) {
                Cache::delete('error-notice:gold-eagle');
            }

            return true;
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);
        }
    }
}