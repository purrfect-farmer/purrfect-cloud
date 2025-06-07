<?php
namespace App\Farmers;


class SlotcoinFarmer extends BaseFarmer
{

    protected $key = 'slotcoin';
    protected $origin = 'https://app.slotcoin.app';

    protected $delay = 2;

    protected function setAuth()
    {
        /** Get Access Token */
        $accessToken = $this->getBaseApi()
            ->post('https://api.slotcoin.app/v1/clicker/auth', [
                'initData' => $this->farmer->getInitData(),
                'referralCode' => 'a2dd-60f7'
            ])
            ->json('accessToken');

        /** Set Headers */
        return $this->farmer->setAuthorizationHeader($accessToken);
    }

    public function process()
    {
        try {
            /** Daily Check-In */
            $dailyCheckIn = $this->getApi()->post('https://api.slotcoin.app/v1/clicker/check-in/info')->json();
            $timeToClaim = intval($dailyCheckIn['time_to_claim']);

            /** Claim Daily Check-In */
            if ($timeToClaim <= 0) {
                $this->getApi()->post('https://api.slotcoin.app/v1/clicker/check-in/claim');
            }

            /** Get Info */
            $info = $this->getApi()->post('https://api.slotcoin.app/v1/clicker/api/info')->json();

            /** Tickets */
            $ticketsCount = intval($info['user']['daily_roulette_count']);

            /** Energy */
            $energy = intval($info['user']['spins']);
            $bid = intval($info['user']['bid']);

            while ($ticketsCount > 0) {
                /** Subtract Ticket */
                $ticketsCount -= 1;

                /** Spin Ticket */
                $this->getApi()->post('https://api.slotcoin.app/v1/clicker/daily/spin');
            }

            while ($energy >= $bid) {
                /** Deduct Energy */
                $energy -= $bid;

                /** Spin Lottery */
                $this->getApi()
                    ->post(
                        'https://api.slotcoin.app/v1/clicker/api/spin',
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