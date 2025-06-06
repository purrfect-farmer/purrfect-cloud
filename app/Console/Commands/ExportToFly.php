<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Farmer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

class ExportToFly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export-to-fly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accounts = Account::all()->map(fn(Account $account) => [
            'id' => $account->user_id,
            'title' => $account->getFarmerTitle(),
            'session' => $account->session_id,
            'proxy' => $account->proxy,
            'user' => $account->data['user'] ?? null,
            'createdAt' => $account->created_at,
            'updatedAt' => $account->updated_at,
        ]);
        $payments = Payment::all()->map(fn(Payment $payment) => [
            'id' => $payment->id,
            'accountId' => $payment->user_id,
            'reference' => $payment->reference,
            'data' => $payment->data,
            'createdAt' => $payment->created_at,
            'updatedAt' => $payment->updated_at,
        ]);
        $subscriptions = Subscription::all()->map(fn(Subscription $subscription) => [
            'id' => $subscription->id,
            'accountId' => $subscription->user_id,
            'active' => $subscription->status === 'active',
            'startsAt' => $subscription->starts_at,
            'endsAt' => $subscription->ends_at,
            'createdAt' => $subscription->created_at,
            'updatedAt' => $subscription->updated_at,
        ]);
        $farmers = Farmer::all()->map(fn(Farmer $farmer) => [
            'id' => $farmer->id,
            'accountId' => $farmer->user_id,
            'active' => $farmer->is_connected,
            'farmer' => $farmer->farmer,
            'initData' => $farmer->getInitData(),
            'headers' => $farmer->headers,
            'createdAt' => $farmer->created_at,
            'updatedAt' => $farmer->updated_at,
        ]);

        $result = json_encode(compact('accounts', 'subscriptions', 'payments', 'farmers'), JSON_PRETTY_PRINT);
        file_put_contents(storage_path('app/fly-backup.json'), $result);
    }
}
