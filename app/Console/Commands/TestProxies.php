<?php

namespace App\Console\Commands;

use App\Facades\Proxy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestProxies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmer:test-proxies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Proxies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        foreach (Proxy::list() as $proxy) {
            $this->line('Testing: ' . $proxy);
            try {
                $this->info(
                    'PASS: ' . Http::withOptions(['proxy' => 'http://' . $proxy])
                        ->get('http://checkip.amazonaws.com')
                        ->body()
                );
            } catch (\Throwable $e) {
                $this->error('ERROR: ' . $proxy);
            }
        }
    }
}
