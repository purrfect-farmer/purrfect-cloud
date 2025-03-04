<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FarmAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm All';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        collect(config('farmer.drops'))
            ->filter(fn($drop) => $drop['enabled'])
            ->keys()
            ->each(
                fn($key) =>  $this->call('farm:' . $key)
            );
    }
}
