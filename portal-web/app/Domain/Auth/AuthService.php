<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function register(array $payload): array
    {
        $email = strtolower((string) ($payload['email'] ?? ''));
        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Email already registered.']);
        }

        if ($username === '') {
            $username = $this->buildUsernameFromEmail($email);
        }

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'timezone' => $payload['timezone'] ?? 'UTC',
            'locale' => $payload['locale'] ?? 'en',
            'role' => 'member',
            'status' => 'active',
            'plan_code' => 'free',
        ]);

        $deviceName = (string) ($payload['device_name'] ?? 'web');
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'role' => $user->role,
            ],
            'token' => $token,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function login(array $payload): array
    {
        $credential = (string) ($payload['name'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        // Support login by email or username (case-insensitive)
        if (str_contains($credential, '@')) {
            $user = User::where('email', strtolower($credential))->first();
        } else {
            $user = User::whereRaw('LOWER(username) = ?', [strtolower($credential)])->first();
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['name' => 'Invalid credentials.']);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['name' => 'Account is not active.']);
        }

        $user->update(['last_login_at' => now()]);

        $deviceName = (string) ($payload['device_name'] ?? 'web');
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }

    private function buildUsernameFromEmail(string $email): string
    {
        $base = strtolower((string) Str::before($email, '@'));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $base) ?: 'user';
        $normalized = trim($normalized, '-._');
        $candidate = $normalized !== '' ? $normalized : 'user';

        if (! User::where('username', $candidate)->exists()) {
            return $candidate;
        }

        do {
            $candidate = $candidate . '-' . Str::lower(Str::random(4));
        } while (User::where('username', $candidate)->exists());

        return $candidate;
    }
}
