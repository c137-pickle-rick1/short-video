<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AuthEmailCode extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'purpose',
        'code_hash',
        'sent_at',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function scopeForEmailAndPurpose(Builder $query, string $email, string $purpose): Builder
    {
        return $query
            ->where('email', mb_strtolower(trim($email)))
            ->where('purpose', $purpose);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('consumed_at');
    }
}
