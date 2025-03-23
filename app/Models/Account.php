<?php

namespace App\Models;

use App\Facades\Proxy;
use App\Helpers;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'proxy'
    ];


    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        /** Creating */
        static::creating(function (Account $account) {
            $account->proxy = Proxy::getRandomUnused();
        });

        /** Updated */
        static::updated(function (Account $account) {
            if ($account->wasChanged('session_id')) {
                $account->sendSessionStatusMessage(
                    $account->session_id !== null
                );
            }
        });

        /** Deleted */
        static::deleted(function (Account $account) {
            if ($account->session_id) {
                $account->sendSessionStatusMessage(false);
            }
        });
    }

    /**
     * Send Status Message
     * @param bool $loggedIn
     * @return void
     */
    public function sendSessionStatusMessage($loggedIn = true)
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
            'Your Telegram account was successfully logged in on Cloud. Automatic refetch has been enabled.' :
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


    /** Farmers */
    public function farmers()
    {
        return $this->hasMany(
            Farmer::class,
            'user_id',
            'user_id'
        );
    }
}
