<?php

namespace App\Console\Commands\Traits;

use App\Facades\Madeline;
use App\Facades\Proxy;
use App\Helpers;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

trait Farmer
{
    /**
     *  Execute farming
     * @param callable $callback
     * @return void
     */
    public function farm($callback)
    {
        Cache::lock($this->signature)->get(function () use ($callback) {
            /** Start Date */
            $startDate = now();

            /** Run Callback */
            $result = call_user_func($callback);

            if ($result !== false) {
                /** Update Telegram Data */
                $this->updateTelegramData();

                /** End Date */
                $endDate = now();

                /** Send Message */
                Helpers::sendFarmingCompletedMessage(
                    $this->getKey(),
                    $startDate,
                    $endDate
                );
            }
        });
    }

    /** Get Base Headers */
    protected function getBaseHeaders()
    {
        return [
            'Origin' => $this->origin,
            'Referer' => $this->origin . '/',
            'X-Requested-With' => 'org.telegram.messenger'
        ];
    }

    /** Get Proxy Options */
    protected function getProxyOptions(Account $account)
    {
        if (config('farmer.proxy.enabled')) {
            $proxy = Proxy::getUnique($account->user_id);
            return $proxy ? ['proxy' => 'http://' . $proxy] : [];
        } else {
            return [];
        }
    }

    /**
     * Get Account API
     * @param \App\Models\Account $account
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getApi(Account $account)
    {
        /** Delay */
        $this->delayRequest();

        return Http::withOptions(
            $this->getProxyOptions($account)
        )
            ->withHeaders($account->headers)
            ->withHeaders(
                $this->getBaseHeaders()
            )
            ->withUserAgent(
                $account->getUserAgent()
            );
    }

    /**
     * Get Base Account API
     * @param \App\Models\Account $account
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getBaseApi(Account $account)
    {
        /** Delay */
        $this->delayRequest();

        return Http::withOptions(
            $this->getProxyOptions($account)
        )
            ->withHeaders(
                $this->getBaseHeaders()
            )
            ->withUserAgent(
                $account->getUserAgent()
            );
    }


    /**
     * Log Farmer Error
     * @param \Throwable $e
     * @param Account|null $account
     * @return void
     */
    protected function logError(\Throwable $e, ?Account $account = null)
    {
        /** Farmer Title */
        $title = config('farmer.drops')[$this->getKey()]['title'];

        /** Log Error */
        Log::error($title . ' Error', [
            'message' => $e->getMessage(),
            'account' => $account->user_id ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    /**
     * Get Farmer Key
     * @return string
     */
    protected function getKey()
    {
        return explode(':', $this->signature)[1];
    }


    /**
     * Delay Request
     * @return void
     */
    protected function delayRequest()
    {
        if (property_exists($this, 'delay')) {
            /** Delay */
            Sleep::for($this->delay)->seconds();
        }
    }

    /**
     * Update Telegram Data
     * @return void
     */
    protected function updateTelegramData()
    {
        Account::farmer(
            $this->getKey()
        )
            ->with(['session'])
            ->where(
                'updated_at',
                '<',
                now()->subMinutes(30)
            )
            ->each(function (Account $account) {
                if ($account->session) {
                    try {
                        $this->refetchAuth(
                            $account,
                            property_exists(
                                $this,
                                'setAuthOnlyOnError'
                            ) ? $this->setAuthOnlyOnError === false : true
                        );
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError($e, $account);

                        /** Remove Session */
                        $account->session->delete();
                    }
                }
            });
    }

    /** Refetch Auth or Disconnect */
    protected function refetchAuthOrDisconnect(Account $account)
    {
        if ($account->session) {
            try {
                /** Refetch Auth using Session */
                $this->refetchAuth($account, true);
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $account);

                /** Remove Session */
                $account->session->delete();

                /** Disconnect */
                $account->disconnect();
            }
        } else {
            /** Disconnect */
            $account->disconnect();
        }
    }


    /**
     * Refetch Auth
     * @param \App\Models\Account $account
     * @param boolean $shouldSetAuth
     * @return void
     */
    protected function refetchAuth(Account $account, $shouldSetAuth = true)
    {
        $api = Madeline::session(
            $account->session->session_id
        );

        $result = $this->getTelegramData($api);

        /** Update Telegram Web App */
        $account->telegram_web_app = [
            ...$account->telegram_web_app,
            'initData' => $result['initData'],
            'initDataUnsafe' => $result['initDataUnsafe'],
        ];

        /** Try to Update Auth Headers */
        try {
            if (
                $shouldSetAuth && method_exists($this, 'setAuth')
            ) {
                $this->setAuth($account, $result);
            }
        } catch (\Throwable $e) {
            $this->logError($e, $account);
        }

        /** Mark as connected */
        $account->is_connected = true;

        /** Save the Account */
        $account->save();
    }

    /**
     * Get TelegramData
     * @param \danog\MadelineProto\API $api
     * @return array
     */
    protected function getTelegramData($api)
    {
        $parsed = Helpers::parseTelegramBotUrl(
            config('farmer.drops')[$this->getKey()]['telegram_link']
        );

        $webview = $parsed['short_name']  ?
            $this->requestAppWebView($api, $parsed) :
            $this->requestMainWebView($api, $parsed);

        return $this->extractTgWebAppData(
            $webview['url']
        );
    }


    /**
     * Call requestMainWebView
     * @param \danog\MadelineProto\API $api
     * @param array $parsed
     */
    protected function requestMainWebView($api, $parsed)
    {
        return $api->messages->requestMainWebView(
            bot: $parsed['bot'],
            platform: 'android',
        );
    }

    /**
     * Call requestAppWebView
     * @param \danog\MadelineProto\API $api
     * @param array $parsed
     */
    protected function requestAppWebView($api, $parsed)
    {
        return $api->messages->requestAppWebView(
            platform: 'android',
            app: [
                '_' => 'inputBotAppShortName',
                'bot_id' => $parsed['bot'],
                'short_name' => $parsed['short_name'],
            ],
        );
    }

    /**
     * Extract tgWebAppData
     * @param string $url
     * @return array
     */
    protected function extractTgWebAppData($url)
    {
        $parsedUrl = parse_url($url);
        $fragment = $parsedUrl['fragment'] ?? '';

        parse_str($fragment, $data);
        parse_str($data['tgWebAppData'], $initDataUnsafe);

        return [
            'url' => $url,
            'platform' => $data['tgWebAppPlatform'],
            'version' => $data['tgWebAppVersion'],
            'initData' => $data['tgWebAppData'],
            'initDataUnsafe' => [
                ...$initDataUnsafe,
                'user' => json_decode($initDataUnsafe['user'], true),
            ],
        ];
    }
}
