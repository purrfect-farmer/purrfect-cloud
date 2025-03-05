<?php

namespace App;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
     * @param bool $deletePreviousMessage
     * @return \Telegram\Bot\Objects\Message|null
     */
    public static function sendCloudFarmerMessage(
        $key,
        $text,
        $options = [],
        $deletePreviousMessage = true
    ) {
        /** Disable Messages */
        if (config('farmer.disable_telegram_messages')) return;

        /** Configure Params */
        $params = [
            'chat_id' => config('farmer.chat_id'),
            'disable_notification' => true,
            'parse_mode' => 'HTML',
            'text' => is_array($text) ? implode("\n", $text) : $text,
            ...$options
        ];

        /** Send New Message */
        $message = Telegram::bot()->sendMessage($params);

        /** Delete Previous Message */
        if ($deletePreviousMessage) {
            $cacheKey = 'cloud-message:' . $key;
            $previousMessageId = Cache::get($cacheKey);
            if ($previousMessageId) {
                try {
                    Telegram::bot()->deleteMessage([
                        'chat_id' => $params['chat_id'],
                        'message_id' => $previousMessageId
                    ]);
                } catch (\Throwable $e) {
                }
            }

            /** Put New Message Id in Cache */
            Cache::forever(
                $cacheKey,
                $message->messageId
            );
        }

        return $message;
    }

    /**
     * Send Farming Completed Message
     * @param string $farmer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return \Telegram\Bot\Objects\Message
     */
    public static function sendFarmingCompletedMessage(
        $farmer,
        $startDate,
        $endDate
    ) {
        $config = config('farmer.drops')[$farmer];
        $title = $config['title'];
        $links = static::getCloudAccountLinks(
            Account::farmer($farmer)->get()
        );
        $key = $farmer . '.completed';

        return static::sendCloudFarmerMessage(
            $key,
            [
                "<b>$title</b>",
                "<i>✅ Status: Completed</i>",
                $links,
                "<b>🗓️ Start Date</b>: $startDate",
                "<b>🗓️ End Date</b>: $endDate"
            ],
            ['message_thread_id' => $config['thread_id']]
        );
    }


    /**
     * Send Message to User
     * @param string $key
     * @param \App\Models\Account $account
     * @param array $message
     * @param bool $deletePreviousMessage
     * @return \Telegram\Bot\Objects\Message
     */
    public static function sendUserMessage(
        $key,
        Account $account,
        $message,
        $deletePreviousMessage = true
    ) {
        /** Message Key */
        $key =  implode(':', [
            $account->farmer,
            $account->user_id,
            $key
        ]);

        /** Title */
        $title = config('farmer.drops')[$account->farmer]['title'];


        /** User ID */
        $id = $account->user_id;

        /** Username */
        $username =
            htmlspecialchars(
                '@' . Str::limit(
                    $account->telegram_web_app['initDataUnsafe']['user']['username'] ?? '' ?: $id,
                    15
                )
            );

        /** User Mention Link */
        $link = "<a href=\"tg://user?id=$id\">$username</a>";

        /** Date */
        $date = now();

        /** Send Message */
        return static::sendCloudFarmerMessage(
            $key,
            [
                "<b>$title</b>",
                "<b>👤 Account</b>: $link",
                "<b>🗓️ Date</b>: $date",
                ...$message
            ],
            [
                'chat_id' => $account->user_id,
                'disable_notification' => false,
            ],
            $deletePreviousMessage
        );
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
            'chat_id' => config('farmer.chat_id'),
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
            'chat_id' => config('farmer.chat_id'),
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
        $list = $accounts->map(function (Account $account) {
            $id = $account->user_id;
            $status = $account->is_connected ? '✅' : '❌';

            /** Username */
            $username = Str::padRight(
                Str::lower(
                    Str::limit(
                        $account->telegram_web_app['initDataUnsafe']['user']['username'] ?? ''
                            ?: $id,
                        12
                    )
                ),
                15,
                '  '
            );

            /** Farmer Title */
            $title = config('farmer.display_farmer_title') ? Str::upper(
                Str::limit(
                    $account->telegram_web_app['farmerTitle'] ?? 'TGUser',
                    8
                )
            ) : '';

            return compact(
                'id',
                'status',
                'username',
                'title'
            );
        });

        /** Sort By Title */
        if (config('farmer.display_farmer_title')) {
            $list = $list->sortBy('title');
        }

        /** Retrieve Links */
        $links = $list->map(function ($data) {
            $id = $data['id'];
            $status = $data['status'];
            $username = htmlspecialchars('@' . $data['username']);
            $title = $data['title'] ? '<b>' . htmlspecialchars('(' . $data['title'] . ')') . '</b>' : '';

            return "$status $title <a href=\"tg://user?id=$id\">$username</a>";
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

    /**
     * Get extra points
     * @param int $points
     * @return int
     */
    public static function extraGamePoints($points)
    {
        return intval(
            $points + static::randomPercent($points, 0, 20)
        );
    }

    /**
     * Get a random percentage of the value
     * @param int $value
     * @param int $min
     * @param int $max
     * @return float
     */
    public static function randomPercent($value, $min = 0, $max = 100)
    {
        return floor(
            $value * rand($min, $max) / 100
        );
    }

    /**
     * Check if url is a Telegram Link
     * @param string $link
     * @return bool
     */
    public static function isTelegramLink($link)
    {
        return $link && preg_match(
            '/^(http|https):\/\/t\.me\/.+/',
            $link
        );
    }
}
