<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CloudFarmerController extends Controller
{
    public function sync(Request $request)
    {
        $data = $request->validate([
            'farmer' => [
                'required',
                'string',
                Rule::in([
                    'funatic',
                    'gold-eagle'
                ])
            ],
            'user_id' => ['required', 'integer'],
            'telegram_web_app' => ['required', 'array'],
            'headers' => ['required', 'array']
        ]);


        try {
            return Account::updateOrCreate([
                'farmer' => $data['farmer'],
                'user_id' => $data['user_id'],
            ], [
                'telegram_web_app' => $data['telegram_web_app'],
                'headers' => $data['headers'],
            ]);
        } catch (\Throwable $e) {
            abort(400, $e->getMessage());
        }
    }

    public function stats()
    {
        return Account::select(
            'farmer',
            DB::raw('COUNT(*) as accounts')
        )->groupBy('farmer')
            ->get()
            ->mapWithKeys(
                fn($item) => [
                    $item['farmer'] => $item['accounts']
                ]
            );
    }
}