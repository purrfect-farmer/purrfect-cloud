<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Models\Farmer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use GuzzleHttp\Cookie\CookieJar;

class FarmUnijump extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:unijump';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Unijump Automatically';


    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://unijump.xyz';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            $this->getFarmers()->mapConcurrently(function (Farmer $farmer) {
                try {
                    /** Get API */
                    $api = $this->getUnijumpApi($farmer);

                    /** Get User */
                    $utc = $api->get('https://unijump.xyz/api/v1/player/utc')->json('utc');
                    $player = $api->get('https://unijump.xyz/api/v1/player/state')->json();
                    $leagueLevel = $player['leagueLevel'];


                    /** Claim Daily Rewards */
                    $dailyRewards = $player['dailyRewards'] ?? null;
                    if ($dailyRewards) {
                        $currentDay = $dailyRewards['currentDay'];
                        $milestones = collect($dailyRewards['milestones']);
                        $rewards = collect($dailyRewards['rewards']);

                        $currentReward = $rewards->first(fn($item) => $item['day'] === $currentDay);
                        $currentMilestone = $milestones->first(fn($item) => $item['day'] === $currentDay);

                        if ($currentReward && !$currentReward['claimed']) {
                            $api->post('https://unijump.xyz/api/v1/player/daily-reward/claim', []);
                        }
                    }


                    /** Claim Leagues */
                    $leaguesToClaim = collect($player['leaguesToClaim'] ?? []);
                    $leaguesToClaim->each(
                        fn($league) => $api->post('https://unijump.xyz/api/v1/leagues/reward/claim/' . $league)
                    );


                    /** Farming */
                    if ($leagueLevel >= 3) {
                        $farming = $player['farming'] ?? null;
                        if (!$farming) {
                            $api->post('https://unijump.xyz/api/v1/farming/start');
                        } else if ($farming['endsAt'] < $utc) {
                            $api->post('https://unijump.xyz/api/v1/farming/claim');
                            $api->post('https://unijump.xyz/api/v1/farming/start');

                        }
                    }

                    /** Lootbox */
                    $lootboxesInfo = $player['lootboxesInfo'];

                    /** Get Free Lootbox */
                    if ($lootboxesInfo['freeAvailableAt'] < $utc) {
                        $api->get('https://unijump.xyz/api/v1/lootboxes/get_free');
                    }

                    /** Open Lootboxes */
                    foreach ($lootboxesInfo['availableLootboxes'] as $type => $count) {
                        for ($i = 0; $i < $count; $i++) {
                            $api->post(
                                'https://unijump.xyz/api/v1/lootboxes/open',
                                ['lootboxType' => $type]
                            );
                        }
                    }

                    /** Free Spin */
                    $wheelSpins = $player['wheelSpins'];

                    /** Get Free Spin */
                    if ($wheelSpins['freeAvailableAt'] < $utc) {
                        $api->post('https://unijump.xyz/api/v1/fortune-wheel/free-spin');
                    }
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError($e, $farmer);

                    /** Refetch Auth or Disconnect Farmer */
                    $this->refetchAuthOrDisconnect($farmer);
                }
            });
        });
    }

    protected function getUnijumpApi(Farmer $farmer)
    {
        /** Cookies */
        $cookies = new CookieJar();

        /** Get API */
        $api = $this->getApi($farmer)->withOptions(['cookies' => $cookies]);

        /** Fetch CSRF Token */
        $api->get('https://unijump.xyz/api/v1/auth/login', ['initData' => $farmer->getInitData()]);

        return $api;
    }
}
