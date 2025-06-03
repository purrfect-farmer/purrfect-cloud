<?php
namespace App\Farmers;

use App\Libraries\TelegramClient;
use App\Models\Farmer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseFarmer
{
    /** Farmer Key */
    protected $key;

    /** Origin */
    protected $origin;

    /** Requests Delay */
    protected $delay = 0;

    /**
     * Should set auth
     * @var boolean
     */
    protected $shouldSetAuth = false;

    protected ?TelegramClient $client = null;

    public function __construct(protected Farmer $farmer)
    {
        if ($this->farmer->account->session_id) {
            $this->client = TelegramClient::session($this->farmer->account->session_id);
            $this->updateWebAppData();
        }

        /** Set Auth */
        if ($this->shouldSetAuth) {
            try {
                $this->setAuth()->save();
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError($e);
            }
        }
    }

    protected function updateWebAppData()
    {
        try {
            /** Get Config */
            $config = config('farmer.drops')[$this->getKey()] ?? null;

            /** Get Web App Data */
            $data = $this->client->getTelegramData($config['telegram_link']);

            /** Update WebApp Data */
            $this->farmer->update([
                'is_connected' => true,
                'telegram_web_app' => [
                    'initData' => $data['initData']
                ]
            ]);
        } catch (\Throwable $e) {
            $this->logError($e);
            $this->client->logout();
            $this->farmer->account->update(['session_id' => null]);
            $this->client = null;
        }
    }


    /** Check if a value is a Telegram Link */
    protected function isTelegramLink($url)
    {
        return $url && preg_match(
            '/^(http|https):\/\/t\.me\/.+/',
            $url
        );
    }

    /** Check if can Join Telegram Link */
    protected function canJoinTelegramLink($url)
    {
        return $url &&
            preg_match('/^(http|https):\/\/t\.me\/[^\/\?]+$/i', $url) &&
            isset($this->farmer->account->session_id);
    }

    /** Join Telegram Link */
    protected function joinTelegramLink($url)
    {
        return $this->client->joinTelegramLink($url);
    }

    /** Try to Join Telegram Link */
    protected function tryToJoinTelegramLink($link)
    {
        if ($this->canJoinTelegramLink($link)) {
            $this->joinTelegramLink($link);
        }
    }

    /** Validate Telegram Task
     * Return true if it's not a valid link or it can join it
     */
    protected function validateTelegramTask($link)
    {
        return !$this->isTelegramLink($link) || $this->canJoinTelegramLink($link);
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
    protected function getProxyOptions()
    {
        if (config('farmer.proxy.enabled')) {
            $proxy = $this->farmer->account->proxy;
            return $proxy ? ['proxy' => 'http://' . $proxy] : [];
        } else {
            return [];
        }
    }

    /**
     * Get Farmer API
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getApi()
    {
        return $this->getBaseApi()
            ->replaceHeaders($this->farmer->headers)
            ->retry(2, 0, function (\Exception $exception, PendingRequest $request) {
                if (
                    !$exception instanceof RequestException ||
                    !$exception->response->status() ||
                    !method_exists($this, 'setAuth')
                ) {
                    return false;
                }

                /** Set Auth */
                $this->setAuth()->save();

                /** Replace Headers */
                $request->replaceHeaders($this->farmer->headers);

                return true;
            });
    }

    /**
     * Get Base Farmer API
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getBaseApi()
    {
        /** Delay */
        $this->delayRequest();

        return Http::throw()
            ->withRequestMiddleware(function (RequestInterface $request) {
                /** Log API Call */
                if (config('farmer.log_api_calls')) {
                    /** Log Info */
                    $this->logInfo('API Call', [
                        'method' => (string) $request->getMethod(),
                        'uri' => (string) $request->getUri(),
                        'body' => (string) $request->getBody(),
                        'headers' => $request->getHeaders()
                    ]);
                }

                /** Return Request */
                return $request;
            })
            ->withResponseMiddleware(function (ResponseInterface $response) {
                /** Log API Response */
                if (config('farmer.log_api_calls')) {
                    /** Log Info */
                    $this->logInfo('API Response', [
                        'status' => $response->getStatusCode(),
                        'headers' => $response->getHeaders(),
                        'body' => (string) $response->getBody()
                    ]);
                }

                return $response;
            })
            ->replaceHeaders(
                $this->getBaseHeaders()
            )
            ->withOptions(
                $this->getProxyOptions()
            )
            ->withUserAgent(
                $this->farmer->getUserAgent()
            );
    }

    /**
     * Log Info
     * @param string $title
     * @param array $data
     * @return void
     */
    protected function logInfo($title, $data = [])
    {
        Log::info($this->getTitle() . ' ' . $title, array_merge(
            [
                'title' => $this->farmer->getFarmerTitle(),
                'user_id' => $this->farmer->user_id ?? null,
                'username' => $this->farmer->getInitDataUnsafe()['user']['username'] ?? null,
            ],
            $data
        ));
    }


    /**
     * Log Farmer Error
     * @param \Throwable $e
     * @return void
     */
    protected function logError(\Throwable $e)
    {
        /** Log Error */
        Log::error($this->getTitle() . ' Error', array_merge(
            [
                'title' => $this->farmer->getFarmerTitle(),
                'user_id' => $this->farmer->user_id ?? null,
                'username' => $this->farmer->getInitDataUnsafe()['user']['username'] ?? null,
                'session' => $this->farmer->account->session_id ?? null,
            ],
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
        return $this->key;
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
        /** Delay */
        if ($this->delay > 0) {
            Sleep::for($this->delay)->seconds();
        }
    }

    /** Disconnect Farmer */
    protected function disconnect()
    {
        try {
            if (!$this->farmer->account->session_id) {
                /** Disconnect */
                $this->farmer->disconnect();
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);
        }

    }


    /**
     * Process Farmer
     * @param Farmer|int $farmer
     */
    public static function farm($farmer)
    {
        if (!$farmer instanceof Farmer) {
            $farmer = Farmer::findOrFail($farmer);
        }

        return app()->make(static::class, ['farmer' => $farmer])->process();
    }
}