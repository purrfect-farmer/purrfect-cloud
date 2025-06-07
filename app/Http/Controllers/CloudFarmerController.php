<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\Account;
use App\Models\Farmer;
use App\Rules\ValidWebAppData;
use Illuminate\Http\Request;
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
        $account = Account::with('activeSubscription')
            ->where('user_id', $data['user']['id'])
            ->first();

        return [
            'account' => $account,
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
        $validated = $this->validateSyncRequest($request);

        try {
            /** Get Farmer */
            $farmer = Farmer::with('subscription')
                ->farmer($validated['farmer'])
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

                    return tap($farmer)->update(
                        $this->getFarmerData($validated)
                    );
                } else {
                    abort(400, 'Not allowed!');
                }
            } else {
                /** Get Account */
                $account = Account::subscribed()
                    ->where('user_id', $validated['user_id'])
                    ->first();

                if ($account) {
                    /** Update Account */
                    $this->updateAccount(
                        $account,
                        $validated['telegram_web_app']
                    );

                    /** Create Farmer */
                    return $account->farmers()->create(
                        $this->getFarmerData($validated)
                    );
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
                            'username' => strval($farmer->getUsername() ?? $farmer->user_id),
                            'photo_url' => $farmer->getPhotoUrl(),
                            'updated_at' => $farmer->updated_at
                        ],
                        $displayTitle ? [
                            'title' => $farmer->getFarmerTitle(),
                        ] : []
                    )
                )
                    ->sortBy($displayTitle ? 'title' : 'username')
                    ->values()
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

    /** Get Farmer Data */
    protected function getFarmerData($validated)
    {
        return [
            'farmer' => $validated['farmer'],
            'headers' => $validated['headers'],
            'telegram_web_app' => [
                'initData' => $validated['telegram_web_app']['initData']
            ],
            'is_connected' => true,
        ];
    }

    protected function validateSyncRequest(Request $request)
    {
        return $request->validate([
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
    }
}
