<?php

namespace App\Libraries;


use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as AppInfoSettings;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use danog\MadelineProto\Settings\Database\Mysql as MysqlSettings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class Madeline
{
    /**
     * Storage disk
     * @var Filesystem
     */
    protected Filesystem $disk;
    public function __construct()
    {
        /** Create Disk */
        $this->disk = Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/madeline'),
        ]);
    }

    /**
     * Get API
     * @param string $session
     * @return API
     */
    public function session(string $session = 'default')
    {
        return new API(
            $this->sessionPath($session),
            (new Settings())
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
                ->setDb(
                    (new MysqlSettings)
                        ->setUri(config('madeline.database.uri'))
                        ->setDatabase(config('madeline.database.database'))
                        ->setUsername(config('madeline.database.username'))
                        ->setPassword(config('madeline.database.password'))
                        ->setEphemeralFilesystemPrefix(
                            config('madeline.database.prefix') . $session
                        )
                )
                ->setLogger(
                    (new LoggerSettings)
                        ->setExtra(
                            $this->logPath($session)
                        )
                )
        );
    }

    /**
     * Check if session exists
     * @param string $session
     * @return bool
     */
    public function sessionExists(string $session)
    {
        return $this->disk->exists(
            $this->getSessionFile($session)
        );
    }

    /**
     * Get Sessions
     * @return array
     */
    public function getSessions()
    {
        return (
            collect($this->disk->directories())
            ->map(
                fn($item) => str_replace(['session_', '.madeline'], '', $item)
            )
            ->all()
        );
    }

    /** Generate Session */
    public function generateSession()
    {
        do {
            $session = bin2hex(random_bytes(8));
        } while ($this->sessionExists($session));

        return $session;
    }

    /** Get Session File */
    public function getSessionFile(string $session)
    {
        return 'session_' . $session . '.madeline';
    }

    /** Get Session Path */
    public function sessionPath(string $session)
    {
        return $this->disk->path(
            $this->getSessionFile($session)
        );
    }

    /** Get Log Path */
    public function logPath(string $session)
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
