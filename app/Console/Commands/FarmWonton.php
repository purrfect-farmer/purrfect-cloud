<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\WontonFarmer;
use Illuminate\Console\Command;

class FarmWonton extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:wonton';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Wonton Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => WontonFarmer::farm($farmer));
        });
    }
}
