<?php

namespace App\Console\Commands;

use App\Libraries\TelegramClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class TelegramCleanupSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:cleanup-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup Sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        collect(TelegramClient::getSessions())
            ->each(
                function ($session) {
                    try {
                        $client = TelegramClient::session($session);
                        if (!$client->getSelf()) {
                            $client->logout();
                        }
                    } catch (\Throwable $e) {
                        Log::error(
                            'Cleanup Session: ' . $session,
                            [
                                'error' => $e->getMessage()
                            ]
                        );
                    }
                }
            );
    }
}
