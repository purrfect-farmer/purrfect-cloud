<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\FrogsterFarmer;
use Illuminate\Console\Command;

class FarmFrogster extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:frogster';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Frogster Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => FrogsterFarmer::farm($farmer));
        });
    }
}
