<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\DiggerFarmer;
use Illuminate\Console\Command;

class FarmDigger extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:digger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Digger Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => DiggerFarmer::farm($farmer));
        });
    }
}