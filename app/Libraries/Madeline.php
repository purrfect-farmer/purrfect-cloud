<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Storage;

class Madeline
{
    /**
     * Storage Disk
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected $disk;
    public function __construct()
    {
        $this->disk = Storage::disk('local');
        $this->disk->makeDirectory('madeline');
    }
    public function session($session = 'session.madeline')
    {
        return new \danog\MadelineProto\API(
            $this->disk->path('madeline/' . $session),
            (new \danog\MadelineProto\Settings())
                ->setAppInfo(
                    (new \danog\MadelineProto\Settings\AppInfo())->setApiId(
                        config('madeline.api_id')
                    )->setApiHash(
                        config('madeline.api_hash')
                    )
                )
        );
    }
}
