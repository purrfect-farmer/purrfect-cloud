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
            /** Get Account */
            $account = Account::farmer($data['farmer'])
                ->userId($data['user_id'])
                ->first();

            /** Update Account */
            if ($account) {
                return tap($account)->update([
                    'is_connected' => true,
                    'telegram_web_app' => $data['telegram_web_app'],
                    'headers' => $data['headers'],
                ]);
            } else {
                /** Allowed */
                $allowed = config('farmer.access_require_membership') === false ||
                    collect(['creator', 'administrator', 'member'])
                    ->contains(
                        Telegram::bot()
                            ->getChatMember([
                                'chat_id' => config('farmer.chat_id'),
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

    public function accounts()
    {
        $list = Account::all()->groupBy('farmer')->map(fn($list) => [
            'total' => $list->count(),
            'users' => $list->map(fn($account) => array_merge(
                [
                    'id' => $account->id,
                    'is_connected' => $account->is_connected,
                    'user_id' => $account->user_id,
                    'username' => $account->telegram_web_app['initDataUnsafe']['user']['username'],
                    'photo_url' => $account->telegram_web_app['initDataUnsafe']['user']['photo_url'],
                    'updated_at' => $account->updated_at
                ],
                config('farmer.display_farmer_title') ? [
                    'title' => $account->telegram_web_app['farmerTitle'] ?? 'TGUser',
                ] : []
            ))
        ]);

        /** Sort By Title */
        if (config('farmer.display_farmer_title')) {
            $list = $list->map(fn($group) => [
                ...$group,
                'users' => $group['users']->sortBy('title')->values()
            ]);
        }

        return $list;
    }

    public function disconnect(Account $account)
    {
        /** Delete the Account */
        $account->delete();

        return response()->noContent();
    }
}
