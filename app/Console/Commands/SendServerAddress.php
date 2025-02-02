<?php

namespace App\Console\Commands;

use App\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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
        $ip = trim(Http::get('http://checkip.amazonaws.com')->body());

        /** Address */
        $address = 'http://' . $ip . ':8000';

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
                'message_thread_id' => env('TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID'),
                'disable_notification' => false,
            ]
        );
    }
}
