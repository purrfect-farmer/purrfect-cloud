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
    protected $signature = 'farmer:update-proxies';

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
        if (config('farmer.proxy.enabled')) {
            /** Update List */
            Proxy::updateList();

            /** Get List */
            $list = collect(Proxy::list());

            /** Get Accounts */
            $accounts = Account::all();

            /** Used Proxies */
            $proxies = $accounts->pluck('proxy')->filter();

            /** Get Invalid Accounts */
            $invalidAccounts = $accounts->filter(
                fn($account) => $list->doesntContain($account->proxy)
            );

            /** Check if there are accounts to update */
            if ($invalidAccounts->isNotEmpty()) {
                /** Available Proxies */
                $available = Proxy::getAvailable($proxies);

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
}
