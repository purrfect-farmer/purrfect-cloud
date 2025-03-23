<?php

namespace App\Models;

use App\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'is_connected' => 'boolean',
            'telegram_web_app' => 'array',
            'headers' => 'array',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        /** Created */
        static::created(function (Account $account) {
            $account->sendStatusMessage(true);
        });

        /** Updated */
        static::updated(function (Account $account) {
            if ($account->wasChanged('is_connected')) {
                $account->sendStatusMessage(
                    $account->is_connected
                );
            }
        });

        /** Deleted */
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
        /** Status */
        $status = $connected ?
            '<b>✅ Status:</b> Connected' :
            '<b>❌ Status:</b> Disconnected';

        /** Message */
        $message = $connected ?
            'Automatic cloud farming has commenced.' :
            'Please kindly re-open the farmer and sync to cloud.';

        /** Send Message */
        Helpers::sendUserMessage(
            'sync',
            $this,
            [
                "$status",
                "<blockquote><b>$message</b></blockquote>",
            ],
        );
    }

    /** Scope Connected */
    public function scopeConnected(Builder $builder, $connected = true)
    {
        return $builder->where('is_connected', $connected);
    }

    /** Scope Farmer */
    public function scopeFarmer(Builder $builder, string $farmer)
    {
        return $builder->where('farmer', $farmer);
    }

    /** Scope User ID */
    public function scopeUserId(Builder $builder, int|string $id)
    {
        return $builder->where('user_id', $id);
    }

    /** Scope Needs Refetch */
    public function scopeNeedsRefetch(Builder $builder)
    {
        return $builder
            ->where('is_connected', false)
            ->orWhere(
                'updated_at',
                '<',
                now()->subMinutes(30)
            );
    }

    /** Connect */
    public function connect()
    {
        return $this->update(['is_connected' => true]);
    }

    /** Disconnect */
    public function disconnect()
    {
        return $this->update(['is_connected' => false]);
    }

    /**
     * Get User-Agent
     */
    public function getUserAgent()
    {
        return $this->headers['User-Agent'] ?? Helpers::getUserAgent($this->user_id);
    }

    /** Override Auth */
    public function setAuthorizationHeader($value)
    {
        $this->headers = collect($this->headers)->map(
            fn($v, $k) => strtolower($k) === 'authorization' ? $value : $v
        )->all();
    }

    /** Session */
    public function session()
    {
        return $this->hasOne(
            MadelineSession::class,
            'user_id',
            'user_id'
        );
    }
}
