<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\Farmer;
use App\Models\Account;
use Illuminate\Console\Command;

class FarmSlotcoin extends Command
{
    use Farmer;

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
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Retrieve Accounts */
            $accounts = $this->retrieveAccounts();

            /** Tap */
            while ($accounts->isNotEmpty()) {
                $accounts = $this->farmAccounts($accounts);
            }
        });
    }


    /**
     *  Set Authorization
     * @param \App\Models\Account $account
     * @return void
     */
    protected function setAuth(Account $account)
    {
        /** Get Access Token */
        $accessToken = $this->getBaseApi($account)
            ->post('https://api.slotcoin.app/v1/clicker/auth', [
                'initData' => $account->telegram_web_app['initData'],
                'referralCode' => ''
            ])
            ->json('accessToken');

        /** Set Headers */
        $account->setAuthorizationHeader($accessToken);
    }

    protected function farmAccounts($accounts)
    {
        return $accounts->map(function ($item) {
            try {
                $account = $item['account'];
                $ticketsCount = $item['ticketsCount'];
                $energy = $item['energy'];
                $bid = $item['bid'];


                if ($ticketsCount > 0) {
                    /** Subtract Ticket */
                    $ticketsCount -= 1;

                    /** Spin Ticket */
                    $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/daily/spin');
                }

                /** Deduct Energy */
                $energy -= $bid;

                /** Spin Lottery */
                $this->getApi($account)
                    ->post(
                        'https://api.slotcoin.app/v1/clicker/api/spin',
                    );

                /** Return Energy and Account */
                if ($energy >= $bid || $ticketsCount > 0) {
                    return compact(
                        'account',
                        'ticketsCount',
                        'energy',
                        'bid',
                    );
                }
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e);
            }
        })->filter();
    }

    protected function retrieveAccounts()
    {
        return Account::farmer('slotcoin')
            ->connected()
            ->get()->map(function (Account $account) {
                try {
                    /** Set Auth */
                    $this->setAuth($account);

                    /** Daily Check-In */
                    $dailyCheckIn = $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/check-in/info')->json();
                    $timeToClaim = intval($dailyCheckIn['time_to_claim']);

                    /** Claim Daily Check-In */
                    if ($timeToClaim <= 0) {
                        $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/check-in/claim');
                    }

                    /** Get Info */
                    $info = $this->getApi($account)->post('https://api.slotcoin.app/v1/clicker/api/info')->json();

                    /** Tickets */
                    $ticketsCount = intval($info['user']['daily_roulette_count']);

                    /** Energy */
                    $energy = intval($info['user']['spins']);
                    $bid = intval($info['user']['bid']);

                    /** Return Energy and Account */
                    if ($energy >= $bid || $ticketsCount > 0) {
                        return compact(
                            'account',
                            'ticketsCount',
                            'energy',
                            'bid',
                        );
                    }
                } catch (\Throwable $e) {
                    /** Disconnect Account */
                    $account->disconnect();

                    /** Log Error */
                    $this->logError($e);
                }
            })->filter();
    }
}
