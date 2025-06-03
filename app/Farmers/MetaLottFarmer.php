<?php
namespace App\Farmers;


class MetaLottFarmer extends BaseFarmer
{

    protected $key = 'meta-lott';
    protected $origin = 'https://www.metalott.com';

    public function process()
    {
        try {
            /** Init Data Unsafe */
            $initDataUnsafe = $this->farmer->getInitDataUnsafe();

            /** Get API */
            $api = $this->getBaseApi();

            /** Get Access Token */
            $accessToken = $api
                ->asForm()
                ->replaceHeaders([
                    'Encryption' => '0'
                ])
                ->post(
                    'https://www.metalott.com/core/app/auth/login',
                    [
                        'tgUserId' => $initDataUnsafe['user']['id'],
                        'username' => $initDataUnsafe['user']['username'] ?? '',
                    ]
                )
                ->json('result');

            /** Update Headers */
            $api->replaceHeaders([
                'Authorization' => $accessToken,
                'X-Access-Token' => $accessToken,
            ]);

            /** Fetch Sign In Status */
            $signInStatus = $api
                ->post('https://www.metalott.com/core/app/signIn/signStatus')
                ->json('result');


            if ($signInStatus === 'FALSE') {
                $api
                    ->post('https://www.metalott.com/core/app/signIn/do')
                    ->json('result');
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }
}