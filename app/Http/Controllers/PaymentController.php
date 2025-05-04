<?php

namespace App\Http\Controllers;

use App\Facades\Flutterwave;
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

        $payment = Flutterwave::initialize([
            'amount' => config('farmer.subscription_amount'),
            'redirect_url' => route('payments.verify'),
            'currency' => 'NGN',
            'customer' => [
                'email' => $validated['email'],
            ],
            'customizations' => [
                'title' => config('app.name') . ' Payment',
                'logo' => url('icon.png')
            ],
            'meta' => [
                'user_id' => $data['user']['id'],
            ]
        ]);

        return [
            'authorization_url' => $payment['link']
        ];
    }

    /**
     * Verify Payment
     * @param \Illuminate\Http\Request $request
     * @return Payment|\Illuminate\Http\Response
     */
    public function verify(Request $request)
    {
        /** Reference */
        $reference = $this->validateRequest($request);

        /** Fetch Payment */
        $payment = $this->getPayment($reference);

        if ($payment) {
            return $payment;
        } else {
            /** Get Result */
            $result = Flutterwave::verify($reference);

            /** Compare Result */
            if (!$this->isSuccessful($result)) {
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
        /** Reference */
        $reference = $this->validateRequest($request);

        /** Fetch Payment */
        $payment = $this->getPayment($reference);

        if ($payment) {
            return view('payments.success', ['payment' => $payment]);
        } else {
            try {
                /** Get Result */
                $result = Flutterwave::verify($reference);
            } catch (\Throwable $e) {
                return response(view('payments.error'))
                    ->setStatusCode(403, 'Payment not found!');
            }

            /** Compare Result */
            if (!$this->isSuccessful($result)) {
                return response(view('payments.error'))
                    ->setStatusCode(403, 'Payment unsuccessful!');
            }

            /** Save Payment */
            $payment = $this->savePayment($result);

            return view('payments.success', ['payment' => $payment]);
        }
    }

    /**
     * Validate and Get Reference
     * @param \Illuminate\Http\Request $request
     */
    protected function validateRequest(Request $request)
    {
        $validated = $request->validate([
            'tx_ref' => ['required', 'string', 'max:32']
        ]);

        return $validated['tx_ref'];
    }

    /**
     * Check if result is successful
     * @param array $result
     * @return bool
     */
    protected function isSuccessful($result)
    {
        return $result['status'] === 'successful';
    }

    /**
     * Get User ID
     * @param array $result
     * @return int
     */
    protected function getUserId($result)
    {
        return $result['meta']['user_id'];
    }

    /**
     * Get Reference
     * @param array $result
     * @return string
     */
    protected function getReference($result)
    {
        return $result['tx_ref'];
    }

    /**
     * Get Payment
     * @param string $reference
     * @return Payment|null
     */
    protected function getPayment($reference)
    {
        return Payment::where('reference', $reference)->first();
    }


    /**
     * Save Payment
     * @param array $result
     * @return Payment
     */
    protected function savePayment($result)
    {
        /** Find or Create Account */
        $account = Account::firstOrCreate(['user_id' => $this->getUserId($result)]);

        /** Create Payment */
        $payment = $account->payments()->create([
            'reference' => $this->getReference($result),
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
