<?php

namespace App\Console\Commands;

use App\Facades\Madeline;
use App\Helpers;
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
     * @var \danog\MadelineProto\API
     */
    protected $api;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->api = Madeline::session();

        $parsed = Helpers::parseTelegramBotUrl(
            'https://t.me/purrfect_little_bot/app?startapp=purrfect'
        );

        $result = $parsed['short_name']  ?
            Madeline::requestAppWebView($this->api, $parsed) :
            Madeline::requestMainWebView($this->api, $parsed);

        dump($parsed);
        dump($result);
        dump(Madeline::extractTgWebAppData($result['url']));
    }
}
