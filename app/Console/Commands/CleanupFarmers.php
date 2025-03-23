<?php

namespace App\Console\Commands;

use App\Models\Farmer;
use Illuminate\Console\Command;

class CleanupFarmers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:farmers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup Farmers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Farmer::withoutEvents(function () {
            Farmer::whereNotIn(
                'farmer',
                collect(config('farmer.drops'))
                    ->filter(fn($drop) => $drop['enabled'])
                    ->keys()
            )->delete();
        });
        $this->info("Farmers Deleted");
    }
}
