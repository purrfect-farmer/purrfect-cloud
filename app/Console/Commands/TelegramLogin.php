<?php

namespace App\Console\Commands;

use App\Libraries\TelegramClient;
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
        $client = TelegramClient::session()->getClient();

        if ($client instanceof \App\Libraries\Madeline) {
            $api = $client->getApi();
            $api->start();
        } else {
            $this->warn('GramJS can not be started from CLI');
        }

    }
}
