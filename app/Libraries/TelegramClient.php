<?php
namespace App\Libraries;

use App\Contracts\TelegramClientInterface;

class TelegramClient
{
    public function __construct(
        protected TelegramClientInterface $client
    ) {
    }

    public function phoneLogin(string $phone)
    {
        return $this->client->phoneLogin($phone);
    }

    public function completePhoneLogin(string $code)
    {

        return $this->client->completePhoneLogin($code);
    }

    public function complete2faLogin(string $password)
    {
        return $this->client->complete2faLogin($password);
    }


    public function logout()
    {
        return $this->client->logout();
    }

    public function getSelf()
    {
        return $this->client->getSelf();
    }

    public function getWebview(string $url)
    {
        return $this->client->getWebview($url);
    }

    public static function session($sessionName = 'default')
    {
        return new static(
            config('farmer.telegram_client') === 'gramjs' ? new GramJS($sessionName) : new Madeline($sessionName)
        );
    }

    public static function getSessions()
    {
        return config('farmer.telegram_client') === 'gramjs' ? GramJS::getSessions() : Madeline::getSessions();
    }

    public static function sessionExists(string $session)
    {
        return config('farmer.telegram_client') === 'gramjs' ? GramJS::sessionExists($session) : Madeline::sessionExists($session);
    }

    public function getClient()
    {
        return $this->client;
    }

    public static function generateSession()
    {
        return bin2hex(random_bytes(8));
    }


    /**
     * Get TelegramData
     * @param string $url
     * @return array
     */
    public function getTelegramData($url)
    {
        $webview = $this->getWebview($url);

        return $this->extractTgWebAppData(
            $webview['url']
        );
    }

    /**
     * Extract tgWebAppData
     * @param string $url
     * @return array
     */
    public function extractTgWebAppData($url)
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