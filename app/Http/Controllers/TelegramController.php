<?php

namespace App\Http\Controllers;

use App\Facades\Madeline;
use App\Helpers;
use App\Models\Account;
use App\Rules\ExistingMadelineSession;
use App\Rules\ValidWebAppData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelPhone\Rules\Phone;

class TelegramController extends Controller
{
    /** Get Login */
    public function login(Request $request)
    {
        $validated = $request->validate(
            [
                'session' => ['nullable', 'string', 'alpha_num:ascii', 'size:16', new ExistingMadelineSession],
                'phone' => ['required', 'string', new Phone()],
            ],
            ['phone' => 'Phone is Invalid']
        );

        $session = $validated['session'] ?? Madeline::generateSession();
        $api = Madeline::session(
            $session
        );

        $result = $api->phoneLogin($validated['phone']);

        return [
            'session' => $session,
            'status' => $result['_']
        ];
    }

    /** Get Code */
    public function code(Request $request)
    {
        $validated = $request->validate([
            'session' => ['required', 'string', 'alpha_num:ascii', 'size:16', new ExistingMadelineSession],
            'code' => ['required', 'string'],
        ]);

        $api = Madeline::session(
            $validated['session']
        );

        $result = $api->completePhoneLogin(
            $validated['code']
        );

        if ($result['_'] === 'account.password') {
            return [
                'status' => $result['_'],
                'hint' => $result['hint'],
            ];
        } else {
            return $this->saveSession(
                $api,
                $validated['session'],
                $result
            );
        }
    }

    /** Password */
    public function password(Request $request)
    {
        $validated = $request->validate([
            'session' => ['required', 'string', 'alpha_num:ascii', 'size:16', new ExistingMadelineSession],
            'password' => ['required', 'string'],
        ]);

        $api = Madeline::session(
            $validated['session']
        );
        $result = $api->complete2faLogin(
            $validated['password']
        );

        return $this->saveSession(
            $api,
            $validated['session'],
            $result
        );
    }

    /** Logout */
    public function logout(Request $request)
    {
        $account = $this->getAccount($request);

        if ($account) {
            try {
                Madeline::session($account->session_id)->logout();
            } catch (\Throwable $e) {
                Log::error(
                    'TELEGRAM SESSION LOGOUT: ' . $e->getMessage(),
                    ['user_id' => $account->user_id ?? null]
                );
            }

            /** Update Session */
            $account->update(['session_id' => null]);
        }

        return [
            'status' => true,
        ];
    }

    /** Get Session */
    public function session(Request $request)
    {
        $account = $this->getAccount($request);

        return [
            'session' => $account->session_id ?? null,
        ];
    }

    /**
     * Get Account
     * @param \Illuminate\Http\Request $request
     * @return Account|null
     */
    protected function getAccount(Request $request)
    {
        $validated = $request->validate([
            'auth' => ['required', 'string', new ValidWebAppData],
        ]);

        $data = Helpers::getWebAppData($validated['auth']);
        $account = Account::where(
            'user_id',
            $data['user']['id']
        )->first();

        return $account;
    }

    /**
     * Save session
     * @param \danog\MadelineProto\API $api
     * @param string $session
     * @param array $result
     * @return array
     */
    protected function saveSession(
        $api,
        $session,
        $result
    ) {
        /** Get Account */
        $account = Account::subscribed()->where(
            'user_id',
            $result['user']['id']
        )->first();


        /** Ensure Account Exists */
        if (!$account) {
            $api->logout();
            abort(400, 'Not allowed!');
        }

        /** Save Previous Session Id */
        $previousSessionId = $account->session_id;

        /** Update Session Id */
        $account->forceFill(['session_id' => $session])->save();

        /** Logout of Previous Session */
        if ($previousSessionId) {
            try {
                Madeline::session($previousSessionId)->logout();
            } catch (\Throwable $e) {
                Log::error(
                    'TELEGRAM SESSION LOGOUT: ' . $e->getMessage(),
                    ['user_id' => $account->user_id ?? null]
                );
            }
        }

        return [
            'status' => $result['_'],
            'user' => $result['user']
        ];
    }
}
