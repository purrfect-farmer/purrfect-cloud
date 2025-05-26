<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\SlotcoinFarmer;
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
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => SlotcoinFarmer::farm($farmer));
        });
    }
}