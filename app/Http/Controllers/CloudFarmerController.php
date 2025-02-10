<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Telegram\Bot\Laravel\Facades\Telegram;

class CloudFarmerController extends Controller
{
    public function sync(Request $request)
    {
        $data = $request->validate([
            'farmer' => [
                'required',
                'string',
                Rule::in([
                    'funatic',
                    'gold-eagle',
                ])
            ],
            'user_id' => ['required', 'integer'],
            'telegram_web_app' => ['required', 'array'],
            'headers' => ['required', 'array']
        ]);


        try {
            /** Get Account */
            $account = Account::where([
                'farmer' => $data['farmer'],
                'user_id' => $data['user_id'],
            ])->first();

            /** Update Account */
            if ($account) {
                return tap($account)->update([
                    'telegram_web_app' => $data['telegram_web_app'],
                    'headers' => $data['headers'],
                ]);
            } else {
                /** Allowed */
                $allowed = env('ACCESS_REQUIRE_MEMBERSHIP') === false ||
                    collect(['creator', 'administrator', 'member'])
                    ->contains(
                        Telegram::bot()
                            ->getChatMember([
                                'chat_id' => env('TELEGRAM_CHAT_ID'),
                                'user_id' =>  $data['user_id']
                            ])->status
                    );


                /** Ensure user is allowed */
                if ($allowed) {
                    return Account::create([
                        'farmer' => $data['farmer'],
                        'user_id' => $data['user_id'],
                        'telegram_web_app' => $data['telegram_web_app'],
                        'headers' => $data['headers'],
                    ]);
                } else {
                    abort(400, 'Not allowed!');
                }
            }
        } catch (\Throwable $e) {
            abort(400, $e->getMessage());
        }
    }

    public function accounts()
    {
        return Account::all()->groupBy('farmer')->map(fn($list) => [
            'total' => $list->count(),
            'users' => $list->map(fn($account) => [
                'id' => $account->id,
                'user_id' => $account->user_id,
                'username' => $account->telegram_web_app['initDataUnsafe']['user']['username'],
                'photo_url' => $account->telegram_web_app['initDataUnsafe']['user']['photo_url'],
                'updated_at' => $account->updated_at
            ])
        ]);
    }

    public function disconnect(Account $account)
    {
        Account::withoutEvents(function () use ($account) {
            $account->delete();
        });

        return response()->noContent();
    }
}
