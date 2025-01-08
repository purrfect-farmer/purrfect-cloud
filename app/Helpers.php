<?php

namespace App;

use Illuminate\Support\Arr;

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
}
