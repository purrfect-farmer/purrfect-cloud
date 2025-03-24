<?php

namespace App\Models;

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
