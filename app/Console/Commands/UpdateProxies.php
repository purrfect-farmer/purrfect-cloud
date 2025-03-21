<?php

namespace App\Console\Commands;

use App\Facades\Proxy;
use Illuminate\Console\Command;

class UpdateProxies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmer:update-proxies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Proxies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (config('farmer.proxy.enabled')) {
            Proxy::updateList();
        }
    }
}
