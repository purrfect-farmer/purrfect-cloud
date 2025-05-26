<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\BattleBullsFarmer;
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
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(
                fn($farmer) => BattleBullsFarmer::farm($farmer)
            );
        });
    }

}