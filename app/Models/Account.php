<?php

namespace App\Models;

use App\Helpers;
use Illuminate\Database\Eloquent\Builder;
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
        'is_connected',
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
            $account->sendStatusMessage(true);
        });


        /** Send Disconnected Message */
        static::deleted(function (Account $account) {
            $account->sendStatusMessage(false);
        });
    }

    /**
     * Send Status Message
     * @param bool $connected
     * @return void
     */
    public function sendStatusMessage($connected = true)
    {
        /** Message Key */
        $key =  implode(':', [
            $this->farmer,
            $this->user_id,
            'sync'
        ]);

        /** Title */
        $title = [
            'funatic' => '🤡 Funatic Farmer',
            'gold-eagle' => '🥇 Gold Eagle Farmer',
            'slotcoin' => '🎰 Slotcoin Farmer',
            'dreamcoin' => '🔋 DreamCoin Farmer',
            'hrum' => '🥠 Hrum Farmer',
        ][$this->farmer];


        /** User ID */
        $id = $this->user_id;

        /** Username */
        $username =
            '@' . Str::of(
                $this->telegram_web_app['initDataUnsafe']['user']['username'] ?? '' ?: $id
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
        $message = $connected ?
            'Automatic cloud farming has commenced.' :
            'Please kindly re-open the farmer and sync to cloud.';

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
                'chat_id' => $this->user_id,
                'disable_notification' => false,
                'message_thread_id' => ''
            ]
        );
    }

    /** Scope Connected */
    public function scopeConnected(Builder $builder)
    {
        return $builder->where('is_connected', true);
    }

    /** Scope Farmer */
    public function scopeFarmer(Builder $builder, string $farmer)
    {
        return $builder->where('farmer', $farmer);
    }

    /** Connect */
    public function connect()
    {
        $this->update(['is_connected' => true]);
        $this->sendStatusMessage(true);
    }

    /** Disconnect */
    public function disconnect()
    {
        $this->update(['is_connected' => false]);
        $this->sendStatusMessage(false);
    }
}
