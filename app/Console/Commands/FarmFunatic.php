<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class FarmFunatic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:funatic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Funatic Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // Retrieve Accounts
        $accounts = Account::where('farmer', 'funatic')
            ->get()->map(function (Account $account) {
                try {

                    /** API */
                    $api = Http::withHeaders($account->headers)
                        ->withUserAgent(
                            $account->headers['User-Agent'] ?: Helpers::getUserAgent($account->user_id)
                        );

                    /** Get Boosters */
                    $boosters = $api->get('https://clicker.api.funtico.com/boosters')->json('data');
                    $availableBoosters = collect($boosters)->filter(
                        fn($item) => (
                            $item['price'] === 0 &&
                            $item['isActive'] === false &&
                            $item['cooldownLeft'] === 0 &&
                            $item['usagesLeft'] !== 0
                        )
                    );

                    /** Purchase Booster */
                    if ($availableBoosters->isNotEmpty()) {
                        $availableBoosters->each(function ($booster) use ($api) {
                            /** Activate Booster */
                            $api->post(
                                'https://clicker.api.funtico.com/boosters/activate',
                                [
                                    'boosterType' => $booster['type']
                                ]
                            );
                        });
                    }


                    /** Get Game */
                    $game = $api->get('https://clicker.api.funtico.com/game')->json('data');
                    $energy = $game['energy']['currentEnergyBalance'];


                    /** Return Energy and Account */
                    if ($energy > 0) {
                        return compact(
                            'account',
                            'energy'
                        );
                    }
                } catch (\Throwable $e) {
                    $account->delete();
                }
            })->filter();



        /** Tap */
        while ($accounts->isNotEmpty()) {
            $accounts = $accounts->map(function ($item) {
                try {
                    $account = $item['account'];
                    $energy = $item['energy'];

                    $taps = min($energy, 8 + rand(0, 2));
                    $energy -= $taps;



                    /** Tap */
                    Http::withHeaders($account->headers)
                        ->withUserAgent(
                            $account->headers['User-Agent'] ?: Helpers::getUserAgent($account->user_id)
                        )
                        ->post(
                            'https://clicker.api.funtico.com/tap',
                            [
                                'taps' => $taps
                            ]
                        );

                    /** Return Energy and Account */
                    if ($energy > 0) {
                        return compact(
                            'account',
                            'energy'
                        );
                    }
                } catch (\Throwable $e) {
                }
            })->filter();


            /** Log Taps */
            Log::info('Funatic Taps - ' . now());

            /** Sleep */
            Sleep::for(500)->milliseconds();
        }

        /** Log Farming Completion */
        Log::info('Completed Funatic Farming - ' . now());
    }
}