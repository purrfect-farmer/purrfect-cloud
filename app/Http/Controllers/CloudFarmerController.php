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
            'account' => $account ? [
                'id' => $account->user_id,
                'title' => $account->getFarmerTitle(),
                'session' => $account->session_id,
                'proxy' => $account->proxy,
                'user' => $account->data['user'] ?? null,
            ] : null,
            'subscription' => $account->activeSubscription ? [
                'startsAt' => $account->activeSubscription->starts_at,
                'endsAt' => $account->activeSubscription->ends_at,
            ] : null
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
                ->userId($validated['userId'])
                ->first();

            /** Update Farmer */
            if ($farmer) {
                if ($farmer->subscription) {
                    /** Update Account */
                    $this->updateAccount(
                        $farmer->account,
                        $validated
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
                    ->where('user_id', $validated['userId'])
                    ->first();

                if ($account) {
                    /** Update Account */
                    $this->updateAccount(
                        $account,
                        $validated
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
        $list = Account::with('farmers')
            ->subscribed()
            ->get()
            ->map(fn($account) => array_merge(
                [
                    'id' => $account->user_id,
                    'user' => $account->data['user'] ?? null,
                    'session' => $account->session_id,
                    'proxy' => $account->proxy,
                    'farmers' => $account->farmers->map(fn($farmer) => [
                        'id' => $farmer->id,
                        'farmer' => $farmer->farmer,
                        'active' => $farmer->is_connected,
                    ])
                ],
                $displayTitle ? ['title' => $account->getFarmerTitle()] : []
            ));

        return $list;
    }

    /**
     *  Disconnect Farmer
     * @return mixed|\Illuminate\Http\Response
     */
    public function disconnect(Request $request)
    {
        $farmer = Farmer::findOrFail($request->id);
        /** Delete the Farmer */
        $farmer->delete();

        return response()->noContent();
    }

    /**
     * Update Account
     * @param \App\Models\Account $account
     * @param string $data
     * @return void
     */
    protected function updateAccount(Account $account, $data)
    {
        $account->update([
            'data' => array_merge($account->data ?? [], [
                'farmerTitle' => $data['title'] ?? 'TGUser',
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
                'initData' => $validated['initData']
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
            'userId' => ['required', 'integer'],
            'initData' => ['required', 'string'],
            'title' => ['required', 'string'],
            'headers' => ['required', 'array']
        ]);
    }
}
