<?php

namespace App\Libraries;


use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as AppInfoSettings;
use Illuminate\Contracts\Filesystem\Filesystem;

class Madeline
{
    public function __construct(
        protected Filesystem $disk
    ) {
        $this->disk->makeDirectory('madeline');
    }
    public function session($session = 'session.madeline')
    {
        return new API(
            $this->disk->path(
                $this->resolveSessionPath($session)
            ),
            (new Settings())
                ->setAppInfo(
                    (new AppInfoSettings)->setApiId(
                        2496
                    )->setApiHash(
                        '8da85b0d5bfe62527e5b244c209159c3'
                    )
                        ->setLangPack('webk')
                        ->setLangCode('en')
                        ->setSystemLangCode('en-US')
                        ->setAppVersion('2.2 K')
                        ->setDeviceModel('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36')
                        ->setSystemVersion('Linux x86_64')

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

    public static function logger() {}
}
