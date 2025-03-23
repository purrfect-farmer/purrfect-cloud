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
     * @param bool $loggedIn
     * @return void
     */
    public function sendStatusMessage($loggedIn = true)
    {
        /** Message Key */
        $key =  implode(':', [
            'cloud-telegram-session',
            $this->user_id
        ]);

        /** Status */
        $status = $loggedIn ?
            '<b>✅ Status:</b> Logged In' :
            '<b>❌ Status:</b> Logged Out';

        /** Message */
        $message = $loggedIn ?
            'Your Telegram account has been logged in on Cloud. Automatic refetch has been enabled.' :
            'Your Telegram account was logged out of Cloud. Automatic refetch has been disabled.';

        /** Date */
        $date = now();

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            $key,
            [
                "<b>⚡ Cloud Telegram Session</b>",
                "$status",
                "<b>🗓️ Date</b>: $date",
                "<blockquote><b>$message</b></blockquote>",
            ],
            [
                'chat_id' => $this->user_id,
                'disable_notification' => false,
            ]
        );
    }
}
