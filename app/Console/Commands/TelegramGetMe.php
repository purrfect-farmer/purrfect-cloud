<?php

namespace App\Console\Commands;

use App\Libraries\TelegramClient;
use Illuminate\Console\Command;

class TelegramGetMe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:get-me {session?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets User';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = TelegramClient::session(
            $this->argument('session') ?? 'default'
        );

        dump($client->getSelf());
    }
}
