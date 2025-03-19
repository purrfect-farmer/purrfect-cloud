<?php

namespace App\Console\Commands;

use App\Facades\Madeline;
use Illuminate\Console\Command;

class TelegramLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:login';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Telegram Login';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = Madeline::session();
        $client->start();
    }
}
