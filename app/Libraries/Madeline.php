<?php

namespace App\Libraries;


use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as AppInfoSettings;
use danog\MadelineProto\Settings\Database\Mysql as MysqlSettings;
use Illuminate\Contracts\Filesystem\Filesystem;

class Madeline
{
    public function __construct(
        protected Filesystem $disk
    ) {
        $this->disk->makeDirectory('madeline');
    }

    /**
     * Get API
     * @param string $session
     * @return API
     */
    public function session($session = 'session.madeline')
    {
        return new API(
            $this->disk->path(
                $this->resolveSessionPath($session)
            ),
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
                            config('madeline.database.prefix') . substr(md5($session), 0, 10)
                        )
                )
        );
    }

    /**
     * Check if session exists
     * @param string $session
     * @return bool
     */
    public function sessionExists($session)
    {
        return $this->disk->exists(
            $this->resolveSessionPath($session)
        );
    }

    /**
     * Resolve Session Path
     * @param string $session
     * @return string
     */
    public function resolveSessionPath($session)
    {
        return 'madeline/' . $session;
    }

    /**
     * Get Sessions
     * @return \Illuminate\Support\Collection<mixed, array|string>
     */
    public function getSessions()
    {
        return collect($this->disk->directories('madeline'))->map(
            fn($item) => str_replace('madeline/', '', $item)
        );
    }
}
