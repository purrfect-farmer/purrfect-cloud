<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\Farmer;
use App\Helpers;
use App\Models\Account;
use ParagonIE\ConstantTime\Base32;
use ParagonIE\ConstantTime\Hex;
use ParagonIE\ConstantTime\Base64;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OTPHP\TOTP;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PublicKey;

class FarmGoldEagle extends Command
{
    use Farmer;

    /**
     * TOTP Secret
     * @var string
     */
    const SECRET = 'FZYQHANLB3I2KAWEOKI4T2PVXHHZ4K5F';

    /**
     * Private Key
     * @var string
     */
    const PUBLIC_KEY = '-----BEGIN PUBLIC KEY----- MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyH0A/d/2Dc1QGDCpVgD/ 8Xx1o3GHccjybtK3AM4Wv0faLZL6J1jDLGdmOEnE2+HkTuxTBSVBZT1a+8Iazxkd LqTihCZxGUxp6i9CZatICimC7LbdGJW++t+X9l7EH6uEBPuSjQcuNuaODQkefncW //rni5iksdd3pjQRLM+PVEMzPw+pvgfPfAn0fUDqer0itUJFQ5P0+tVaL/6AlcBY EqnirvIo8tfps/+9yGqc2znCVWwaR+1uCeVZ6gbt96XPVxaGf+hKn+TwiJo2sykH OGADDSK8sEWca7DqSQScGSTc5/DD2CeSK78pwlhYOQb6694PI0Cr5g+tpPm94gk/ nwIDAQAB -----END PUBLIC KEY-----';

    /**
     * TOTP Instance
     * @var TOTP
     */
    protected $otp;

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
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Get OTP */
            if (!$this->getOtp()) return false;

            /** Start Farming */
            Account::farmer('gold-eagle')
                ->connected()
                ->get()
                ->each(function (Account $account) {
                    try {
                        /** Get Progress */
                        $progress = $this->getApi($account)->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                        /** Tap */
                        if ($progress['energy'] >= 10) {
                            $energy = $progress['energy'];
                            $weight = $progress['tap_weight'];
                            $percent = 90 + rand(0, 8);
                            $claim = floor(
                                ($energy * $percent) / 100
                            );
                            $taps = floor(
                                $claim / $weight
                            );

                            /** Calculate Data */
                            $data = $this->calculateData($taps);

                            /** Send Taps */
                            $result = $this->getApi($account)->post('https://gold-eagle-api.fly.dev/tap', [
                                'data' => $data,
                            ])->json();
                        }

                        /** Claim to Wallet */
                        if ($progress['coins_amount'] >= 50_000) {
                            $tasks = $this->getApi($account)
                                ->get('https://gold-eagle-api.fly.dev/task/my/available')->json();

                            $claimable = collect($tasks)->every(
                                fn($task) => $task['task_type'] !== 'Sl8' || $task['status'] === 'Completed'
                            );

                            /** Claim To Sl8 */
                            if ($claimable) {
                                $this->claimToSl8($account);
                            }
                        }
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError($e);

                        /** Disconnect Account */
                        $account->disconnect();
                    }
                });
        });
    }

    /**
     * Claim to Sl8
     * @param \App\Models\Account $account
     * @return void
     */
    protected function claimToSl8(Account $account)
    {
        /** Retrieve User */
        $user = $this->getApi($account)->get('https://gold-eagle-api.fly.dev/user/me')->json();

        /** Ensure User is Registered */
        if ($user['is_sl8_user']) {
            /** Get SL8 Info */
            $sl8 = $this->getApi($account)->get('https://gold-eagle-api.fly.dev/me/sl8')->json();

            /** Wallet is Active */
            if ($sl8['wallet_status'] === 'Active') {
                /** Get Progress */
                $progress = $this->getApi($account)->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                /** Claim */
                $result = $this->getApi($account)->post('https://gold-eagle-api.fly.dev/wallet/claim')->json();

                /** Send Claim Notification */
                $this->sendClaimNotification(
                    $account,
                    $progress['coins_amount'],
                    $sl8['wallet_address'],
                    $result['hash']
                );
            }
        }
    }


    /**
     * Send Claim Notification
     * @param \App\Models\Account $account
     * @param int $amount
     * @param string $address
     * @param string $hash
     * @return void
     */
    protected function sendClaimNotification(
        Account $account,
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
            $account,
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
     * Get Nonce
     *
     * @return string
     */
    protected function getNonce()
    {
        return Base64::encode($this->otp->now());
    }

    /**
     * Calculate Data
     *
     * @param mixed $taps
     * @return string
     */
    protected function calculateData($taps)
    {
        /** Encode as JSON */
        $json = json_encode([
            'st' => $taps,
            'ct' => $this->getNonce(),
        ]);

        /**
         * @var PublicKey
         */
        $key = PublicKeyLoader::load(static::PUBLIC_KEY);
        $data = $key->withPadding(RSA::ENCRYPTION_PKCS1)->encrypt($json);

        return Base64::encode($data);
    }


    /**
     * Convert Base32 Secret to Hex
     * @param string $secret
     * @return string
     */
    protected function secretToHex($secret)
    {
        $bytes = Base32::decodeUpper($secret);
        $hex = Hex::encode($bytes);
        return $hex;
    }

    /**
     * Get TOTP Generator
     * @return TOTP|null
     */
    protected function getOtp()
    {
        try {
            $config = Http::get("https://raw.githubusercontent.com/purrfect-farmer/purrfect-data/main/config.json")->json();
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

            $otp = TOTP::createFromSecret(static::SECRET);

            $otp->setDigest('sha256');
            $otp->setDigits(6);
            $otp->setPeriod(3);

            /** Set OTP */
            $this->otp = $otp;

            return $otp;
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);
        }
    }
}
