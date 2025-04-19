<?php

namespace App\Console\Commands;

use App\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendServerAddress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-server-address';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Server Address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** Get IP */
        $ip = trim(Http::throw()->get('http://checkip.amazonaws.com')->body());

        /** Address */
        $address = 'http://' . $ip . ':8000';

        /** Publish to Seeker */
        if (config('seeker.enabled')) {
            try {
                Http::baseUrl(
                    config('seeker.server')
                )
                    ->throw()
                    ->replaceHeaders([
                        'Accept' => 'application/json',
                    ])
                    ->post("/api/servers", [
                        'key' => config('seeker.key'),
                        'name' => config('app.name'),
                        'address' => $address
                    ]);
            } catch (\Throwable $e) {
                Log::error('Seeker Error', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
            }
        }

        /** Get Date */
        $date = now()->toDateString();

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            'app:server-address',
            [
                "<b>☁️ Latest Cloud Server</b>",
                "<b>🚀 Address</b>: $address",
                "<b>🗓️ Updated</b>: $date",
            ],
            [
                'message_thread_id' => config('farmer.announcement_thread_id'),
                'disable_notification' => false,
            ]
        );
    }
}
