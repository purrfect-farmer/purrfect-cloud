<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\VoxelFarmer;
use Illuminate\Console\Command;

class FarmVoxel extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:voxel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Voxel Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => VoxelFarmer::farm($farmer));
        });
    }
}