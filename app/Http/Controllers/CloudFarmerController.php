<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    'zoo',
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
                /** Get Member */
                $member = Telegram::bot()->getChatMember([
                    'chat_id' => env('TELEGRAM_CHAT_ID'),
                    'user_id' =>  $data['user_id']
                ]);

                /** Ensure user is in chat */
                if (
                    collect(['creator', 'administrator', 'member'])->contains($member->status)
                ) {
                    return Account::create([
                        'farmer' => $data['farmer'],
                        'user_id' => $data['user_id'],
                        'telegram_web_app' => $data['telegram_web_app'],
                        'headers' => $data['headers'],
                    ]);
                } else {
                    abort(400, 'Not a Member of Chat!');
                }
            }
        } catch (\Throwable $e) {
            abort(400, $e->getMessage());
        }
    }

    public function stats()
    {
        return Account::all()->groupBy('farmer')->map(fn($list) => [
            'total' => $list->count(),
            'users' => $list->mapWithKeys(fn($account) => [
                $account->telegram_web_app['initDataUnsafe']['user']['id'] =>
                $account->telegram_web_app['initDataUnsafe']['user']['username'],
            ])
        ]);
    }
}
