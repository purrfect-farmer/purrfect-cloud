<?php

namespace App\Models;

use App\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'starts_at',
        'ends_at',
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }


    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        /** Created */
        static::created(function (Subscription $subscription) {
            $subscription->sendSubscriptionStatusMessage(true);
        });

        /** Updated */
        static::updated(function (Subscription $subscription) {
            if ($subscription->wasChanged('ends_at')) {
                $subscription->sendSubscriptionStatusMessage(
                    $subscription->status === 'active'
                );
            }
        });
    }



    /**
     * Send Status Message
     * @param bool $active
     * @return void
     */
    public function sendSubscriptionStatusMessage($active = true)
    {
        /** Message Key */
        $key =  implode(':', [
            'cloud-subscription',
            $this->user_id
        ]);

        /** Status */
        $status = $active ?
            '<b>✅ Status:</b> Activated' :
            '<b>❌ Status:</b> Expired';

        /** Message */
        $message = $active ?
            'Cloud Subscription has been activated, you can now use all available Cloud Services. <b>(Subscription Ends: ' . $this->ends_at . ')</b>' :
            'Cloud Subscription has expired, all services has been suspended. Kindly make payment to resume.';

        /** Date */
        $date = now();

        /** Send Message */
        Helpers::sendCloudFarmerMessage(
            $key,
            [
                "<b>⛅ Cloud Subscription</b>",
                "$status",
                "<b>🗓️ Date</b>: $date",
                "<blockquote>$message</blockquote>",
            ],
            [
                'chat_id' => $this->user_id,
                'disable_notification' => false,
            ],
            false
        );
    }

    /** Scope Active */
    public function scopeActive(Builder $builder)
    {
        return $builder->where('status', 'active')
            ->where('ends_at', '>', now());
    }

    /** Account */
    public function account()
    {
        return $this->belongsTo(
            Account::class,
            'user_id',
            'user_id'
        );
    }
}
