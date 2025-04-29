<?php

namespace App\Console\Commands;

use App\Facades\Proxy;
use App\Models\Account;
use Illuminate\Console\Command;

class UpdateProxies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-proxies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Proxies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** Update List */
        Proxy::updateList();

        /** Test Proxies */
        $results = Proxy::getWorkingProxies();

        /** Get List */
        $list = $results->sortBy('duration')->values()->pluck('proxy');

        /** Unset Proxy for Unsubscribed Accounts */
        Account::unsubscribed()->whereNotNull('proxy')->update(['proxy' => null]);

        /** Get Accounts */
        $accounts = Account::subscribed()->get();

        /** Used Proxies */
        $proxies = $accounts->pluck('proxy')->filter();

        /** Get Invalid Accounts */
        $invalidAccounts = $accounts->filter(
            fn($account) => $list->doesntContain($account->proxy)
        );

        /** Check if there are accounts to update */
        if ($invalidAccounts->isNotEmpty()) {
            /** Available Proxies */
            $available = $list->filter(
                fn($proxy) => $proxies->doesntContain($proxy)
            )->values();

            /** Update Proxy */
            $invalidAccounts
                ->each(
                    fn(Account $account) => $account->forceFill(
                        ['proxy' => $available->shift()]
                    )->save()
                );
        }
    }
}
