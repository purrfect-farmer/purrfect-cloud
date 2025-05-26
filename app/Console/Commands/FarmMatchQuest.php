<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\MatchQuestFarmer;
use Illuminate\Console\Command;

class FarmMatchQuest extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:matchquest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm MatchQuest Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => MatchQuestFarmer::farm($farmer));
        });
    }
}