<?php

namespace App\Console\Commands;

use App\Libraries\TelegramClient;
use Illuminate\Console\Command;

class TelegramListSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:list-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List Sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->table(
            ['Name'],
            collect(TelegramClient::getSessions())
                ->map(
                    fn($item) => [$item]
                )
        );
    }
}
