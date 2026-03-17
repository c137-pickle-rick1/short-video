<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'handle',
        'user_id',
        'enabled',
        'last_discovered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
        ];
    }

    public function linkedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_source_links')
            ->withPivot(['relationship', 'is_primary', 'verified_at'])
            ->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'source_follows')->withTimestamps();
    }

    public function importedVideos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
}
