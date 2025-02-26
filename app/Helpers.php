<?php

namespace App;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use League\Uri\Uri;
use Telegram\Bot\Laravel\Facades\Telegram;
use PHPHtmlParser\Dom;

class Helpers
{
    /**
     *  Mobile User Agents
     * @var array<string>
     */
    public const USER_AGENTS = [
        "Mozilla/5.0 (Linux; Android 14; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.135 Mobile Safari/537.36 Telegram-Android/11.6.1 (Samsung SM-G998B; Android 14; SDK 34; HIGH)",
        "Mozilla/5.0 (Linux; Android 14; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.135 Mobile Safari/537.36 Telegram-Android/11.6.1 (Pixel 8 Pro; Android 14; SDK 34; HIGH)",
        "Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6667.95 Mobile Safari/537.36 Telegram-Android/11.6.1 (Samsung SM-S918B; Android 13; SDK 33; HIGH)",
        "Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6667.75 Mobile Safari/537.36 Telegram-Android/11.6.1 (Pixel 7; Android 13; SDK 33; HIGH)",
        "Mozilla/5.0 (Linux; Android 14; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.112 Mobile Safari/537.36 Telegram-Android/11.6.1 (Samsung SM-F946B; Android 14; SDK 34; HIGH)",
        "Mozilla/5.0 (Linux; Android 14; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.120 Mobile Safari/537.36 Telegram-Android/11.6.1 (Pixel Fold; Android 14; SDK 34; HIGH)",
        "Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6667.81 Mobile Safari/537.36 Telegram-Android/11.6.1 (Samsung SM-X906B; Android 13; SDK 33; HIGH)",
        "Mozilla/5.0 (Linux; Android 12; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.6635.90 Mobile Safari/537.36 Telegram-Android/11.6.1 (Pixel 6 Pro; Android 12; SDK 32; HIGH)",
        "Mozilla/5.0 (Linux; Android 14; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.135 Mobile Safari/537.36 Telegram-Android/11.6.1 (Samsung SM-S911U; Android 14; SDK 34; HIGH)",
        "Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6667.121 Mobile Safari/537.36 Telegram-Android/11.6.1 (Pixel 8; Android 13; SDK 33; HIGH)"
    ];

    /**
     * Get User Agent with Seed
     * @param int $seed
     * @return string
     */
    public static function getUserAgent(int $seed)
    {
        /** Seed */
        mt_srand($seed);

        /** Get Index */
        $index = mt_rand(0, count(static::USER_AGENTS) - 1);

        /** Retrieve User Agent */
        $result = static::USER_AGENTS[$index];

        /** Reset Seed */
        mt_srand();

        /** Return Result */
        return $result;
    }

    /**
     * Get Previous Message Id
     * @param mixed $key
     */
    public static function getPreviousCloudMessageId($key)
    {
        return Cache::get('cloud-message:' . $key);
    }

    /**
     * Send Cloud Farmer Message
     * @param string $key
     * @param array|string $text
     * @param array $options
     * @return \Telegram\Bot\Objects\Message
     */
    public static function sendCloudFarmerMessage($key, $text, $options = [])
    {
        $cache_key = 'cloud-message:' . $key;
        $previous_message_id = Cache::get($cache_key);
        $params = [
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'message_thread_id' => env('TELEGRAM_CHAT_THREAD_ID'),
            'disable_notification' => true,
            'parse_mode' => 'HTML',
            'text' => is_array($text) ? implode("\n", $text) : $text,
            ...$options
        ];


        /** Delete Previous Message */
        try {
            if ($previous_message_id) {
                Telegram::bot()->deleteMessage([
                    'chat_id' => $params['chat_id'],
                    'message_id' => $previous_message_id
                ]);
            }
        } catch (\Throwable $e) {
        }

        /** Send New Message */
        $message = Telegram::bot()->sendMessage($params);

        /** Put Message Id in Cache */
        Cache::forever($cache_key, $message->messageId);

        return $message;
    }

    /**
     * Pin message
     * @param int $id
     * @param array $options
     * @return bool
     */
    public static function pinCloudMessage(int $id, $options = [])
    {
        $params = [
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'message_id' => $id,
            ...$options
        ];

        return Telegram::bot()->pinChatMessage($params);
    }

    /**
     * Unpin message
     * @param int $id
     * @param array $options
     * @return bool
     */
    public static function unpinCloudMessage(int $id, $options = [])
    {
        $params = [
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'message_id' => $id,
            ...$options
        ];

        return Telegram::bot()->unpinChatMessage($params);
    }

    /**
     * Get Account Links
     * @param \Illuminate\Database\Eloquent\Collection $accounts
     * @return string
     */
    public static function getCloudAccountLinks(Collection $accounts)
    {
        $totalUsers = $accounts->count();
        $links = $accounts->map(function (Account $account) {
            $id = $account->user_id;
            $status = $account->is_connected ? '✅' : '❌';
            $username = htmlspecialchars(

                '@' . Str::limit(
                    $account->telegram_web_app['initDataUnsafe']['user']['username'] ?? '' ?: $id,
                    13
                )
            );
            $farmerTitle = htmlspecialchars('(' . Str::padBoth(
                Str::limit(
                    $account->telegram_web_app['farmerTitle'] ?? 'TGUser',
                    8
                ),
                10,
                '.'
            ) . ')');


            return "$status <b>$farmerTitle</b> <a href=\"tg://user?id=$id\">$username</a>";
        })->implode("\n");

        return "\n<blockquote><b>👤 Accounts</b>: $totalUsers\n$links</blockquote>\n";
    }

    /** Fetch Content */
    public static function fetchContent($url)
    {
        return Http::get($url)->body();
    }

    /**
     * Find Website main script
     * @param string $url
     * @param string $name
     * @return mixed
     */
    public static function findDropMainScript($url, $name = "index")
    {
        $dom = new Dom;
        $dom->loadFromUrl(
            $url,
            ['removeScripts' => false]
        );

        $scripts = $dom->find('script');
        $indexScript = collect($scripts)
            ->first(
                fn($item) => (
                    $item->getAttribute('type') === 'module' &&
                    Str::of($item->getAttribute('src'))->contains($name)
                )
            );

        return $indexScript;
    }


    public static function getDropMainScript($url, $name = "index")
    {
        $indexScript = static::findDropMainScript($url, $name);

        if (!$indexScript) return;

        $scriptUrl = Uri::fromBaseUri($indexScript->getAttribute("src"), $url);
        $scriptResponse = static::fetchContent($scriptUrl);

        return $scriptResponse;
    }
}
