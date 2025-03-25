<?php

namespace App\Http\Controllers;

use App\Facades\Paystack;
use App\Helpers;
use App\Models\Account;
use App\Models\Payment;
use App\Rules\ValidWebAppData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class PaymentController extends Controller
{
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'auth' => ['required', 'string', new ValidWebAppData],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $data = Helpers::getWebAppData($validated['auth']);

        return Paystack::initialize([
            'amount' => config('farmer.subscription_amount'),
            'email' => $validated['email'],
            'metadata' => [
                'user_id' => $data['user']['id'],
            ]
        ]);
    }


    public function verify(Request $request)
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:32']
        ]);

        /** Fetch Payment */
        $payment = Payment::where('reference', $validated['reference'])->first();

        if ($payment) {
            return $payment;
        } else {
            /** Get Result */
            $result = Paystack::verify($validated['reference']);

            /** Compare Result */
            if ($result['status'] !== 'success') {
                return response($result)->setStatusCode(403, 'Payment not found / successful');
            }

            /** Find or Create Account */
            $account = Account::firstOrCreate(['user_id' => $result['metadata']['user_id']]);

            /** Create Payment */
            $payment = $account->payments()->create([
                'reference' => $result['reference'],
                'data' => $result
            ]);

            /** Get Active Subscription */
            $subscription = $account->activeSubscription;

            if ($subscription) {
                $subscription->forceFill(
                    [
                        'status' => 'active',
                        'ends_at' => $subscription->ends_at->addMonth()
                    ]
                )->save();
            } else {
                /** Create Subscription */
                $account->subscriptions()->create([
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => now()->addMonth(),
                ]);
            }

            /** Add Member to Group */
            try {
                if (!Helpers::isGroupMember($account->user_id)) {
                    Helpers::sendInviteLink($account->user_id);
                }
            } catch (\Throwable $e) {
                /** Log Error */
                Log::error('Add Member to Group', [
                    'account' => $account,
                    'error' => $e->getMessage(),
                ]);
            }

            /** Return Payment */
            return $payment;
        }
    }
}
