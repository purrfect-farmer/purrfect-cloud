<?php

namespace App\Models;

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
}
