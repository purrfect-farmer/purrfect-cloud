<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\MetaLottFarmer;
use Illuminate\Console\Command;

class FarmMetaLott extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:meta-lott';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Meta Lott Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => MetaLottFarmer::farm($farmer));
        });
    }
}
