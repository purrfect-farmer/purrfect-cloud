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
        $request->validate(
            [
                'session' => ['nullable', 'string', 'alpha_num:ascii', new ExistingMadelineSession],
                'phone' => ['required', 'string', new Phone()],
            ],
            ['phone' => 'Phone is Invalid']
        );

        $session =  \Illuminate\Support\Str::random(32);
        $api = Madeline::session(
            $session
        );

        $result = $api->phoneLogin($request->phone);

        return [
            'session' => $session,
            'status' => $result['_']
        ];
    }

    public function code(Request $request)
    {
        $request->validate([
            'session' => ['required', 'string', 'alpha_num:ascii', new ExistingMadelineSession],
            'code' => ['required', 'string'],
        ]);

        $api = Madeline::session(
            $request->session
        );

        $result = $api->completePhoneLogin(
            $request->code
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
            /** Create or Update Session */
            MadelineSession::updateOrCreate(
                ['user_id' => $result['user']['id']],
                ['session_id' => $request->session]
            );

            return [
                'status' => $result['_'],
                'user' => $result['user']
            ];
        }
    }

    public function password(Request $request)
    {
        $request->validate([
            'session' => ['required', 'string', 'alpha_num:ascii', new ExistingMadelineSession],
            'password' => ['required', 'string'],
        ]);

        $api = Madeline::session(
            $request->session
        );
        $result = $api->complete2faLogin(
            $request->password
        );

        if (
            !$this->userIsAllowed(
                $result['user']['id']
            )
        ) {
            $api->logout();
            abort(400, 'Not allowed!');
        } else {

            /** Create or Update Session */
            MadelineSession::updateOrCreate(
                ['user_id' => $result['user']['id']],
                ['session_id' => $request->session]
            );

            return [
                'status' => $result['_'],
                'user' => $result['user']
            ];
        }
    }



    public function logout(Request $request)
    {
        $request->validate([
            'session' => ['required', 'string', 'alpha_num:ascii', new ExistingMadelineSession],
        ]);

        $api = Madeline::session(
            $request->session
        );
        $api->logout();

        /** Delete Session */
        MadelineSession::where('session_id', $request->session)->delete();

        return [
            'status' => true,
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
}
