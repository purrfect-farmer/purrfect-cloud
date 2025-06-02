<?php

namespace App\Models;

use App\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Farmer extends Model
{
    /** @use HasFactory<\Database\Factories\FarmerFactory> */
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
        static::created(function (Farmer $account) {
            $account->sendStatusMessage(true);
        });

        /** Updated */
        static::updated(function (Farmer $account) {
            if ($account->wasChanged('is_connected')) {
                $account->sendStatusMessage(
                    $account->is_connected
                );
            }
        });

        /** Deleted */
        static::deleted(function (Farmer $account) {
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
            'Cloud Farming has commenced. Ensure to keep track of your account.' :
            'Cloud Farming has been paused. Launch the Farmer and Sync to Cloud to resume.';

        $config = config('farmer.drops')[$this->farmer];
        $link = $config['telegram_link'];

        /** Send Message */
        Helpers::sendUserMessage(
            'sync',
            $this,
            [
                "$status",
                "\n<blockquote>(<a href=\"$link\">Open Telegram Bot</a>) <b><i>$message</i></b></blockquote>",
            ],
        );
    }

    /** Scope Subscribed */
    public function scopeSubscribed(Builder $builder)
    {
        return $builder->whereHas('subscription');
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
                now()->subMinutes(20)
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

        return $this;
    }

    /**
     * Set Headers
     */
    public function setHeaders(array $headers = [])
    {
        $this->headers = array_merge($this->headers ?? [], $headers);

        return $this;
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

    /**
     * Active Subscription
     * @return \Illuminate\Database\Eloquent\Builder<Subscription>
     */
    public function subscription()
    {
        return $this->hasOne(
            Subscription::class,
            'user_id',
            'user_id'
        )->active();
    }

    /**
     * Get Farmer Title
     * @return string
     */
    public function getFarmerTitle()
    {
        return $this->account->getFarmerTitle();
    }

    /** Get Username */
    public function getUsername()
    {
        return $this->getInitDataUnsafe()['user']['username'] ?? null;
    }

    /**
     * Get Photo URL
     * @return string
     */
    public function getPhotoUrl()
    {
        return $this->getInitDataUnsafe()['user']['photo_url'] ?? null;
    }

    /**
     * Get Init Data
     * @return string
     */
    public function getInitData()
    {
        return $this->telegram_web_app['initData'];
    }

    /**
     * Get Init Data Unsafe
     * @return array
     */
    public function getInitDataUnsafe()
    {
        $parsed = $this->getInitDataParsed();

        return [
            ...$parsed,
            'user' => json_decode($parsed['user'], true),
        ];
    }


    /**
     * Get Init Data Parsed
     * @return array
     */
    public function getInitDataParsed()
    {
        parse_str($this->getInitData(), $data);
        return $data;
    }
}
