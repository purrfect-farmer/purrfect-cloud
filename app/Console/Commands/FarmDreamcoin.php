<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\DreamcoinFarmer;
use Illuminate\Console\Command;

class FarmDreamcoin extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:dreamcoin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Dreamcoin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => DreamcoinFarmer::farm($farmer));
        });
    }
}