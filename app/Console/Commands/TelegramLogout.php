<?php

namespace App\Console\Commands;

use App\Libraries\TelegramClient;
use Illuminate\Console\Command;

class TelegramLogout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:logout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Telegram Logout';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = TelegramClient::session();
        $client->logout();
    }
}
