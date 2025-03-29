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
        $passed = [];
        $failed = [];

        foreach (Proxy::list() as $proxy) {
            /** Display Comment */
            $this->comment('Testing: ' . $proxy);

            try {
                /** Get IP */
                $ip = Http::throw()->withOptions(['proxy' => 'http://' . $proxy])
                    ->get('http://checkip.amazonaws.com')
                    ->body();

                /** Add to Passed List */
                $passed[] = [$proxy];

                /** Log Info */
                $this->info(
                    'PASSED: ' . $ip
                );
            } catch (\Throwable $e) {
                /** Add to Failed List */
                $failed[] = [$proxy];

                /** Log Error */
                $this->error('ERROR: ' . $proxy);
            }
        }

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
