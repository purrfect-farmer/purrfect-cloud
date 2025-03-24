<?php

namespace App\Http\Controllers;

use App\Facades\Madeline;
use App\Helpers;
use App\Models\Account;
use App\Models\Farmer;
use App\Rules\ValidWebAppData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Telegram\Bot\Laravel\Facades\Telegram;

class CloudFarmerController extends Controller
{
    /**
     * Get Subscription
     * @param \Illuminate\Http\Request $request
     */
    protected function subscription(Request $request)
    {
        $validated = $request->validate([
            'auth' => ['required', 'string', new ValidWebAppData],
        ]);

        $data = Helpers::getWebAppData($validated['auth']);
        $account = Account::where(
            'user_id',
            $data['user']['id']
        )->first();

        return [
            'subscription' => $account->activeSubscription ?? null
        ];
    }

    public function sync(Request $request)
    {
        $validated = $request->validate([
            'farmer' => [
                'required',
                'string',
                Rule::in(
                    collect(config('farmer.drops'))
                        ->filter(fn($drop) => $drop['enabled'])
                        ->keys()
                )
            ],
            'user_id' => ['required', 'integer'],
            'telegram_web_app' => ['required', 'array'],
            'headers' => ['required', 'array']
        ]);


        try {
            /** Get Farmer */
            $farmer = Farmer::farmer($validated['farmer'])
                ->userId($validated['user_id'])
                ->first();

            /** Update Farmer */
            if ($farmer) {
                if ($farmer->subscription) {
                    return tap($farmer)->update([
                        'is_connected' => true,
                        'telegram_web_app' => $validated['telegram_web_app'],
                        'headers' => $validated['headers'],
                    ]);
                } else {
                    abort(400, 'Not allowed!');
                }
            } else {
                /** Get Account */
                $account = Account::subscribed()->where('user_id', $validated['user_id'])->first();

                if ($account) {
                    /** Create Farmer */
                    return $account->farmers()->create([
                        'farmer' => $validated['farmer'],
                        'telegram_web_app' => $validated['telegram_web_app'],
                        'headers' => $validated['headers'],
                        'is_connected' => true,
                    ]);
                } else {
                    abort(400, 'Not allowed!');
                }
            }
        } catch (\Throwable $e) {
            abort(400, $e->getMessage());
        }
    }

    public function farmers()
    {
        $list = Farmer::subscribed()->get()->groupBy('farmer')->map(fn($list) => [
            'total' => $list->count(),
            'users' => $list->map(
                fn($farmer) => array_merge(
                    [
                        'id' => $farmer->id,
                        'is_connected' => $farmer->is_connected,
                        'user_id' => $farmer->user_id,
                        'username' => $farmer->telegram_web_app['initDataUnsafe']['user']['username'] ?? $farmer->user_id,
                        'photo_url' => $farmer->telegram_web_app['initDataUnsafe']['user']['photo_url'] ?? null,
                        'updated_at' => $farmer->updated_at
                    ],
                    config('farmer.display_farmer_title') ? [
                        'title' => $farmer->telegram_web_app['farmerTitle'] ?? 'TGUser',
                    ] : []
                )
            )->sortBy(
                config('farmer.display_farmer_title') ? 'title' : 'id'
            )->values()
        ]);

        return $list;
    }

    public function disconnect(Farmer $farmer)
    {
        /** Delete the Farmer */
        $farmer->delete();

        return response()->noContent();
    }

    /** Kick Member */
    public function kick(int $id)
    {
        /** Get Account */
        $account = Account::where('user_id', $id)->firstOrFail();

        /** Remove Session */
        if ($account->session_id) {
            try {
                Madeline::session($account->session_id)->logout();
            } catch (\Throwable $e) {
            }
        }

        try {
            /** Remove User */
            Telegram::bot()->banChatMember([
                'chat_id' => config('farmer.chat_id'),
                'user_id' => $account->user_id
            ]);

            /** Unban User */
            Telegram::bot()->unbanChatMember([
                'chat_id' => config('farmer.chat_id'),
                'user_id' => $account->user_id,
                'only_if_banned' => true,
            ]);
        } catch (\Throwable $e) {
            /** Log Error */
            Log::error('Kick Member', [
                'account' => $account,
                'error' => $e->getMessage(),
            ]);
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
    }
}
