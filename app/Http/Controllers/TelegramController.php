<?php

namespace App\Http\Controllers;

use App\Facades\Madeline;
use App\Models\MadelineSession;
use App\Rules\ExistingMadelineSession;
use Illuminate\Http\Request;
use Propaganistas\LaravelPhone\Rules\Phone;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller
{
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
        } else if (
            !$this->userIsAllowed(
                $result['user']['id']
            )
        ) {
            $api->logout();
            abort(400, 'Not allowed!');
        } else {
            /** Save Session */
            $this->saveSession(
                $result['user']['id'],
                $validated['session']
            );

            return [
                'status' => $result['_'],
                'user' => $result['user']
            ];
        }
    }

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

        if (
            !$this->userIsAllowed(
                $result['user']['id']
            )
        ) {
            $api->logout();
            abort(400, 'Not allowed!');
        } else {
            /** Save Session */
            $this->saveSession(
                $result['user']['id'],
                $validated['session']
            );

            return [
                'status' => $result['_'],
                'user' => $result['user']
            ];
        }
    }



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

            /** Delete Session */
            MadelineSession::where('session_id', $validated['session'])->delete();
        }

        return [
            'status' => true,
        ];
    }

    public function check(Request $request)
    {
        $validated = $request->validate([
            'session' => ['required', 'string', 'alpha_num:ascii', 'size:16'],
        ]);

        return [
            'status' => Madeline::sessionExists($validated['session']),
        ];
    }

    protected function userIsAllowed($id)
    {
        return (
            config('farmer.access_require_membership') === false ||
            collect(['creator', 'administrator', 'member'])
            ->contains(
                Telegram::bot()
                    ->getChatMember([
                        'chat_id' => config('farmer.chat_id'),
                        'user_id' =>  $id
                    ])->status
            )
        );
    }

    /**
     * Save session
     * @param string $userId
     * @param string $sessionId
     * @return void
     */
    protected function saveSession(
        $userId,
        $sessionId
    ) {
        /** Get Existing Session */
        $existing = MadelineSession::where('user_id', $userId)->first();

        if ($existing) {
            /** Save Previous Session Id */
            $previousSessionId = $existing->session_id;

            /** Update Session Id */
            $existing->forceFill(['session_id' => $sessionId])->save();

            /** Logout of Previous Session */
            try {
                Madeline::session($previousSessionId)->logout();
            } catch (\Throwable $e) {
            }
        } else {
            /** Create a new Session */
            MadelineSession::create(
                [
                    'user_id' => $userId,
                    'session_id' => $sessionId
                ]
            );
        }
    }
}
