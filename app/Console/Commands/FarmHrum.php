<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\HrumFarmer;
use Illuminate\Console\Command;

class FarmHrum extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:hrum';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Hrum Automatically';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => HrumFarmer::farm($farmer));

        });
    }
}