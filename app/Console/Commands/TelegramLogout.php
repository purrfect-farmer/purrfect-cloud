<?php

namespace App\Console\Commands;

use App\Facades\Madeline;
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
        $client = Madeline::session();
        $client->logout();
    }
}
