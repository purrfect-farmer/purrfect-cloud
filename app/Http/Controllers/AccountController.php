<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Libraries\TelegramClient;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    public function index()
    {
        $displayTitle = config('farmer.display_farmer_title');
        $list = Account::all()
            ->map(
                fn($account) => array_merge(
                    [
                        'id' => $account->user_id,
                        'user' => $account->data['user'] ?? null,
                        'session' => $account->session_id,
                        'proxy' => $account->proxy,
                        'subscriptions' => $account->activeSubscription ? [
                            [
                                'startsAt' => $account->activeSubscription->starts_at,
                                'endsAt' => $account->activeSubscription->ends_at,
                            ]
                        ] : []
                    ],
                    $displayTitle ? [
                        'title' => $account->getFarmerTitle(),
                    ] : []
                )
            )->values();

        return $list;
    }

    /** Kick Member */
    public function kick(Request $request)
    {
        /** Get Account */
        $account = Account::where('user_id', $request->id)->firstOrFail();

        /** Remove Session */
        if ($account->session_id) {
            try {
                TelegramClient::session($account->session_id)->logout();
            } catch (\Throwable $e) {
            }
        }

        /** Delete the Farmers */
        try {
            $account->farmers->each->delete();
        } catch (\Throwable $e) {
            /** Log Error */
            Log::error('Failed to delete farmers: ' . $e->getMessage());
        }

        /** Delete the Account */
        $account->delete();


        /** Remove User */
        try {
            Helpers::removeUserFromGroup(
                $account->user_id
            );
        } catch (\Throwable $e) {
            /** Log Error */
            Log::error('Kick Member', [
                'account' => $account,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->noContent();
    }


    /** Add subscription  */
    public function subscription(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'integer'],
            'date' => ['required', 'string', 'date']
        ]);

        /** Find or Create Account */
        $account = Account::with('activeSubscription')
            ->firstOrCreate(['user_id' => $validated['id']]);

        /** Update Subscription */
        $account->updateSubscription($validated['date']);

        return $account->fresh(['activeSubscription']);
    }
}
