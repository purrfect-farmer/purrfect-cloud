<?php

namespace App\Console\Commands;

use App\Facades\Madeline;
use App\Models\Account;
use Illuminate\Console\Command;

class ReassignTelegramSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:reassign-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reassign Telegram Sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        foreach (Madeline::getSessions() as $session) {
            $self = Madeline::session($session)->getSelf();

            if ($self) {
                Account::where('user_id', $self['id'])
                    ->update(['session_id' => $session]);
            }
        }
    }
}