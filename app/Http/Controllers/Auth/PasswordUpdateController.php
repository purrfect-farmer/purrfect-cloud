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
            'currentPassword' => ['required', 'string', 'currentPassword'],
            'newPassword' => ['required', 'string', Rules\Password::defaults()],
        ]);

        /** Update Password */
        $request->user()->forceFill([
            'password' => Hash::make($input['newPassword']),
        ])->save();

        return response()->noContent();
    }
}
