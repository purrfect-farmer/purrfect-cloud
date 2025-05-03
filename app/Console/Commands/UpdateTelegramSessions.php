<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class UpdateTelegramSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:update-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Permissions of Session Files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** File System */
        $filesystem = new Filesystem();

        /** Paths */
        $paths = [
            base_path('MadelineProto.log'),
            public_path('MadelineProto.log'),
            storage_path("app/madeline"),
            storage_path("logs/madeline")
        ];

        foreach ($paths as $path) {
            if (file_exists($path) === false)
                continue;
            try {
                if (is_dir($path)) {
                    /** Get Directory Files */
                    $files = (new Finder())
                        ->ignoreDotFiles(false)
                        ->in($path);

                    foreach ($files as $item) {
                        /** Set Directory Permission */
                        if ($item->isDir()) {
                            $filesystem->chmod($item->getRealPath(), 0775);
                        }

                        /** Set File Permission */
                        if ($item->isFile()) {
                            $filesystem->chmod($item->getRealPath(), 0664);
                        }
                    }

                    /** Set Permission */
                    $filesystem->chmod($path, 0775);
                } else {
                    /** Set File Permission */
                    $filesystem->chmod($path, 0664);
                }
            } catch (\Throwable $e) {
                Log::error('MadelineProto PERMISSIONS: ' . $e->getMessage());
            }
        }
    }
}
