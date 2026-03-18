<?php

namespace App\ShortVideo\Repositories;

use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Database\ConnectionInterface;

final class CreatorIdentityRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function syncSourceCreatorIdentity(int $sourceId, string $handle): ?int
    {
        $userId = $this->ensureExternalCreatorIdentity($handle, '@'.ShortVideoData::normalizeHandle($handle), null);
        if ($userId === null) {
            return null;
        }

        $this->db->table('sources')->where('id', $sourceId)->update(['user_id' => $userId]);

        return $userId;
    }

    public function ensureExternalCreatorIdentity(?string $handle, ?string $name, ?string $avatarUrl): ?int
    {
        $normalizedHandle = ShortVideoData::normalizeHandle($handle);
        if ($normalizedHandle === '') {
            return null;
        }

        $existingAccount = $this->db->table('user_external_accounts')
            ->where('provider', 'x')
            ->where('handle', $normalizedHandle)
            ->first();

        if ($existingAccount) {
            $userId = (int) $existingAccount->user_id;
            $this->refreshExternalCreatorUser($userId, $normalizedHandle, $name, $avatarUrl);

            return $userId;
        }

        $existingExternalUser = $this->db->table('users')
            ->where('username', $normalizedHandle)
            ->where('account_type', 'external_creator')
            ->first();

        if ($existingExternalUser) {
            $userId = (int) $existingExternalUser->id;
            $this->refreshExternalCreatorUser($userId, $normalizedHandle, $name, $avatarUrl);
        } else {
            $timestamp = now();
            $userId = (int) $this->db->table('users')->insertGetId([
                'name' => is_string($name) && trim($name) !== '' ? trim($name) : '@'.$normalizedHandle,
                'username' => $this->generateUniqueExternalUsername($normalizedHandle),
                'account_type' => 'external_creator',
                'email' => null,
                'phone' => null,
                'email_verified_at' => null,
                'password' => null,
                'avatar_url' => is_string($avatarUrl) && trim($avatarUrl) !== '' ? trim($avatarUrl) : null,
                'bio' => null,
                'last_login_at' => null,
                'remember_token' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $timestamp = now();
        $this->db->table('user_external_accounts')->updateOrInsert(
            [
                'provider' => 'x',
                'handle' => $normalizedHandle,
            ],
            [
                'user_id' => $userId,
                'provider_user_id' => null,
                'profile_url' => 'https://x.com/'.$normalizedHandle,
                'raw_payload' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]
        );

        return $userId;
    }

    private function refreshExternalCreatorUser(int $userId, string $handle, ?string $name, ?string $avatarUrl): void
    {
        $user = $this->db->table('users')
            ->select('account_type', 'name', 'avatar_url')
            ->where('id', $userId)
            ->first();

        if (! $user || ($user->account_type ?? 'local') !== 'external_creator') {
            return;
        }

        $updates = [
            'name' => is_string($name) && trim($name) !== '' ? trim($name) : (($user->name ?? null) ?: '@'.$handle),
            'updated_at' => now(),
        ];

        if (is_string($avatarUrl) && trim($avatarUrl) !== '') {
            $updates['avatar_url'] = trim($avatarUrl);
        }

        $this->db->table('users')->where('id', $userId)->update($updates);
    }

    private function generateUniqueExternalUsername(string $handle): string
    {
        $base = preg_replace('/[^a-z0-9_]+/', '_', $handle) ?? '';
        $base = trim($base, '_');
        $base = $base !== '' ? substr($base, 0, 24) : 'x_user';

        $candidate = $base;
        $counter = 1;
        while ($this->db->table('users')->where('username', $candidate)->exists()) {
            $suffix = $counter === 1 ? '_x' : '_x'.$counter;
            $candidate = substr($base, 0, max(1, 32 - strlen($suffix))).$suffix;
            $counter++;
        }

        return $candidate;
    }
}
