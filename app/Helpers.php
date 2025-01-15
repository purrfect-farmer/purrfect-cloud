<?php

namespace App;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Laravel\Facades\Telegram;

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
     * Send Cloud Farmer Message
     * @param string $key
     * @param array|string $text
     * @return \Telegram\Bot\Objects\Message
     */
    public static function sendCloudFarmerMessage($key, $text)
    {
        $cacheKey = 'cloud-message:' . $key;
        $previousMessageId = Cache::get($cacheKey);

        /** Delete Previous Message */
        try {
            if ($previousMessageId) {
                Telegram::bot()->deleteMessage([
                    'chat_id' => env('TELEGRAM_CHAT_ID'),
                    'message_id' => $previousMessageId
                ]);
            }
        } catch (\Throwable $e) {
        }

        /** Send New Message */
        $message = Telegram::bot()->sendMessage([
            'disable_notification' => true,
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'message_thread_id' => env('TELEGRAM_CHAT_THREAD_ID'),
            'parse_mode' => 'HTML',
            'text' => is_array($text) ? implode("\n", $text) : $text
        ]);

        /** Put Message Id in Cache */
        Cache::forever($cacheKey, $message->messageId);

        return $message;
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
            $username =
                '@' . Str::of($account->telegram_web_app['initDataUnsafe']['user']['username'] ?? '' ?: $id)
                ->limit(15);

            return "<a href=\"tg://user?id=$id\">$username</a>";
        })->implode("\n");

        return "\n<blockquote><b>👤 Accounts</b>: $totalUsers\n$links</blockquote>\n";
    }
}
