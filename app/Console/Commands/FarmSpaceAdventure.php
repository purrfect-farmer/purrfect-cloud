<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\SpaceAdventureFarmer;
use Illuminate\Console\Command;

class FarmSpaceAdventure extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:space-adventure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Space Adventure Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** Process Farmers */
        $this->getFarmers()->mapConcurrently(fn($farmer) => SpaceAdventureFarmer::farm($farmer));
    }
}
