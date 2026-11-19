<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    /**
     * Artificial accounts seeded for local development and testing.
     *
     * @var list<array{name: string, email: string}>
     */
    public const array ARTIFICIAL_USERS = [
        ['name' => 'Test User', 'email' => 'test@example.com'],
        ['name' => 'Frontend QA', 'email' => 'frontend-qa@example.com'],
        ['name' => 'Frontend Second', 'email' => 'fe@example.com'],
        ['name' => 'Ana Fictional', 'email' => 'ana@example.com'],
        ['name' => 'Bruno Fictional', 'email' => 'bruno@example.com'],
        ['name' => 'Carla Fictional', 'email' => 'carla@example.com'],
    ];

    /**
     * Seed the artificial accounts and, when configured, the personal account.
     *
     * Existing accounts are left untouched so reruns preserve local edits.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Test users are restricted to local and testing environments.');
        }

        foreach (self::ARTIFICIAL_USERS as $user) {
            $this->firstOrCreateUser($user['email'], $user['name']);
        }

        $personalEmail = config('app.personal_user.email');

        if (filled($personalEmail)) {
            $this->firstOrCreateUser(
                $personalEmail,
                config('app.personal_user.name') ?: 'Personal User',
                config('app.personal_user.password') ?: 'password',
            );
        }
    }

    private function firstOrCreateUser(string $email, string $name, ?string $password = null): User
    {
        $attributes = ['name' => $name, 'email' => $email];

        if ($password !== null) {
            $attributes['password'] = $password;
        }

        return User::query()->firstOrCreate(['email' => $email], User::factory()->raw($attributes));
    }
}
