<?php

namespace App\Http\Controllers;

use App\Facades\Madeline;
use App\Models\Account;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Telegram\Bot\Laravel\Facades\Telegram;

class CloudFarmerController extends Controller
{
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
                return tap($farmer)->update([
                    'is_connected' => true,
                    'telegram_web_app' => $validated['telegram_web_app'],
                    'headers' => $validated['headers'],
                ]);
            } else {
                /** Allowed */
                $allowed = config('farmer.access_require_membership') === false ||
                    collect(['creator', 'administrator', 'member'])
                    ->contains(
                        Telegram::bot()
                            ->getChatMember([
                                'chat_id' => config('farmer.chat_id'),
                                'user_id' =>  $validated['user_id']
                            ])->status
                    );


                /** Ensure user is allowed */
                if ($allowed) {
                    /** Get or Create Account */
                    $account = Account::firstOrCreate([
                        'user_id' => $validated['user_id'],
                    ]);

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
        $list = Farmer::all()->groupBy('farmer')->map(fn($list) => [
            'total' => $list->count(),
            'users' => $list->map(
                fn($farmer) => array_merge(
                    [
                        'id' => $farmer->id,
                        'is_connected' => $farmer->is_connected,
                        'user_id' => $farmer->user_id,
                        'username' => $farmer->telegram_web_app['initDataUnsafe']['user']['username'],
                        'photo_url' => $farmer->telegram_web_app['initDataUnsafe']['user']['photo_url'],
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
