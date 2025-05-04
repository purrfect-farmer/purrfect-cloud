<?php

namespace App\Models;

use App\Facades\Proxy;
use App\Helpers;
use Illuminate\Database\Eloquent\Builder;
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
        'proxy',
        'data'
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }


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
        $key = implode(':', [
            'cloud-telegram-session',
            $this->user_id
        ]);

        /** Status */
        $status = $loggedIn ?
            '<b>✅ Status:</b> Logged In' :
            '<b>❌ Status:</b> Logged Out';

        /** Message */
        $message = $loggedIn ?
            'Telegram account was successfully logged in on Cloud. Automatic refetch of farmers has been enabled.' :
            'Telegram account was logged out of Cloud. Automatic refetch of farmers has been disabled.';

        /** Date */
        $date = now();

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            $key,
            [
                "<b>⚡ Cloud Telegram Session</b>",
                "$status",
                "<b>🗓️ Date</b>: $date",
                "<blockquote><b><i>$message</i></b></blockquote>",
            ],
            [
                'chat_id' => $this->user_id,
                'disable_notification' => false,
            ]
        );
    }

    /** Scope Subscribed */
    public function scopeSubscribed(Builder $builder)
    {
        return $builder->whereHas('activeSubscription');
    }

    /** Scope Unsubscribed */
    public function scopeUnsubscribed(Builder $builder)
    {
        return $builder->whereDoesntHave('activeSubscription');
    }

    /**
     * Active Subscription
     * @return \Illuminate\Database\Eloquent\Builder<Subscription>
     */
    public function activeSubscription()
    {
        return $this->hasOne(
            Subscription::class,
            'user_id',
            'user_id'
        )->active();
    }


    /** Subscriptions */
    public function subscriptions()
    {
        return $this->hasMany(
            Subscription::class,
            'user_id',
            'user_id'
        );
    }

    /** Payments */
    public function payments()
    {
        return $this->hasMany(
            Payment::class,
            'user_id',
            'user_id'
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

    /**
     * Get Farmer Title
     * @return string
     */
    public function getFarmerTitle()
    {
        return $this->data['farmerTitle'] ?? 'TGUser';
    }


    /**
     * Get Username
     * @return string
     */
    public function getUsername()
    {
        return $this->data['user']['username'] ?? null;
    }

    /**
     * Get Photo URL
     * @return string
     */
    public function getPhotoUrl()
    {
        return $this->data['user']['photo_url'] ?? null;
    }
}