<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FarmGoldEagle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:gold-eagle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Gold Eagle Automatically';


    /**
     * Http User Agent
     *
     * @var string
     */
    protected $userAgent = 'Mozilla/5.0 (Linux; Android 14; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.135 Mobile Safari/537.36 Telegram-Android/11.5.5 (Samsung SM-G991U1; Android 14; SDK 34; HIGH)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Start Farming
        Account::where('farmer', 'gold-eagle')
            ->get()
            ->each(function (Account $account) {
                try {
                    /** API */
                    $api = Http::withHeaders($account->headers)
                        ->withUserAgent($this->userAgent);

                    /** Get Progress */
                    $progress = $api->get('https://gold-eagle-api.fly.dev/user/me/progress')->json();

                    /** Tap */
                    if ($progress['energy'] >= 10) {
                        $api->post('https://gold-eagle-api.fly.dev/tap', [
                            'available_taps' => 1,
                            'count' => $progress['energy'],
                            'timestamp' => time(),
                            'salt' => Str::uuid()->toString()
                        ])->json();
                    }
                } catch (\Throwable $e) {
                    $account->delete();
                }
            });

        // Log Farming Completion
        Log::info('Completed Gold Eagle Farming - ' . now());
    }
}
