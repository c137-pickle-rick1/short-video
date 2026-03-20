<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'origin',
        'tweet_id',
        'source_id',
        'uploader_user_id',
        'title',
        'caption',
        'description',
        'storage_disk',
        'storage_path',
        'poster_url',
        'playback_url',
        'hls_url',
        'duration_text',
        'duration_seconds',
        'width',
        'height',
        'visibility',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_likes')->withTimestamps();
    }

    public function bookmarkedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_bookmarks')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(VideoComment::class)
            ->whereNull('parent_id')
            ->whereNull('deleted_at');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(VideoComment::class)->whereNull('deleted_at');
    }
}
