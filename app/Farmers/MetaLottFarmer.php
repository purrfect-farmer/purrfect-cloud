<?php
namespace App\Farmers;


class MetaLottFarmer extends BaseFarmer
{

    protected $key = 'meta-lott';
    protected $origin = 'https://www.metalott.com';

    protected function setAuth()
    {
        /** Init Data Unsafe */
        $initDataUnsafe = $this->farmer->getInitDataUnsafe();

        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->asForm()
            ->post(
                'https://www.metalott.com/core/app/auth/login',
                [
                    'tgUserId' => $initDataUnsafe['user']['id'],
                    'username' => $initDataUnsafe['user']['username'] ?? '',
                ]
            )
            ->json('result');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader($accessToken);
    }

    public function process()
    {
        try {
            $signInStatus = $this->getApi()
                ->post('https://www.metalott.com/core/app/signIn/signStatus')
                ->json('result');

            if ($signInStatus === 'FALSE') {
                $this->getApi()
                    ->post('https://www.metalott.com/core/app/signIn/do')
                    ->json('result');
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Refetch Auth or Disconnect Farmer */
            $this->refetchAuthOrDisconnect();
        }
    }
}