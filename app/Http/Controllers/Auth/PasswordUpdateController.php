<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class PasswordUpdateController extends Controller
{
    public function store(Request $request)
    {
        /** Input */
        $input = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'new_password' => ['required', 'string', Rules\Password::defaults()],
        ]);

        /** Update Password */
        $request->user()->forceFill([
            'password' => Hash::make($input['new_password']),
        ])->save();

        return response()->noContent();
    }
}
