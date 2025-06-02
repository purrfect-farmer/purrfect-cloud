<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Libraries\TelegramClient;
use Illuminate\Console\Command;

class TelegramJoinLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:join-link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Telegram Join Link';


    /**
     * Telegram API Client
     * @var TelegramClient
     */
    protected $api;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->api = TelegramClient::session();

        $result = $this->api->joinTelegramLink(config('farmer.farmer_channel_link'));

        dump($result);
    }
}
