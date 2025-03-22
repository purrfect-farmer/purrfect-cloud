<?php

namespace App\Models;

use App\Helpers;
use Illuminate\Database\Eloquent\Model;

class MadelineSession extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
    ];


    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        /** Created */
        static::created(function (MadelineSession $session) {
            $session->sendStatusMessage(true);
        });

        /** Updated */
        static::updated(function (MadelineSession $session) {
            if ($session->wasChanged('session_id')) {
                $session->sendStatusMessage(
                    true
                );
            }
        });

        /** Deleted */
        static::deleted(function (MadelineSession $session) {
            $session->sendStatusMessage(false);
        });
    }

    /**
     * Send Status Message
     * @param bool $status
     * @return void
     */
    public function sendStatusMessage($status = true)
    {
        /** Message Key */
        $key =  implode(':', [
            'cloud-telegram-session',
            $this->user_id
        ]);

        /** Status */
        $status = $status ?
            '<b>✅ Status:</b> Logged In' :
            '<b>❌ Status:</b> Logged Out';

        /** Message */
        $message = $status ?
            'Telegram account has been logged in on Cloud.' :
            'Telegram account has been logged out of Cloud.';

        /** Date */
        $date = now();

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            $key,
            [
                "<b>Cloud Telegram Session</b>",
                "$status",
                "<b>🗓️ Date</b>: $date",
                "<blockquote><i>$message</i></blockquote>",
            ],
            [
                'chat_id' => $this->user_id,
                'disable_notification' => false,
            ]
        );
    }
}
