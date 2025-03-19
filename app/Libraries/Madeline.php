<?php

namespace App\Libraries;


use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use danog\MadelineProto\Settings\AppInfo as AppInfoSettings;
use Illuminate\Contracts\Filesystem\Filesystem;

class Madeline
{
    public function __construct(
        protected Filesystem $disk
    ) {
        $this->disk->makeDirectory('madeline');
    }
    public function session(
        $apiId,
        $apiHash,
        $session = 'session.madeline'
    ) {
        return new API(
            $this->disk->path(
                $this->resolveSessionPath($session)
            ),
            (new Settings())
                ->setAppInfo(
                    (new AppInfoSettings)->setApiId(
                        $apiId
                    )->setApiHash(
                        $apiHash
                    )
                )
                ->setLogger(
                    (new LoggerSettings)
                        ->setType(Logger::LOGGER_FILE)
                        ->setLevel(Logger::LEVEL_FATAL)
                        ->setExtra(
                            storage_path('logs/MadelineProto.log')
                        )
                )
        );
    }

    public function sessionExists($session)
    {
        return $this->disk->exists(
            $this->resolveSessionPath($session)
        );
    }

    public function resolveSessionPath($session)
    {
        return 'madeline/' . $session;
    }
}
