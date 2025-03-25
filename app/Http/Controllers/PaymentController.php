<?php

namespace App\Http\Controllers;

use App\Facades\Paystack;
use App\Helpers;
use App\Models\Account;
use App\Models\Payment;
use App\Rules\ValidWebAppData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Initialize Payment
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'auth' => ['required', 'string', new ValidWebAppData],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $data = Helpers::getWebAppData($validated['auth']);

        return Paystack::initialize([
            'amount' => config('farmer.subscription_amount'),
            'callback_url' => route('payments.verify'),
            'email' => $validated['email'],
            'metadata' => [
                'user_id' => $data['user']['id'],
            ]
        ]);
    }

    /**
     * Verify Payment
     * @param \Illuminate\Http\Request $request
     * @return Payment|\Illuminate\Http\Response
     */
    public function verify(Request $request)
    {
        /** Fetch Payment */
        $payment = $this->getPayment($request);

        if ($payment) {
            return $payment;
        } else {
            /** Get Result */
            $result = Paystack::verify($request->input('reference'));

            /** Compare Result */
            if ($result['status'] !== 'success') {
                return response($result)
                    ->setStatusCode(403, 'Payment not found / unsuccessful!');
            }

            /** Save Payment */
            return $this->savePayment($result);
        }
    }

    /**
     * Verify Web
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    public function verifyWeb(Request $request)
    {
        /** Fetch Payment */
        $payment = $this->getPayment($request);

        if ($payment) {
            return view('payments.success', ['payment' => $payment]);
        } else {
            try {
                /** Get Result */
                $result = Paystack::verify($request->input('reference'));
            } catch (\Throwable $e) {
                return response(view('payments.error'))
                    ->setStatusCode(403, 'Payment not found!');
            }

            /** Compare Result */
            if ($result['status'] !== 'success') {
                return response(view('payments.error'))
                    ->setStatusCode(403, 'Payment unsuccessful!');
            }

            /** Save Payment */
            $payment = $this->savePayment($result);

            return view('payments.success', ['payment' => $payment]);
        }
    }

    /**
     * Get Payment
     * @param \Illuminate\Http\Request $request
     * @return Payment|null
     */
    protected function getPayment(Request $request)
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:32']
        ]);

        /** Fetch Payment */
        return Payment::where('reference', $validated['reference'])->first();
    }


    /**
     * Save Payment
     * @param mixed $result
     * @return Payment
     */
    protected function savePayment($result)
    {
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
