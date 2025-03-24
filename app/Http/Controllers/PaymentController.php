<?php

namespace App\Http\Controllers;

use App\Facades\Paystack;
use App\Models\Account;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        return Paystack::initialize([
            'amount' => config('farmer.subscription_amount'),
            'email' => $validated['email'],
            'metadata' => [
                'user_id' => $validated['user_id'],
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
                abort(403, 'Payment not found');
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

                return $payment;
            } else {
                /** Create Subscription */
                $account->subscriptions()->create([
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => now()->addMonth(),
                ]);

                /** Return Payment */
                return $payment;
            }
        }
    }
}
