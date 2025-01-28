<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;

class Farm extends Command
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
        Concurrency::driver('fork')->run([
            fn() => $this->call('farm:gold-eagle'),
            fn() => $this->call('farm:funatic'),
            fn() => $this->call('farm:zoo'),
        ]);
    }
}
