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
                        'id' => $account->id,
                        'session_id' => $account->session_id,
                        'subscription' => $account->activeSubscription,
                        'user_id' => $account->user_id,
                        'username' => strval($account->getUsername() ?? $account->user_id),
                        'photo_url' => $account->getPhotoUrl(),
                    ],
                    $displayTitle ? [
                        'title' => $account->getFarmerTitle(),
                    ] : []
                )
            )->sortBy(
                $displayTitle ? 'title' : 'username'
            )->values();

        return $list;
    }

    /** Kick Member */
    public function kick(int $id)
    {
        /** Get Account */
        $account = Account::where('user_id', $id)->firstOrFail();

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
            'user_id' => ['required', 'string', 'integer'],
            'date' => ['required', 'string', 'date']
        ]);

        /** Find or Create Account */
        $account = Account::with('activeSubscription')
            ->firstOrCreate(['user_id' => $validated['user_id']]);

        /** Update Subscription */
        $account->updateSubscription($validated['date']);

        return $account->fresh(['activeSubscription']);
    }
}
