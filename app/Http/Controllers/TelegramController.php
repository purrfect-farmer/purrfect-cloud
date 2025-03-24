<?php

namespace App\Http\Controllers;

use App\Facades\Madeline;
use App\Helpers;
use App\Models\Account;
use App\Rules\ExistingMadelineSession;
use App\Rules\ValidWebAppData;
use Illuminate\Http\Request;
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
        $validated = $request->validate([
            'session' => ['required', 'string', 'alpha_num:ascii', 'size:16'],
        ]);

        if (Madeline::sessionExists($validated['session'])) {
            $api = Madeline::session(
                $validated['session']
            );

            $api->logout();

            /** Get Account */
            $account = Account::where('session_id', $validated['session'])->first();

            /** Set Session to Null */
            if ($account) {
                $account->forceFill(['session_id' => null])->save();
            }
        }

        return [
            'status' => true,
        ];
    }

    /** Check if session exists */
    public function check(Request $request)
    {
        $validated = $request->validate([
            'auth' => ['required', 'string', new ValidWebAppData],
        ]);

        $data = Helpers::getWebAppData($validated['auth']);
        $account = Account::subscribed()->where('user_id', $data['user']['id'])->first();

        return [
            'session' => $account->session_id ?? null,
        ];
    }

    /**
     * Save session
     * @param \danog\MadelineProto\API $api
     * @param string $session
     * @param array $result
     * @return array
     */
    protected function saveSession($api, $session, $result)
    {
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
            }
        }

        return [
            'status' => $result['_'],
            'user' => $result['user']
        ];
    }
}
