<?php
namespace App\Libraries;

use App\Contracts\TelegramClientInterface;
use App\Helpers;
use Illuminate\Support\Facades\Http;

class GramJS implements TelegramClientInterface
{
    public function __construct(protected string $session = 'default')
    {
    }

    protected static function getHttpApi()
    {
        return Http::throw()
            ->baseUrl('http://127.0.0.1:6767')
            ->withHeader(
                'x-api-key',
                config('gramjs.api_key')
            );
    }

    public function phoneLogin(string $phone)
    {
        $result = static::getHttpApi()->post('phone', [
            'session' => $this->session,
            'phone' => $phone,
        ])->json('result');

        return $result;
    }

    public function completePhoneLogin(string $code)
    {
        $result = static::getHttpApi()->post('code', [
            'session' => $this->session,
            'code' => $code,
        ])->json('result');

        return $result;
    }

    public function complete2faLogin(string $password)
    {
        $result = static::getHttpApi()->post('password', [
            'session' => $this->session,
            'password' => $password,
        ])->json('result');

        return $result;
    }


    public function logout()
    {
        return static::getHttpApi()->post('logout', [
            'session' => $this->session,
        ])->json('result');
    }

    public function getSelf()
    {
        return static::getHttpApi()->post('self', [
            'session' => $this->session,
        ])->json('result');
    }

    public function getWebview(string $url)
    {
        $parsed = Helpers::parseTelegramBotUrl(
            $url
        );

        return static::getHttpApi()->post('webview', [
            'session' => $this->session,
            'bot' => $parsed['bot'] ?? '',
            'shortName' => $parsed['short_name'] ?? '',
            'startParam' => $parsed['start_param'] ?? ''
        ])->json('result');
    }

    public function joinTelegramLink(string $url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $entries = explode("/", trim($path, "/"));

        return static::getHttpApi()->post('join', [
            'session' => $this->session,
            'entity' => $entries[0] ?? '',
        ])->json('result');
    }

    public static function session(string $session)
    {
        return new static($session);
    }

    public static function getSessions()
    {
        return static::getHttpApi()->post('sessions')->json('result');
    }

    public static function sessionExists($session)
    {
        return static::getHttpApi()->post('exists', ['session' => $session])->json('result');
    }
}