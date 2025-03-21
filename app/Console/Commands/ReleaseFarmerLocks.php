<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReleaseFarmerLocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmer:release-locks {farmer?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Forcefully release locks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $names = $this->argument('farmer') ? ['farm:' . $this->argument('farmer')] :

            collect(config('farmer.drops'))
            ->keys()
            ->map(
                fn($key) =>  'farm:' . $key
            );

        foreach ($names as $item) {
            Cache::lock($item)->forceRelease();
            $this->info("Released: " . $item);
        }
    }
}
