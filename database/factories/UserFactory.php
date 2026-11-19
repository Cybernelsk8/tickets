<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Assign the super administrator role after creating the user.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('super_admin'));
    }

    /**
     * Assign the mail manager role after creating the user.
     */
    public function mailManager(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('mail_manager'));
    }

    /**
     * Assign the area manager role after creating the user.
     */
    public function jefeArea(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('jefe_area'));
    }

    /**
     * Assign the collaborator role after creating the user.
     */
    public function colaborador(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('colaborador'));
    }

    /**
     * Assign the consultant role after creating the user.
     */
    public function consultor(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('consultor'));
    }
}
