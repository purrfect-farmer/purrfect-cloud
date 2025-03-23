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
            $this->requestAppWebView($parsed) :
            $this->requestMainWebView($parsed);

        dump($parsed);
        dump($result);
        dump($this->extractTgWebAppData($result['url']));
    }

    protected function getBotApp($parsed)
    {
        return $this->api->messages->getBotApp(
            app: [
                '_' => 'inputBotAppShortName',
                'bot_id' => $parsed['bot'],
                'short_name' => $parsed['short_name'],
            ]
        );
    }

    protected function startBot($parsed)
    {
        return $this->api->messages->startBot(
            bot: $parsed['bot'],
            start_param: $parsed['param'],
        );
    }

    protected function requestMainWebView($parsed)
    {
        return $this->api->messages->requestMainWebView(
            bot: $parsed['bot'],
            start_param: $parsed['param'],
            platform: 'android',
        );
    }

    protected function requestAppWebView($parsed)
    {
        return $this->api->messages->requestAppWebView(
            platform: 'android',
            start_param: $parsed['param'],
            app: [
                '_' => 'inputBotAppShortName',
                'bot_id' => $parsed['bot'],
                'short_name' => $parsed['short_name'],
            ],
        );
    }

    protected function extractTgWebAppData($url)
    {
        $parsedUrl = parse_url($url);
        $fragment = $parsedUrl['fragment'] ?? '';

        parse_str($fragment, $data);
        parse_str($data['tgWebAppData'], $initDataUnsafe);

        return [
            ...$data,
            'initDataUnsafe' => [
                ...$initDataUnsafe,
                'user' => json_decode($initDataUnsafe['user'], true),
            ],
        ];
    }
}
