<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Libraries\TelegramClient;
use Illuminate\Console\Command;

class TelegramMiniAppData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:mini-app-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Telegram Get Mini-App Data';


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

        $parsed = Helpers::parseTelegramBotUrl(
            config('farmer.farmer_bot_link')
        );

        $result = $this->api->getWebview(config('farmer.farmer_bot_link'));

        dump($parsed);
        dump($result);
        dump($this->api->extractTgWebAppData($result['url']));
    }
}
