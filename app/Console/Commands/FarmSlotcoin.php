<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Models\Farmer;
use Illuminate\Console\Command;

class FarmSlotcoin extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:slotcoin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Slotcoin';


    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://app.slotcoin.app';


    /**
     * The delay in seconds for all requests.
     *
     * @var int
     */
    protected $delay = 2;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Retrieve Farmers */
            $farmers = $this->retrieveFarmers();

            /** Tap */
            while ($farmers->isNotEmpty()) {
                $farmers = $this->farmFarmers($farmers);
            }
        });
    }


    /**
     *  Set Authorization
     * @param \App\Models\Farmer $farmer
     * @return void
     */
    protected function setAuth(Farmer $farmer)
    {
        /** Get Access Token */
        $accessToken = $this->getBaseApi($farmer)
            ->post('https://api.slotcoin.app/v1/clicker/auth', [
                'initData' => $farmer->getInitData(),
                'referralCode' => 'a2dd-60f7'
            ])
            ->json('accessToken');

        /** Set Headers */
        $farmer->setAuthorizationHeader($accessToken);
    }

    protected function farmFarmers($farmers)
    {
        return $this->runConcurrently(
            $farmers->mapForConcurrency(function ($item) {
                try {
                    $farmer = $item['farmer'];
                    $ticketsCount = $item['ticketsCount'];
                    $energy = $item['energy'];
                    $bid = $item['bid'];


                    if ($ticketsCount > 0) {
                        /** Subtract Ticket */
                        $ticketsCount -= 1;

                        /** Spin Ticket */
                        $this->getApi($farmer)->post('https://api.slotcoin.app/v1/clicker/daily/spin');
                    }

                    /** Deduct Energy */
                    $energy -= $bid;

                    /** Spin Lottery */
                    $this->getApi($farmer)
                        ->post(
                            'https://api.slotcoin.app/v1/clicker/api/spin',
                        );

                    /** Return Energy and Farmer */
                    if ($energy >= $bid || $ticketsCount > 0) {
                        return compact(
                            'farmer',
                            'ticketsCount',
                            'energy',
                            'bid',
                        );
                    }
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $item['farmer']);
                }
            })
        )->filter();
    }

    protected function retrieveFarmers()
    {
        return $this->runConcurrently(
            $this->getFarmers()->mapForConcurrency(function (Farmer $farmer) {
                try {
                    /** Daily Check-In */
                    $dailyCheckIn = $this->getApi($farmer)->post('https://api.slotcoin.app/v1/clicker/check-in/info')->json();
                    $timeToClaim = intval($dailyCheckIn['time_to_claim']);

                    /** Claim Daily Check-In */
                    if ($timeToClaim <= 0) {
                        $this->getApi($farmer)->post('https://api.slotcoin.app/v1/clicker/check-in/claim');
                    }

                    /** Get Info */
                    $info = $this->getApi($farmer)->post('https://api.slotcoin.app/v1/clicker/api/info')->json();

                    /** Tickets */
                    $ticketsCount = intval($info['user']['daily_roulette_count']);

                    /** Energy */
                    $energy = intval($info['user']['spins']);
                    $bid = intval($info['user']['bid']);

                    /** Return Energy and Farmer */
                    if ($energy >= $bid || $ticketsCount > 0) {
                        return compact(
                            'farmer',
                            'ticketsCount',
                            'energy',
                            'bid',
                        );
                    }
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $farmer);

                    /** Refetch Auth or Disconnect Farmer */
                    $this->refetchAuthOrDisconnect($farmer);
                }
            })
        )->filter();
    }
}