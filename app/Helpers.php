<?php

namespace App;

use App\Models\Farmer;
use Base64Url\Base64Url;
use Elliptic\EdDSA;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\Uri\Uri;
use PHPHtmlParser\Dom;
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
        "Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6667.121 Mobile Safari/537.36 Telegram-Android/11.6.1 (Pixel 8; Android 13; SDK 33; HIGH)",
    ];

    /**
     * Get User Agent with Seed
     * @param int $seed
     * @return string
     */
    public static function getUserAgent(int $seed)
    {
        return static::getUniqueItem(
            static::USER_AGENTS,
            $seed
        );
    }

    /**
     * Get Unique Item from an Array Based on Seed
     * @param array $collection
     * @param int $seed
     * @return mixed|null
     */
    public static function getUniqueItem(array $collection, int $seed)
    {
        $count = count($collection);

        if ($count === 0) {
            return null;
        }

        return $collection[$seed % $count];
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
        if (config('farmer.disable_telegram_messages')) {
            return;
        }

        /** Configure Params */
        $params = [
            'chat_id' => config('farmer.chat_id'),
            'disable_notification' => true,
            'parse_mode' => 'HTML',
            'text' => is_array($text) ? implode("\n", $text) : $text,
            ...$options,
        ];

        try {

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
                            'message_id' => $previousMessageId,
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
        } catch (\Throwable $e) {
            Log::error('Telegram Message', [
                'message' => $e->getMessage(),
                'params' => $params,
            ]);
        }
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
        $links = static::getCloudUserLinks(
            Farmer::with(['account'])
                ->farmer($farmer)
                ->subscribed()
                ->get()
                ->map(fn(Farmer $farmer) => [
                    'id' => $farmer->user_id,
                    'status' => $farmer->is_connected ? '✅' : '❌',
                    'session' => $farmer->account->session_id ? '🟨' : '🟪',
                    'username' => $farmer->getInitDataUnsafe()['user']['username'] ?? '',
                    'title' => $farmer->getFarmerTitle(),

                ])
        );

        $key = $farmer . '.completed';

        return static::sendCloudFarmerMessage(
            $key,
            [
                "<b>$title</b>",
                "<i>✅ Status: Completed</i>",
                $links,
                "<b>🗓️ Start Date</b>: $startDate",
                "<b>🗓️ End Date</b>: $endDate",
            ],
            ['message_thread_id' => $config['thread_id']]
        );
    }

    /**
     * Send Message to User
     * @param string $key
     * @param \App\Models\Farmer $farmer
     * @param array $message
     * @param bool $deletePreviousMessage
     * @return \Telegram\Bot\Objects\Message
     */
    public static function sendUserMessage(
        $key,
        Farmer $farmer,
        $message,
        $deletePreviousMessage = true
    ) {
        /** Message Key */
        $key = implode(':', [
            $farmer->farmer,
            $farmer->user_id,
            $key,
        ]);

        /** Title */
        $title = config('farmer.drops')[$farmer->farmer]['title'];

        /** User ID */
        $id = $farmer->user_id;

        /** Username */
        $username =
            htmlspecialchars(
                '@' . Str::limit(
                    $farmer->getInitDataUnsafe()['user']['username'] ?? '' ?: $id,
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
                "<b>👤 User</b>: $link",
                "<b>🗓️ Date</b>: $date",
                ...$message,
            ],
            [
                'chat_id' => $farmer->user_id,
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
            ...$options,
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
            ...$options,
        ];

        return Telegram::bot()->unpinChatMessage($params);
    }

    /**
     * Get User Links
     * @param \Illuminate\Database\Eloquent\Collection $collection
     * @return string
     */
    public static function getCloudUserLinks(Collection $collection)
    {
        $totalUsers = $collection->count();
        $list = $collection->map(function (array $data) {
            $id = $data['id'];
            $status = $data['status'];
            $session = $data['session'];

            /** Username */
            $username = Str::padRight(
                Str::lower(
                    Str::limit($data['username'] ?: $id, 12)
                ),
                15,
                '  '
            );

            /** Farmer Title */
            $title = config('farmer.display_farmer_title') ? Str::upper(
                Str::limit($data['title'], 8)
            ) : '';

            return compact(
                'id',
                'status',
                'session',
                'username',
                'title'
            );
        });

        /** Sort By Title or Username */
        $list = $list->sortBy(config('farmer.display_farmer_title') ? 'title' : 'username')->values();

        /** Retrieve Links */
        $links = $list->map(function ($data) {
            $id = $data['id'];
            $status = $data['status'];
            $session = $data['session'];
            $username = htmlspecialchars('@' . $data['username']);
            $title = $data['title'] ? ' ' . '<b>' . htmlspecialchars('(' . $data['title'] . ')') . '</b>' : '';

            return $status . ' ' . $session . "$title <a href=\"tg://user?id=$id\">$username</a>";
        })->implode("\n");

        return "\n<blockquote><b>👤 Users</b>: $totalUsers\n$links</blockquote>\n";
    }

    /** Fetch Content */
    public static function fetchContent($url)
    {
        return Http::throw()->get($url)->body();
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

        if (!$indexScript) {
            return;
        }

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

    /**
     * Parse Telegram Direct Link
     * @param string $url
     * @return array{bot: string, short_name: string, start_param: mixed}
     */
    public static function parseTelegramBotUrl(string $url)
    {
        $parsed = parse_url($url);
        $paths = explode("/", trim($parsed["path"], "/"));

        parse_str($parsed['query'] ?? '', $query);

        return [
            'bot' => '@' . $paths[0],
            'short_name' => $paths[1] ?? '',
            'start_param' => $query['start'] ?? $query['startapp'] ?? '',
        ];
    }

    /**
     * Check if is valid WebAppData
     * @param string $webAppData
     * @return bool
     */
    public static function isValidWebAppData(string $webAppData)
    {
        /** Calculate Secret */
        $secret = hash_hmac(
            "sha256",
            env('TELEGRAM_BOT_TOKEN', ''),
            "WebAppData",
            true
        );

        parse_str($webAppData, $data);

        $hash = $data["hash"];
        $check = collect($data)
            ->except('hash')
            ->sortKeys()
            ->map(fn($v, $k) => $k . '=' . $v)
            ->implode("\n");
        $compare = hash_hmac('sha256', $check, $secret);

        return hash_equals($hash, $compare);
    }

    /**
     * Check if is valid Ed25519 WebAppData
     * @param string $webAppData
     * @return bool
     */
    public static function isValidEd25519WebAppData(string $webAppData)
    {
        parse_str($webAppData, $data);

        $prefix = config('farmer.farmer_bot_id') . ":WebAppData\n";
        $check = collect($data)
            ->except(['hash', 'signature'])
            ->sortKeys()
            ->map(fn($v, $k) => $k . '=' . $v)
            ->implode("\n");

        $message = bin2hex($prefix . $check);
        $signature = bin2hex(Base64Url::decode($data["signature"]));

        $ec = new EdDSA('ed25519');
        $key = $ec->keyFromPublic(
            config('farmer.telegram_public_key')
        );

        return $key->verify($message, $signature);
    }

    /**
     * Get WebAppData
     * @param string $webAppData
     * @return array
     */
    public static function getWebAppData(string $webAppData)
    {
        /** Parse Data */
        parse_str($webAppData, $data);

        return [
            ...$data,
            'user' => json_decode($data['user'], true),
        ];
    }

    /**
     * Remove User from Group
     * @param int|string $id
     * @return void
     */
    public static function removeUserFromGroup($id)
    {
        /** Remove User */
        Telegram::bot()->banChatMember([
            'chat_id' => config('farmer.chat_id'),
            'user_id' => $id,
        ]);

        /** Unban User */
        Telegram::bot()->unbanChatMember([
            'chat_id' => config('farmer.chat_id'),
            'user_id' => $id,
            'only_if_banned' => true,
        ]);
    }

    /**
     * Is Group Member
     * @param int|string $id
     * @return bool
     */
    public static function isGroupMember($id)
    {
        return collect(['creator', 'administrator', 'member'])
            ->contains(
                Telegram::bot()
                    ->getChatMember([
                        'chat_id' => config('farmer.chat_id'),
                        'user_id' => $id,
                    ])->status
            );
    }

    /**
     * Create Invite Link
     * @param int|string $id
     * @return \Telegram\Bot\Objects\ChatInviteLink
     */
    public static function createInviteLink($id)
    {
        return Telegram::bot()->createChatInviteLink([
            'chat_id' => config('farmer.chat_id'),
            'name' => 'user-' . $id,
            'member_limit' => 1,
        ]);
    }

    /**
     * Send Invite Link
     * @param int|string $id
     */
    public static function sendInviteLink($id)
    {
        $result = static::createInviteLink($id);

        Telegram::bot()->sendMessage([
            'chat_id' => $id,
            'text' => $result->invite_link,
        ]);
    }
}