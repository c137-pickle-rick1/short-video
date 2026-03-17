<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserExternalAccount extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'handle',
        'profile_url',
        'raw_payload',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
