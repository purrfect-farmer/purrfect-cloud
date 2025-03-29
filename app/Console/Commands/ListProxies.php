<?php

namespace App\Console\Commands;

use App\Facades\Proxy;
use Illuminate\Console\Command;

class ListProxies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:list-proxies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List Proxies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->table(['Proxy'], collect(Proxy::list())->map(fn($item) => [$item]));
    }
}
