<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Str;

final class LocalAccountService
{
    public function emailExists(string $email): bool
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->exists();
    }

    public function findLocalUserByEmail(string $email): ?User
    {
        return User::query()
            ->where('account_type', 'local')
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->first();
    }

    public function createLocalUser(string $email, string $password): User
    {
        $normalizedEmail = $this->normalizeEmail($email);

        return User::query()->create([
            'name' => $this->defaultName($normalizedEmail),
            'username' => $this->generateUniqueUsername(),
            'account_type' => 'local',
            'email' => $normalizedEmail,
            'phone' => null,
            'email_verified_at' => now(),
            'password' => $password,
            'avatar_url' => null,
            'bio' => null,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ]);
    }

    public function markLoggedIn(User $user): void
    {
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(10),
        ])->save();
    }

    private function defaultName(string $email): string
    {
        $localPart = trim(Str::before($email, '@'));
        if ($localPart === '') {
            return '用户';
        }

        return Str::limit($localPart, 80, '');
    }

    private function generateUniqueUsername(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $username = 'user_'.Str::lower(Str::random(12));
            if (! User::query()->where('username', $username)->exists()) {
                return $username;
            }
        }

        throw new \RuntimeException('Unable to generate a unique username.');
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
