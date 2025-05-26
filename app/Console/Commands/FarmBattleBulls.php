<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\BattleBullsFarmer;
use App\Models\Farmer;
use Illuminate\Console\Command;

class FarmBattleBulls extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:battle-bulls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Battle Bulls Automatically';

    /**
     * The origin for all requests.
     *
     * @var string
     */
    protected $origin = 'https://tg.battle-games.com';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Retrieve Farmers */
            $this->retrieveFarmers();
        });
    }

    /**
     *  Set Authorization
     * @param \App\Models\Farmer $farmer
     * @return Farmer
     */
    protected function setAuth(Farmer $farmer)
    {
        /** Init Data */
        $initData = $farmer->getInitData();

        /** Set Headers */
        return $farmer->setAuthorizationHeader($initData);
    }

    protected function retrieveFarmers()
    {
        return $this->getFarmers()->mapConcurrently(
            fn($farmer) => BattleBullsFarmer::farm($farmer)
        );
    }
}