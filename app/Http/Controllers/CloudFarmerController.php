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

class CloudFarmerController extends Controller
{
    /**
     * Get Subscription
     * @param \Illuminate\Http\Request $request
     */
    public function subscription(Request $request)
    {
        $validated = $request->validate([
            'auth' => ['required', 'string', new ValidWebAppData],
        ]);

        $data = Helpers::getWebAppData($validated['auth']);
        $account = Account::with('activeSubscription')->where(
            'user_id',
            $data['user']['id']
        )->first();

        return [
            'subscription' => $account->activeSubscription ?? null
        ];
    }

    /**
     *  Sync to Cloud
     * @param \Illuminate\Http\Request $request
     * @return bool|Farmer|mixed
     */
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
            $farmer = Farmer::with('subscription')->farmer($validated['farmer'])
                ->userId($validated['user_id'])
                ->first();

            /** Update Farmer */
            if ($farmer) {
                if ($farmer->subscription) {
                    /** Update Account */
                    $this->updateAccount(
                        $farmer->account,
                        $validated['telegram_web_app']
                    );

                    return tap($farmer)->update([
                        'is_connected' => true,
                        'headers' => $validated['headers'],
                        'telegram_web_app' => [
                            'initData' => $validated['telegram_web_app']['initData']
                        ],
                    ]);
                } else {
                    abort(400, 'Not allowed!');
                }
            } else {
                /** Get Account */
                $account = Account::subscribed()->where('user_id', $validated['user_id'])->first();

                if ($account) {
                    /** Update Account */
                    $this->updateAccount(
                        $account,
                        $validated['telegram_web_app']
                    );

                    /** Create Farmer */
                    return $account->farmers()->create([
                        'farmer' => $validated['farmer'],
                        'headers' => $validated['headers'],
                        'telegram_web_app' => [
                            'initData' => $validated['telegram_web_app']['initData']
                        ],
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

    /**
     * Get Farmers
     */
    public function farmers()
    {
        $displayTitle = config('farmer.display_farmer_title');
        $list = Farmer::with('account')
            ->subscribed()
            ->get()
            ->groupBy('farmer')
            ->map(fn($list) => [
                'total' => $list->count(),
                'users' => $list->map(
                    fn($farmer) => array_merge(
                        [
                            'id' => $farmer->id,
                            'is_connected' => $farmer->is_connected,
                            'session_id' => $farmer->account->session_id,
                            'user_id' => $farmer->user_id,
                            'username' => strval($farmer->getInitDataUnsafe()['user']['username'] ?? $farmer->user_id),
                            'photo_url' => $farmer->getInitDataUnsafe()['user']['photo_url'] ?? null,
                            'updated_at' => $farmer->updated_at
                        ],
                        $displayTitle ? [
                            'title' => $farmer->getFarmerTitle(),
                        ] : []
                    )
                )->sortBy(
                        $displayTitle ? 'title' : 'username'
                    )->values()
            ]);

        return $list;
    }

    /**
     *  Disconnect Farmer
     * @param \App\Models\Farmer $farmer
     * @return mixed|\Illuminate\Http\Response
     */
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

    /**
     * Update Account
     * @param \App\Models\Account $account
     * @param array $data
     * @return void
     */
    protected function updateAccount(Account $account, $data)
    {
        $account->update([
            'data' => array_merge($account->data ?? [], [
                'farmerTitle' => $data['farmerTitle'] ?? 'TGUser',
                'user' => Helpers::getWebAppData($data['initData'])['user']
            ])
        ]);
    }
}
