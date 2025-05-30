<?php

namespace App\Libraries;

use App\Contracts\TelegramClientInterface;
use App\Helpers;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as AppInfoSettings;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use danog\MadelineProto\Settings\Database\Mysql as MysqlSettings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class Madeline implements TelegramClientInterface
{
    /**
     * Storage disk
     * @var Filesystem
     */
    protected Filesystem $disk;
    protected API $api;

    public function __construct(
        protected string $sessionName = 'default'
    ) {
        /** Create Disk */
        $this->disk = static::getDisk();

        /** Initiate API */
        $this->api = $this->session($this->sessionName);
    }

    public function getApi()
    {
        return $this->api;
    }

    public function phoneLogin(string $phone)
    {
        return $this->api->phoneLogin($phone);
    }

    public function completePhoneLogin(string $code)
    {

        return $this->api->completePhoneLogin($code);
    }

    public function complete2faLogin(string $password)
    {
        return $this->api->complete2faLogin($password);
    }


    public function logout()
    {
        return $this->api->logout();
    }

    public function getSelf()
    {
        return $this->api->getSelf();
    }

    /** Get Webview */
    public function getWebview(string $url)
    {
        $parsed = Helpers::parseTelegramBotUrl(
            $url
        );

        $webview = $parsed['short_name'] ?
            $this->requestAppWebView($this->api, $parsed) :
            $this->requestMainWebView($this->api, $parsed);

        return $webview;
    }


    /**
     * Call requestMainWebView
     * @param \danog\MadelineProto\API $api
     * @param array $parsed
     */
    public function requestMainWebView($api, $parsed)
    {
        return $api->messages->requestMainWebView(
            start_param: $parsed['start_param'],
            peer: $parsed['bot'],
            bot: $parsed['bot'],
            platform: 'android',
            theme_params: $this->getThemeParams()
        );
    }

    /**
     * Call requestAppWebView
     * @param \danog\MadelineProto\API $api
     * @param array $parsed
     */
    public function requestAppWebView($api, $parsed)
    {
        return $api->messages->requestAppWebView(
            platform: 'android',
            peer: $parsed['bot'],
            start_param: $parsed['start_param'],
            app: [
                '_' => 'inputBotAppShortName',
                'bot_id' => $parsed['bot'],
                'short_name' => $parsed['short_name'],
            ],
            theme_params: $this->getThemeParams()
        );
    }

    public function getThemeParams()
    {
        return [
            "bg_color" => "#ffffff",
            "text_color" => "#000000",
            "hint_color" => "#aaaaaa",
            "link_color" => "#006aff",
            "button_color" => "#2cab37",
            "button_text_color" => "#ffffff",
        ];
    }

    /**
     * Get API
     * @param string $session
     * @return API
     */
    public function session(string $session = 'default')
    {
        /** Settings */
        $settings = (new Settings())
            ->setAppInfo(
                (new AppInfoSettings)
                    ->setApiId(config('madeline.app.api_id'))
                    ->setApiHash(config('madeline.app.api_hash'))
                    ->setLangPack(config('madeline.app.lang_pack'))
                    ->setLangCode(config('madeline.app.lang_code'))
                    ->setAppVersion(config('madeline.app.app_version'))
                    ->setSystemLangCode(config('madeline.app.system_lang_code'))
                    ->setSystemVersion(config('madeline.app.system_version'))
                    ->setDeviceModel(config('madeline.app.device_model'))

            )
            ->setLogger(
                (new LoggerSettings)
                    ->setExtra(
                        static::logPath($session)
                    )
            );

        /** Apply Database Config */
        if (config('madeline.database.enabled')) {
            $settings->setDb(
                (new MysqlSettings)
                    ->setUri(config('madeline.database.uri'))
                    ->setDatabase(config('madeline.database.database'))
                    ->setUsername(config('madeline.database.username'))
                    ->setPassword(config('madeline.database.password'))
                    ->setEphemeralFilesystemPrefix(
                        config('madeline.database.prefix') . $session
                    )
            );
        }

        return new API(
            static::sessionPath($session),
            $settings
        );
    }

    public static function getDisk()
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/madeline'),
        ]);
    }

    /**
     * Check if session exists
     * @param string $session
     * @return bool
     */
    public static function sessionExists(string $session)
    {
        return static::getDisk()->exists(
            static::getSessionFilename($session)
        );
    }

    /**
     * Get Sessions
     * @return array
     */
    public static function getSessions()
    {
        return (
            collect(static::getDisk()->directories())
                ->map(
                    fn($item) => str_replace(['session_', '.madeline'], '', $item)
                )
                ->all()
        );
    }

    /** Generate Session */
    public static function generateSession()
    {
        do {
            $session = bin2hex(random_bytes(8));
        } while (static::sessionExists($session));

        return $session;
    }

    /** Get Session Filename */
    public static function getSessionFilename(string $session)
    {
        return 'session_' . $session . '.madeline';
    }

    /** Get Session Path */
    public static function sessionPath(string $session)
    {
        return static::getDisk()->path(
            static::getSessionFilename($session)
        );
    }

    /** Get Log Path */
    public static function logPath(string $session)
    {
        return tap(
            storage_path("logs/madeline/session_{$session}.log"),
            function ($path) {
                if (!is_dir(dirname($path))) {
                    mkdir(
                        dirname($path),
                        0755,
                        true
                    );
                }
            }
        );
    }
}