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
    protected $signature = 'app:test-proxies';

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
        /** Log */
        $this->info('Testing Proxies...');

        /** Get Results */
        $tests = Proxy::testProxies();
        $passed = $tests->filter(fn($item) => $item['status'])->map(fn($item) => [$item['proxy']]);
        $failed = $tests->filter(fn($item) => $item['status'] === false)->map(fn($item) => [$item['proxy']]);

        /** List Total Passed */
        $this->info('TOTAL PASSED: ' . count($passed));
        $this->table(['Proxy'], $passed);

        /** Separator */
        $this->newLine();

        /** List Total Failed */
        $this->error('TOTAL FAILED: ' . count($failed));
        $this->table(['Proxy'], $failed);
    }
}
