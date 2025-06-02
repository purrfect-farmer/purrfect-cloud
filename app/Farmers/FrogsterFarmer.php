<?php
namespace App\Farmers;


class FrogsterFarmer extends BaseFarmer
{

    protected $key = 'frogster';
    protected $origin = 'https://frogster.app';

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

            /** Claim */
            $this->getApi()->post('https://frogster.app/api/wallets/claim-ton-new?claim_plan_type=1');
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Refetch Auth or Disconnect Farmer */
            $this->refetchAuthOrDisconnect();
        }
    }
}