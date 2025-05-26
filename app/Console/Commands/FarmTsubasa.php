<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\TsubasaFarmer;
use Illuminate\Console\Command;

class FarmTsubasa extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:tsubasa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Tsubasa Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => TsubasaFarmer::farm($farmer));

        });
    }
}
