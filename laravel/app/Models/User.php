<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'account_type',
        'email',
        'phone',
        'email_verified_at',
        'password',
        'avatar_url',
        'bio',
        'last_login_at',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value): ?string {
                if (! is_string($value) || trim($value) === '') {
                    return null;
                }

                $normalizedValue = trim($value);
                $managedAvatarPath = self::managedAvatarPath($normalizedValue);

                if ($managedAvatarPath === null) {
                    return $normalizedValue;
                }

                $segments = explode('/', $managedAvatarPath, 3);
                if (count($segments) !== 3 || $segments[0] !== 'avatars') {
                    return $normalizedValue;
                }

                return route('managed-avatar.show', [
                    'user' => $segments[1],
                    'filename' => $segments[2],
                ], false);
            }
        );
    }

    public function linkedSources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'user_source_links')
            ->withPivot(['relationship', 'is_primary', 'verified_at'])
            ->withTimestamps();
    }

    public function followedSources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'source_follows')->withTimestamps();
    }

    public function followingUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_follows', 'follower_user_id', 'followed_user_id')
            ->withTimestamps();
    }

    public function externalAccounts(): HasMany
    {
        return $this->hasMany(UserExternalAccount::class);
    }

    public function followerUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_follows', 'followed_user_id', 'follower_user_id')
            ->withTimestamps();
    }

    public function uploadedVideos(): HasMany
    {
        return $this->hasMany(Video::class, 'uploader_user_id');
    }

    public function likedVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_likes')->withTimestamps();
    }

    public function bookmarkedVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_bookmarks')->withTimestamps();
    }

    public function videoComments(): HasMany
    {
        return $this->hasMany(VideoComment::class);
    }

    private static function managedAvatarPath(string $avatarUrl): ?string
    {
        $parsedAvatarPath = parse_url($avatarUrl, PHP_URL_PATH);
        if (! is_string($parsedAvatarPath) || trim($parsedAvatarPath) === '') {
            return null;
        }

        $normalizedPath = '/'.ltrim($parsedAvatarPath, '/');

        if (Str::startsWith($normalizedPath, '/storage/avatars/')) {
            $candidatePath = ltrim(Str::after($normalizedPath, '/storage/'), '/');
        } elseif (Str::startsWith($normalizedPath, '/avatars/')) {
            $candidatePath = ltrim($normalizedPath, '/');
        } else {
            return null;
        }

        $avatarHost = parse_url($avatarUrl, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($avatarHost) && $avatarHost !== '' && $avatarHost !== $appHost) {
            return null;
        }

        return $candidatePath;
    }
}
