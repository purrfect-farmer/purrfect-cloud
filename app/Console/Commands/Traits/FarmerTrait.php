<?php

namespace App\Console\Commands\Traits;

use App\Facades\Madeline;
use App\Helpers;
use App\Models\Farmer;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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
        try {
            /** Start Date */
            $startDate = now();

            /** Run Callback */
            $result = call_user_func($callback);

            if ($result !== false) {
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
    }

    /** Get Base Headers */
    protected function getBaseHeaders()
    {
        return [
            'Origin' => $this->origin,
            'Origins' => $this->origin,
            'Referer' => $this->origin . '/',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Cache-Control' => 'no-cache',
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
        return $this->getBaseApi($farmer)->replaceHeaders($farmer->headers)
            ->retry(
                2,
                0,
                function (Exception $exception, PendingRequest $request) use ($farmer) {
                    /** Log Error */
                    $this->logError($exception, $farmer);

                    if (
                        !($exception instanceof RequestException) ||
                        in_array(
                            $exception->response->status(),
                            [401, 403, 418]
                        ) === false
                    ) {
                        return false;
                    }

                    try {
                        if (method_exists($this, 'setAuth')) {
                            /** Update Auth */
                            $this->setAuth($farmer)->save();

                            /** Update Headers */
                            $request->replaceHeaders($farmer->headers);

                            return true;
                        }
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError($e, $farmer);
                    }
                    return false;
                }
            );
    }

    /**
     * Get Base Farmer API
     * @param \App\Models\Farmer $farmer
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getBaseApi(Farmer $farmer)
    {
        return Http::throw()
            ->withRequestMiddleware(function (RequestInterface $request) use ($farmer) {
                /** Log API Call */
                if (config('farmer.log_api_calls')) {

                    /** Log Info */
                    Log::info($this->getTitle() . ' API Call', [
                        'user_id' => $farmer->user_id ?? null,
                        'username' => $farmer->getInitDataUnsafe()['user']['username'] ?? null,
                        'method' => (string) $request->getMethod(),
                        'uri' => (string) $request->getUri(),
                        'body' => (string) $request->getBody(),
                        'headers' => $request->getHeaders()
                    ]);
                }

                /** Delay */
                $this->delayRequest();

                /** Return Request */
                return $request;
            })
            ->replaceHeaders(
                $this->getBaseHeaders()
            )
            ->withOptions(
                $this->getProxyOptions($farmer)
            )
            ->withUserAgent(
                $farmer->getUserAgent()
            );
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
        Log::error($this->getTitle() . ' Error', array_merge(
            $farmer ?
            [
                'user_id' => $farmer->user_id ?? null,
                'username' => $farmer->getInitDataUnsafe()['user']['username'] ?? null,
            ] : [],
            [

                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        ));
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
        return $this->getConfig()['title'];
    }

    /** Get Option */
    protected function getOption($option)
    {
        return $this->getConfig()['options'][$option];
    }

    /** Get Config */
    protected function getConfig()
    {
        return config('farmer.drops')[$this->getKey()];
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
     * Get Base Farmers
     * @return \Illuminate\Database\Eloquent\Builder<Farmer>
     */
    protected function getBaseFarmers()
    {
        return Farmer::with(['account'])
            ->farmer($this->getKey())
            ->subscribed();
    }

    /**
     * Get Farmers
     * @return \Illuminate\Database\Eloquent\Collection<int, Farmer>
     */
    protected function getFarmers($shouldSetAuth = false)
    {
        return $this->getBaseFarmers()
            ->get()
            ->mapConcurrently(function (Farmer $farmer) use ($shouldSetAuth) {
                if ($farmer->account->session_id) {
                    try {
                        /** Update Telegram Web App */
                        $this->updateTelegramWebApp($farmer);

                        /** Mark as connected */
                        $farmer->is_connected = true;
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError($e, $farmer);
                    }
                }

                /** Set Auth */
                if ($shouldSetAuth) {
                    try {
                        $this->setAuth($farmer);
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError($e, $farmer);
                    }
                }

                /** Save Farmer */
                if ($farmer->isDirty()) {
                    $farmer->save();
                }

                return $farmer;
            })->filter(
                fn(Farmer $farmer) => $farmer->is_connected
            );
    }

    /**
     * Update Telegram Data
     * @return void
     */
    protected function updateTelegramData()
    {
        $this->getBaseFarmers()
            ->needsRefetch()
            ->get()
            ->mapConcurrently(function (Farmer $farmer) {
                if ($farmer->account->session_id) {
                    try {
                        $this->updateFarmerData(
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
                if (method_exists($this, 'setAuth')) {
                    /** Update Auth */
                    $this->setAuth($farmer)->save();
                }
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e, $farmer);
            }
        } else {
            /** Disconnect */
            $farmer->disconnect();
        }
    }


    /**
     * Update Farmer Data
     * @param \App\Models\Farmer $farmer
     * @param boolean $shouldSetAuth
     * @return void
     */
    protected function updateFarmerData(Farmer $farmer, $shouldSetAuth = true)
    {
        /** Update TelegramWebApp */
        $this->updateTelegramWebApp($farmer);

        /** Try to Update Auth Headers */
        try {
            if (
                $shouldSetAuth && method_exists($this, 'setAuth')
            ) {
                $this->setAuth($farmer);
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
     * Update TelegramWebApp Data
     * @param \App\Models\Farmer $farmer
     * @return void
     */
    protected function updateTelegramWebApp(Farmer $farmer)
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
            ];
        } catch (\Throwable $e) {
            /** Logout */
            $api->logout();

            /** Remove Session */
            $farmer->account->forceFill(['session_id' => null])->save();

            /** Throw Error */
            throw $e;
        }
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