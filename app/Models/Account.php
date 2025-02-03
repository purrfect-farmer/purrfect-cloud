<?php

namespace App\Models;

use App\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory;


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'farmer',
        'user_id',
        'telegram_web_app',
        'headers',
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telegram_web_app' => 'array',
            'headers' => 'array',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        /** Send Connected Message */
        static::created(function (Account $account) {
            static::sendStatusMessage($account, true);
        });

        /** Send Disconnected Message */
        static::deleted(function (Account $account) {
            static::sendStatusMessage($account, false);
        });
    }

    /**
     * Send Status Message
     * @param \App\Models\Account $account
     * @param bool $connected
     * @return void
     */
    protected static function sendStatusMessage(Account $account, $connected = true)
    {
        /** Message Key */
        $key =  implode(':', [
            $account->farmer,
            $account->user_id,
            'sync'
        ]);

        /** Title */
        $title = [
            'funatic' => '🤡 Funatic Farmer',
            'gold-eagle' => '🥇 Gold Eagle Farmer',
        ][$account->farmer];


        /** User ID */
        $id = $account->user_id;

        /** Username */
        $username =
            '@' . Str::of(
                $account->telegram_web_app['initDataUnsafe']['user']['username'] ?? '' ?: $id
            )
            ->limit(15);

        /** User Mention Link */
        $link = "<a href=\"tg://user?id=$id\">$username</a>";

        /** Date */
        $date = now();

        /** Status */
        $status = $connected ?
            '✅ Status: Connected' :
            '🟥 Status: Disconnected';

        /** Message */
        $message = $status ?
            'Automatic cloud farming has commenced.' :
            'Please kindly re-open the farmer in order to sync to cloud.';

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            $key,
            [
                "<b>$title</b>",
                "<b>👤 Account</b>: $link",
                "<b>🗓️ Date</b>: $date",
                "<i>$status</i>",
                "<i>$message</i>",
            ],
            [
                'chat_id' => $account->user_id,
                'disable_notification' => false,
                'message_thread_id' => ''
            ]
        );
    }
}
