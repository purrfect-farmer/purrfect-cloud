<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Libraries\WebAppUpdater;
use App\Models\Account;
use Illuminate\Console\Command;

class UpdateWebAppData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmer:update-web-app-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update WebAppData of accounts with session';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = now();
        $this->getAccounts()->mapConcurrently(
            fn(Account $account) => WebAppUpdater::update($account)
        );

        $endDate = now();
        $links = $this->getCloudUserLinks();

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            'telegram:update-web-app-data',
            [
                "<b>🌐 Telegram WebAppData</b>",
                "<i>✅ Status: Completed</i>",
                $links,
                "<b>🗓️ Start Date</b>: $startDate",
                "<b>🗓️ End Date</b>: $endDate",
            ],
            ['message_thread_id' => config('farmer.announcement_thread_id')]
        );
    }

    /**
     * Get Accounts
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    protected function getAccounts()
    {
        return $this->getAccountsBuilder()->get();
    }

    /**
     * Get Accounts Builder
     * @return \Illuminate\Database\Eloquent\Builder<Account>
     */
    protected function getAccountsBuilder()
    {
        return Account::with('farmers')
            ->subscribed()
            ->whereNotNull('session_id');
    }


    /**
     * Get User Links
     * @return string
     */
    public function getCloudUserLinks()
    {
        $accounts = Account::subscribed()->get();
        $links = Helpers::getCloudUserLinks(
            $accounts->map(fn(Account $account) => [
                'id' => $account->user_id,
                'status' => $account->session_id ? '✅' : '❌',
                'session' => $account->session_id ? '🟨' : '🟪',
                'username' => $account->data['user']['username'] ?? '',
                'title' => $account->getFarmerTitle(),
            ])
        );

        return "\n<blockquote><i>WebAppData updated!</i></blockquote>$links";
    }
}
