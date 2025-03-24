<?php

namespace App\Console\Commands\Traits;

use App\Facades\Madeline;
use App\Helpers;
use App\Models\Farmer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Psr\Http\Message\RequestInterface;

trait FarmerTrait
{
    /**
     *  Execute farming
     * @param callable $callback
     * @return void
     */
    public function farm($callback)
    {
        Cache::lock($this->signature)->get(function () use ($callback) {
            try {
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
            } catch (\Throwable $e) {
                $this->logError($e);
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
    protected function getProxyOptions(Farmer $farmer)
    {
        if (config('farmer.proxy.enabled')) {
            $proxy = $farmer->account->proxy;
            return $proxy ? ['proxy' => 'http://' . $proxy] : [];
        } else {
            return [];
        }
    }

    /**
     * Get Farmer API
     * @param \App\Models\Farmer $farmer
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getApi(Farmer $farmer)
    {

        return $this->getBaseApi($farmer)->withHeaders($farmer->headers);
    }

    /**
     * Get Base Farmer API
     * @param \App\Models\Farmer $farmer
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getBaseApi(Farmer $farmer)
    {
        /** Delay */
        $this->delayRequest();

        return Http::throw()
            ->withRequestMiddleware(function (RequestInterface $request) use ($farmer) {
                /** Log API Call */
                if (config('farmer.log_api_calls')) {

                    /** Log Info */
                    Log::info($this->getTitle() . ' API Call', [
                        'user_id' => $farmer->user_id ?? null,
                        'username' => $farmer->telegram_web_app['initDataUnsafe']['user']['username'] ?? null,
                        'method' => (string) $request->getMethod(),
                        'uri' => (string) $request->getUri(),
                        'body' => (string) $request->getBody(),
                    ]);
                }

                /** Return Request */
                return $request;
            })
            ->withOptions(
                $this->getProxyOptions($farmer)
            )
            ->withHeaders(
                $this->getBaseHeaders()
            )
            ->withUserAgent(
                $farmer->getUserAgent()
            )
            ->timeout(30);
    }


    /**
     * Log Farmer Error
     * @param \Throwable $e
     * @param Farmer|null $farmer
     * @return void
     */
    protected function logError(\Throwable $e, ?Farmer $farmer = null)
    {
        /** Log Error */
        Log::error($this->getTitle() . ' Error', [
            'user_id' => $farmer->user_id ?? null,
            'username' => $farmer->telegram_web_app['initDataUnsafe']['user']['username'] ?? null,
            'message' => $e->getMessage(),
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

    /** Get Title */
    protected function getTitle()
    {
        return config('farmer.drops')[$this->getKey()]['title'];
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
     * Get Farmers
     * @return \Illuminate\Database\Eloquent\Collection<int, Farmer>
     */
    protected function getFarmers()
    {
        return Farmer::farmer($this->getKey())
            ->subscribed()
            ->connected()
            ->get();
    }

    /**
     * Update Telegram Data
     * @return void
     */
    protected function updateTelegramData()
    {
        Farmer::with(['account'])
            ->farmer(
                $this->getKey()
            )
            ->subscribed()
            ->needsRefetch()
            ->each(function (Farmer $farmer) {
                if ($farmer->account->session_id) {
                    try {
                        $this->refetchAuth(
                            $farmer,
                            property_exists(
                                $this,
                                'setAuthOnlyOnError'
                            ) ? $this->setAuthOnlyOnError === false : true
                        );
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError($e, $farmer);
                    }
                }
            });
    }

    /** Refetch Auth or Disconnect */
    protected function refetchAuthOrDisconnect(Farmer $farmer)
    {
        if ($farmer->account->session_id) {
            try {
                /** Refetch Auth using Session */
                $this->refetchAuth($farmer, true);
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $farmer);

                /** Disconnect */
                $farmer->disconnect();
            }
        } else {
            /** Disconnect */
            $farmer->disconnect();
        }
    }


    /**
     * Refetch Auth
     * @param \App\Models\Farmer $farmer
     * @param boolean $shouldSetAuth
     * @return void
     */
    protected function refetchAuth(Farmer $farmer, $shouldSetAuth = true)
    {
        $api = Madeline::session(
            $farmer->account->session_id
        );

        try {
            $result = $this->getTelegramData($api);

            /** Update Telegram Web App */
            $farmer->telegram_web_app = [
                ...$farmer->telegram_web_app,
                'initData' => $result['initData'],
                'initDataUnsafe' => $result['initDataUnsafe'],
            ];
        } catch (\Throwable $e) {
            /** Logout */
            $api->logout();

            /** Remove Session */
            $farmer->account->forceFill(['session_id' => null])->save();

            /** Throw Error */
            throw $e;
        }

        /** Try to Update Auth Headers */
        try {
            if (
                $shouldSetAuth && method_exists($this, 'setAuth')
            ) {
                $this->setAuth($farmer, $result);
            }
        } catch (\Throwable $e) {
            $this->logError($e, $farmer);
        }

        /** Mark as connected */
        $farmer->is_connected = true;

        /** Save the Farmer */
        $farmer->save();
    }

    /**
     * Get TelegramData
     * @param \danog\MadelineProto\API $api
     * @return array
     */
    protected function getTelegramData($api)
    {
        return Madeline::getTelegramData(
            $api,
            config('farmer.drops')[$this->getKey()]['telegram_link']
        );
    }
}
